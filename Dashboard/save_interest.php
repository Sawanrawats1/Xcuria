<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// 1. Ensure session is valid
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized access. Please log in again."
    ]);
    exit;
}

// 2. Get DB connection
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

// 4. Check if interest exists (optional, but safe)
$checkInterest = $conn->prepare("SELECT id FROM interests WHERE id = ?");
$checkInterest->bind_param("i", $interest_id);
$checkInterest->execute();
$interestResult = $checkInterest->get_result();

if ($interestResult->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid interest selected."
    ]);
    exit;
}
$checkInterest->close();

// 5. Prevent duplicates
$checkDuplicate = $conn->prepare("SELECT 1 FROM user_interests WHERE user_id = ? AND interest_id = ?");
$checkDuplicate->bind_param("ii", $user_id, $interest_id);
$checkDuplicate->execute();
$duplicateResult = $checkDuplicate->get_result();

if ($duplicateResult->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "You've already saved this interest."
    ]);
    exit;
}
$checkDuplicate->close();

// 6. Insert into user_interests
$insert = $conn->prepare("INSERT INTO user_interests (user_id, interest_id) VALUES (?, ?)");
$insert->bind_param("ii", $user_id, $interest_id);

if ($insert->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Interest saved successfully!"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Database error. Please try again."
    ]);
}

$insert->close();
$conn->close();
?>
