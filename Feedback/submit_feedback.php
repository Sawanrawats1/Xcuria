<?php
session_start();
require_once('../Includes/db_connect.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if ($event_id <= 0 || $rating < 1 || $rating > 5 || empty($comment)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data provided.']);
    exit;
}

// Check if user has participated in the event
$check_participation = $conn->prepare("SELECT 1 FROM participation WHERE user_id = ? AND event_id = ?");
$check_participation->bind_param("ii", $user_id, $event_id);
$check_participation->execute();
$result_participation = $check_participation->get_result();

if ($result_participation->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'You must have participated in this event to give feedback.']);
    $check_participation->close();
    exit;
}
$check_participation->close();

// Check if feedback already exists for this user and event
$check_feedback = $conn->prepare("SELECT 1 FROM feedback WHERE user_id = ? AND event_id = ?");
$check_feedback->bind_param("ii", $user_id, $event_id);
$check_feedback->execute();
$result_feedback = $check_feedback->get_result();

if ($result_feedback->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'You have already submitted feedback for this event.']);
    $check_feedback->close();
    exit;
}
$check_feedback->close();

// Insert the new feedback
$stmt = $conn->prepare("INSERT INTO feedback (user_id, event_id, rating, comment) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiis", $user_id, $event_id, $rating, $comment);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Feedback submitted successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>