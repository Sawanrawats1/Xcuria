<?php
session_start();
require_once('../Includes/db_connect.php');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the user is logged in
if (!isset($_SESSION["user_id"])) {
    echo "Error: User not logged in.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $interest_id = isset($_POST['interest_id']) ? intval($_POST['interest_id']) : 0;
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $time = trim($_POST['time'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if ($id <= 0 || empty($title) || empty($date) || empty($time) || empty($location) || empty($description)) {
        echo "Error: Invalid or missing form data.";
        exit();
    }

    $stmt = $conn->prepare("UPDATE events SET interest_id = ?, title = ?, date = ?, time = ?, location = ?, description = ? WHERE id = ? AND created_by = ?");
    
    if (!$stmt) {
        echo "Error: Could not prepare statement. " . $conn->error;
        exit();
    }
    
    // Check if bind_param is successful
    if (!$stmt->bind_param("issssssi", $interest_id, $title, $date, $time, $location, $description, $id, $_SESSION['user_id'])) {
        echo "Error: Could not bind parameters. " . $stmt->error;
        exit();
    }
    
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "Error: Failed to execute statement. " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

} else {
    echo "Error: Invalid request method.";
}
?>