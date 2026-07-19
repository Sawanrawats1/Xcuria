<?php
require_once('../Includes/db_connect.php');

$interest_id = intval($_GET['interest_id']);
$sql = "SELECT name, department, year FROM users 
        JOIN user_interests ON users.id = user_interests.user_id 
        WHERE user_interests.interest_id = ? AND users.role = 'student'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $interest_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

header('Content-Type: application/json');
echo json_encode($students);
?>
