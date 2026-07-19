<?php
session_start();
require_once('../Includes/db_connect.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;

if ($event_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid event ID.']);
    exit();
}

$stmt = $conn->prepare("DELETE FROM participation WHERE user_id = ? AND event_id = ?");
$stmt->bind_param("ii", $user_id, $event_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Participation cancelled successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'You are not participating in this event.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>