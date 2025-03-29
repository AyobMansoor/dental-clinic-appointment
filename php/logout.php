<?php
session_start();

// Validate CSRF Token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid CSRF token.");
}

// Unset all session variables
$_SESSION = [];
// Destroy the session
session_destroy();
// Redirect to the login page
header("Location: ../login.html");
exit();
?>