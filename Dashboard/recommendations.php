<?php
/**
 * recommendations.php
 *
 * Drop this into Xcuria - Copy/Dashboard/ alongside student.php.
 * It calls the Python recommender microservice (must be running:
 * `python app.py` on port 5000) and returns/echoes the personalized
 * "Recommended For You" list for the logged-in student.
 *
 * Two ways to use it:
 *   1. include it directly inside student.php where you want the section
 *      to appear (see the snippet in README.md), OR
 *   2. call it as its own AJAX endpoint from JS, same pattern as your
 *      existing save_interest.php / fetch_notifications.php calls.
 */

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$top_n = isset($_GET['top_n']) ? intval($_GET['top_n']) : 5;

$recommender_url = "http://localhost:5000/recommend?user_id=" . urlencode($user_id) . "&top_n=" . urlencode($top_n);

$ch = curl_init($recommender_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // fail fast if the ML service is down
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json');

if ($response === false) {
    // Recommender service unreachable — fail gracefully rather than
    // breaking the whole dashboard. The frontend should fall back to
    // showing the normal category-filtered event list in this case.
    echo json_encode([
        "status" => "error",
        "message" => "Recommendation service unavailable.",
        "detail" => $curl_error
    ]);
    exit;
}

echo $response;
?>
