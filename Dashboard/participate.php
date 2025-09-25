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
$action = isset($_POST['action']) ? $_POST['action'] : ''; // Get the action from the POST data

if ($event_id <= 0 || ($action !== 'add' && $action !== 'cancel')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit();
}

if ($action === 'add') {
    // Logic for adding a user to an event
    $stmt = $conn->prepare("INSERT INTO participation (user_id, event_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $event_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Successfully joined event!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to participate.']);
    }
    $stmt->close();
} elseif ($action === 'cancel') {
    // Logic for canceling participation
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
}

$conn->close();
?>