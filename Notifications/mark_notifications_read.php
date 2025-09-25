<?php
session_start();
require_once('../Includes/db_connect.php');

header('Content-Type: application/json');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Notifications marked as read.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to mark notifications as read.']);
}

$stmt->close();
$conn->close();
?>