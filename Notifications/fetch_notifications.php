<?php
session_start();
header('Content-Type: application/json');

// Include your database connection file
require_once('../Includes/db_connect.php'); 

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch notifications from the database
    // CORRECTED: Added 'AND is_read = 0' to the SQL query
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    // Success response
    echo json_encode([
        'status' => 'success',
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    // Error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Database query failed: ' . $e->getMessage()
    ]);
}

$conn->close();
?>