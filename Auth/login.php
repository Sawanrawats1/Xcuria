<?php
session_start();
require_once('../Includes/db_connect.php');

header('Content-Type: application/json'); // Send JSON response

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["user_role"] = $user["role"];

            // Track login activity
            $update = $conn->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?");
            $update->bind_param("i", $user["id"]);
            $update->execute();
            $update->close();

            // Set redirect based on role
            $redirect = match ($user["role"]) {
                "admin" => "../dashboard/admin.php",
                "faculty" => "../dashboard/faculty.php",
                "group" => "../dashboard/group.php",
                default => "../dashboard/student.php"
            };

            echo json_encode([
                "success" => true,
                "redirect" => $redirect
            ]);
            exit;
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Incorrect password."
            ]);
            exit;
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Email not found."
        ]);
        exit;
    }

    $stmt->close();
    $conn->close();
}
?>
