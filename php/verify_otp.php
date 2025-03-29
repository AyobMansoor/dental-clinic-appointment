<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify OTP</title>
  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
  <form class="modern-form" method="POST" action="process_verify_otp.php">
    <span class="form-title">Enter the OTP Code Here.</span> <br> <br>
    <div class="input-group">
      <div class="input-wrapper">
        <input type="text" name="otp" placeholder="Enter OTP" required class="form-input" style="padding: 0 6px;">
      </div>
    </div>
    <button type="submit" class="submit-button">Verify OTP</button>
    <button type="button" class="submit-button" onclick="window.location.href='forgot_password.php';">Cancel</button>
  </form>
</body>
</html>


