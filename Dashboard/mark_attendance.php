<?php
/**
 * mark_attendance.php
 * Faculty marks present/absent for students registered in one of their
 * own events. Upserts into the `attendance` table (run schema_updates.sql
 * first if you haven't).
 *
 * Expects POST:
 *   event_id: int
 *   attendance: JSON string, e.g. {"3": "present", "7": "absent"}
 *               (keys = student_id, values = status)
 */

session_start();
require_once __DIR__ . '/../Includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'faculty') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$faculty_id = $_SESSION['user_id'];
$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$attendance_json = $_POST['attendance'] ?? '';

if (!$event_id || !$attendance_json) {
    echo json_encode(["status" => "error", "message" => "event_id and attendance are required"]);
    exit;
}

// Ownership check: faculty can only mark attendance for their OWN events,
// AND only once the event has actually happened — you can't mark someone
// "present" at something that hasn't occurred yet.
$check = $conn->prepare("SELECT id, date, time, end_date, end_time FROM events WHERE id = ? AND created_by = ?");
$check->bind_param("ii", $event_id, $faculty_id);
$check->execute();
$eventRow = $check->get_result()->fetch_assoc();

if (!$eventRow) {
    echo json_encode(["status" => "error", "message" => "You do not own this event"]);
    exit;
}

// Use the real end time when set; fall back to start time for older
// events created before end_date/end_time existed.
$endDate = $eventRow['end_date'] ?: $eventRow['date'];
$endTime = $eventRow['end_time'] ?: $eventRow['time'];
$eventDateTime = strtotime($endDate . ' ' . $endTime);
if ($eventDateTime > time()) {
    echo json_encode([
        "status" => "error",
        "message" => "Attendance can only be marked after the event has taken place."
    ]);
    exit;
}

$attendance = json_decode($attendance_json, true);
if (!is_array($attendance)) {
    echo json_encode(["status" => "error", "message" => "Invalid attendance payload"]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO attendance (event_id, student_id, status, marked_by)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)
");

$updated = 0;
foreach ($attendance as $student_id => $status) {
    $student_id = intval($student_id);
    $status = ($status === 'present') ? 'present' : 'absent';
    $stmt->bind_param("iisi", $event_id, $student_id, $status, $faculty_id);
    if ($stmt->execute()) {
        $updated++;
    }
}

echo json_encode(["status" => "success", "updated" => $updated]);
?>