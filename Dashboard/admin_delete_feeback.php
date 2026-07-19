<?php
session_start();
require_once('../Includes/db_connect.php');

// Check if user is logged in and has the 'admin' role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // If not an admin, redirect to login page to prevent unauthorized access
    header("Location: ../Auth/login_form.html");
    exit();
}

// Check if a valid feedback ID has been provided in the URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $feedbackId = $_GET['id'];
    
    // Use a prepared statement to safely delete the feedback entry
    // This protects against SQL injection attacks
    $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->bind_param("i", $feedbackId);

    if ($stmt->execute()) {
        // If deletion is successful, redirect back to the admin dashboard
        // with a success message
        header("Location: admin_dashboard.php?message=Feedback deleted successfully.");
        exit();
    } else {
        // If there's an error with the deletion, redirect with an error message
        header("Location: admin_dashboard.php?error=Error deleting feedback: " . $stmt->error);
        exit();
    }
    // Close the prepared statement
    $stmt->close();
} else {
    // If no valid ID was provided, redirect with an error message
    header("Location: admin_dashboard.php?error=Invalid request.");
    exit();
}

// Close the database connection
$conn->close();
?>