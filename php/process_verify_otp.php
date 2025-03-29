<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp = trim($_POST['otp']);
    $email = $_SESSION['email'];

    // Check if OTP is valid and not expired
    $stmt = $conn->prepare("SELECT otp, otp_expiry FROM users WHERE email = ?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($db_otp, $otp_expiry);
    $stmt->fetch();
    $stmt->close();

    if ($db_otp === $otp && strtotime($otp_expiry) > time()) {
        // OTP is valid, redirect to reset password page
        header("Location: reset_password.php");
        exit();
    } else {
        echo "<script>alert('Invalid or expired OTP.'); window.location.href = 'verify_otp.php';</script>";
    }
}
?>