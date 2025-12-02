<?php
date_default_timezone_set('Asia/Manila');
// Get these values from your InfinityFree MySQL Databases page
$servername = "localhost"; // The hostname from InfinityFree
$username = "root"; // Your database username
$password = ""; // Your database password
$dbname = "muffeia_online"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Set MySQL session timezone to match PHP
$timezone = '+08:00'; // Manila timezone offset
$conn->query("SET time_zone = '$timezone'");

// Initialize connection with proper charset and timezone
$conn->set_charset("utf8mb4");
?>