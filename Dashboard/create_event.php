<?php
session_start();
require_once('../Includes/db_connect.php');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION["user_id"])) {
    $faculty_id = $_SESSION["user_id"];
    $interest_id = $_POST['interest_id'];
    $title = $_POST['title'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $end_date = $_POST['end_date'];
    $end_time = $_POST['end_time'];
    $location = $_POST['location'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO events (interest_id, created_by, title, date, time, end_date, end_time, location, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssssss", $interest_id, $faculty_id, $title, $date, $time, $end_date, $end_time, $location, $description);
    if ($stmt->execute()) {
        $last_id = $conn->insert_id;

        // Fetch the interest name for the new event
        $interest_name_stmt = $conn->prepare("SELECT name FROM interests WHERE id = ?");
        $interest_name_stmt->bind_param("i", $interest_id);
        $interest_name_stmt->execute();
        $interest_name_result = $interest_name_stmt->get_result();
        $interest_name = $interest_name_result->fetch_assoc()['name'];
        $interest_name_stmt->close();

        // =========================================================================
        // CORRECTED CODE: Use a JOIN to find students from the user_interests table
        // =========================================================================
        
        // Step 1: Find all students who have this same interest_id
        $students_stmt = $conn->prepare("
            SELECT u.id 
            FROM users u
            JOIN user_interests ui ON u.id = ui.user_id
            WHERE ui.interest_id = ? AND u.role = 'student'
        ");
        $students_stmt->bind_param("i", $interest_id);
        $students_stmt->execute();
        $students_result = $students_stmt->get_result();

        // Step 2: Loop through each student and create a notification
        if ($students_result->num_rows > 0) {
            $notification_message = "A new event titled '" . htmlspecialchars($title) . "' has been created for your interest group: " . htmlspecialchars($interest_name) . ".";
            $is_read = 0; // 0 for unread
            
            while ($student = $students_result->fetch_assoc()) {
                $student_id = $student['id'];
                
                $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, ?)");
                $notification_stmt->bind_param("isi", $student_id, $notification_message, $is_read);
                $notification_stmt->execute();
                $notification_stmt->close();
            }
        }
        $students_stmt->close();
        
        // =========================================================================
        // END OF CORRECTED CODE
        // =========================================================================

        // Echo the HTML for the new table row
        echo "<tr id='event-$last_id'>";
        echo "<td>" . htmlspecialchars($interest_name) . "</td>";
        echo "<td>" . htmlspecialchars($title) . "</td>";
        echo "<td>" . htmlspecialchars($date) . "</td>";
        echo "<td>" . htmlspecialchars($time) . "</td>";
        echo "<td>" . htmlspecialchars($location) . "</td>";
        echo "<td>" . htmlspecialchars($description) . "</td>";
        echo "<td>";
        echo "<button onclick=\"openEditModal($last_id)\">✏️ Edit</button>";
        echo "<button onclick=\"deleteEvent($last_id)\">🗑 Delete</button>";
        echo "</td>";
        echo "</tr>";

    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
} else {
    echo "Invalid request or not logged in.";
}
?>
