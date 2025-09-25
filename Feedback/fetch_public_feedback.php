<?php
session_start();
require_once('../Includes/db_connect.php');

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "student") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Query to fetch feedback for events the user participated in, but from other students
$query = "
    SELECT 
        e.title,
        f.rating,
        f.comment,
        u.name AS student_name
    FROM feedback f
    JOIN events e ON f.event_id = e.id
    JOIN users u ON f.user_id = u.id
    WHERE f.user_id != ? AND f.event_id IN (
        SELECT event_id FROM participation WHERE user_id = ?
    )
    ORDER BY f.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$feedback = [];
while ($row = $result->fetch_assoc()) {
    $feedback[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['status' => 'success', 'feedback' => $feedback]);
?>