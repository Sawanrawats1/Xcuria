<?php
session_start();
require_once('../Includes/db_connect.php');

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'An unexpected error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the user is logged in as a student
    if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "student") {
        $response['message'] = 'Authentication failed. Please log in again.';
        echo json_encode($response);
        exit();
    }

    // Check if event_id is set
    if (!isset($_POST['event_id'])) {
        $response['message'] = 'Invalid event ID provided.';
        echo json_encode($response);
        exit();
    }

    $userId = $_SESSION['user_id'];
    $eventId = filter_var($_POST['event_id'], FILTER_SANITIZE_NUMBER_INT);

    // Prepare and execute the DELETE statement
    $deleteStmt = $conn->prepare("DELETE FROM feedback WHERE user_id = ? AND event_id = ?");
    
    // Check if the prepare statement was successful
    if (!$deleteStmt) {
        $response['message'] = 'Database query preparation failed: ' . $conn->error;
        echo json_encode($response);
        exit();
    }
    
    $deleteStmt->bind_param("ii", $userId, $eventId);

    if ($deleteStmt->execute()) {
        if ($deleteStmt->affected_rows > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Feedback deleted successfully!';
        } else {
            $response['message'] = 'No feedback found to delete for this event.';
        }
    } else {
        $response['message'] = 'Failed to delete feedback. Database error: ' . $deleteStmt->error;
    }

    $deleteStmt->close();
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
$conn->close();
?>