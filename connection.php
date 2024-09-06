<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "video_conference1";

try {
    // Create a new PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // No need to echo the success message in production
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
