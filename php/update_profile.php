<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Get form data
$firstname = $_POST['firstname'] ?? '';
$lastname = $_POST['lastname'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';

// Validate input
if (empty($firstname) || empty($lastname) || empty($email)) {
    die("Required fields are missing");
}

// Update query
$stmt = $conn->prepare("UPDATE users SET 
    firstname = ?,
    lastname = ?,
    email = ?,
    phone = ?
    WHERE id = ?");
$stmt->bind_param('ssssi', $firstname, $lastname, $email, $phone, $doctor_id);
$stmt->execute();

header("Location: doctor_dashboard.php");
exit();