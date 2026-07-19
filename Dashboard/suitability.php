<?php
/**
 * suitability.php
 * Returns the ranked "who is suitable for this activity" list for one of
 * the logged-in faculty member's own events.
 * GET params: event_id (required), top_n (optional, default 10)
 */

session_start();
require_once __DIR__ . '/../Includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'faculty') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$faculty_id = $_SESSION['user_id'];
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$top_n = isset($_GET['top_n']) ? intval($_GET['top_n']) : 10;

if (!$event_id) {
    echo json_encode(["status" => "error", "message" => "event_id is required"]);
    exit;
}

// Ownership check, plus: only allow viewing suitability rankings once the
// event has happened, for consistency with the attendance-marking guard.
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
        "message" => "Suitability ranking is available once the event has taken place."
    ]);
    exit;
}

$url = "http://localhost:5000/suitability?event_id=" . urlencode($event_id) . "&top_n=" . urlencode($top_n);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(["status" => "error", "message" => "Suitability service unavailable", "detail" => $curl_error]);
    exit;
}

echo $response;
?>