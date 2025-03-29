<?php
$servername = "localhost";
$username = "root"; // Change if needed
$password = ""; // Change if needed
$database = "dental_clinic"; // Change to your actual database name

$conn = new mysqli($servername, $username, $password, $database);
// Add to your db.php
$conn->query("SET time_zone = '+03:00'"); // Adjust to your time zone (e.g., Jordan)
date_default_timezone_set('Asia/Amman'); // PHP time zone

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);

}
?>



