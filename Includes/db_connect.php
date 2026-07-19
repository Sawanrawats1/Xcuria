<?php
$host = 'localhost';
$user = 'root';
$password = ''; // blank for XAMPP by default
$database = 'xcuria';
$port = 3309; // or 3307 if you changed the port

$conn = new mysqli($host, $user, $password, $database, $port);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
?>
