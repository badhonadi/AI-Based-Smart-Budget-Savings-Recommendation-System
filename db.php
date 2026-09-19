<?php
// Database configuration
$host = "localhost";
$user = "root";
$pass = "";
$db   = "spendee";

// Create connection (MySQLi OOP)
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset (important for security & Bangla support)
$conn->set_charset("utf8mb4");
?>
