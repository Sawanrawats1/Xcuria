<?php
/**
 * sentiment.php
 * Returns per-feedback sentiment labels + per-event summary for all
 * feedback on the logged-in faculty member's own events.
 */

session_start();
require_once __DIR__ . '/../Includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'faculty') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$faculty_id = $_SESSION['user_id'];

$url = "http://localhost:5000/sentiment?faculty_id=" . urlencode($faculty_id);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(["status" => "error", "message" => "Sentiment service unavailable", "detail" => $curl_error]);
    exit;
}

echo $response;
?>
