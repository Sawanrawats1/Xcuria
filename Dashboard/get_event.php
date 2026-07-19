<?php
// Adjust the path as needed to correctly include your DB connection
require_once('../Includes/db_connect.php');

// Check if event_id is passed
if (!isset($_GET['event_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event_id']);
    exit;
}

$eventId = intval($_GET['event_id']);

// Prepare and run the SQL query
$sql = "SELECT * FROM events WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $eventId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Event not found']);
    exit;
}

// Return the event as JSON
$event = $result->fetch_assoc();
echo json_encode($event);
?>
