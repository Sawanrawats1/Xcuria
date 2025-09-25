<?php
require_once('../Includes/db_connect.php');


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $eventId = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param("i", $eventId);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }

    $stmt->close();
} else {
    echo "invalid";
}
?>