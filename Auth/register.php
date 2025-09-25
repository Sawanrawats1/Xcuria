<?php
session_start();
require_once('../Includes/db_connect.php');

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $role = $_POST["role"];
    
    $department = ($role === 'student' && isset($_POST["department"])) ? trim($_POST["department"]) : null;
    $year = ($role === 'student' && isset($_POST["year"])) ? trim($_POST["year"]) : null;

    if ($password !== $confirm_password) {
        echo json_encode(["success" => false, "message" => "Passwords do not match."]);
        exit();
    }

    if (!preg_match("/^(?=.*[A-Za-z])(?=.*\d).{6,}$/", $password)) {
        echo json_encode([
            "success" => false,
            "message" => "Password must be at least 6 characters long and contain both letters and numbers."
        ]);
        exit();
    }

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Email is already registered."]);
        exit();
    }
    $check->close();

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    if ($role === 'student') {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, department, year, login_count) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("ssssss", $name, $email, $hashed_password, $role, $department, $year);
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, login_count) VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
    }

    if ($stmt->execute()) {
        $_SESSION["user_id"] = $stmt->insert_id;
        $_SESSION["user_name"] = $name;
        $_SESSION["user_email"] = $email;
        $_SESSION["user_role"] = $role;

        // --- UPDATED LOGIC FOR NOTIFICATIONS STARTS HERE ---

        // A helper function to create notifications for a specific set of users
        function createNotifications($conn, $recipient_role, $message) {
            $stmt_recipients = $conn->prepare("SELECT id FROM users WHERE role = ?");
            $stmt_recipients->bind_param("s", $recipient_role);
            $stmt_recipients->execute();
            $result = $stmt_recipients->get_result();
            if ($result->num_rows > 0) {
                $stmt_insert = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
                $stmt_insert->bind_param("is", $recipient_id, $message_safe);
                
                $message_safe = $message;
                while ($row = $result->fetch_assoc()) {
                    $recipient_id = $row['id'];
                    $stmt_insert->execute();
                }
                $stmt_insert->close();
            }
            $stmt_recipients->close();
        }

        $notification_message = "A new user, {$name} ({$role}), has registered.";
        
        // Notification logic based on the new user's role
        switch ($role) {
            case 'student':
                // Notify Faculty, Group, and Admin
                createNotifications($conn, 'faculty', "A new student, {$name}, has registered.");
                createNotifications($conn, 'group', "A new student, {$name}, has registered.");
                createNotifications($conn, 'admin', "A new student, {$name}, has registered.");
                break;
            case 'group':
                // Notify Students and Admin
                createNotifications($conn, 'student', "A new group, {$name}, has registered.");
                createNotifications($conn, 'admin', "A new group, {$name}, has registered.");
                break;
            case 'faculty':
                // Notify Students and Admin
                createNotifications($conn, 'student', "A new faculty member, {$name}, has registered.");
                createNotifications($conn, 'admin', "A new faculty member, {$name}, has registered.");
                break;
            case 'admin':
                // No specific notifications for new admins, but they will receive all others
                break;
        }

        // --- NEW REAL-TIME NOTIFICATION LOGIC ---
        // This is where you would trigger your real-time update.
        // A long-running WebSocket server is required to do this.
        // Example:
        // $ws_client->send(json_encode(['type' => 'new_registration', 'data' => ['studentName' => $name, 'role' => $role]]));

        $redirect = match ($role) {
            'faculty' => '../dashboard/faculty.php',
            'group'   => '../dashboard/group.php',
            default   => '../dashboard/student.php',
        };

        echo json_encode(["success" => true, "redirect" => $redirect]);
        exit();

    } else {
        echo json_encode(["success" => false, "message" => "Registration failed. Please try again."]); 
    }

    $stmt->close();
    $conn->close();
}
?>
