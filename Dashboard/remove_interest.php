<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// 1. Ensure session is valid and role is student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized access. Please log in again."
    ]);
    exit;
}

// 2. Include database connection
require_once('../Includes/db_connect.php');

// 3. Validate POST input
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['interest_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request. Missing interest ID."
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$interest_id = intval($_POST['interest_id']);

// 4. Check if the interest exists for the user
$checkExist = $conn->prepare("SELECT 1 FROM user_interests WHERE user_id = ? AND interest_id = ?");
$checkExist->bind_param("ii", $user_id, $interest_id);
$checkExist->execute();
$result = $checkExist->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Interest not found or already removed."
    ]);
    exit;
}
$checkExist->close();

// 5. Delete the interest
$delete = $conn->prepare("DELETE FROM user_interests WHERE user_id = ? AND interest_id = ?");
$delete->bind_param("ii", $user_id, $interest_id);

if ($delete->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Interest removed successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to remove interest. Please try again."
    ]);
}

$delete->close();
$conn->close();
?>
