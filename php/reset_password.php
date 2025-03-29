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
  <title>Reset Password</title>
  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
  <form class="modern-form" method="POST" action="process_reset_password.php">
    <span class="form-title" style="font-size: 20px;">Enter a New Password Here</span> <br> <br>
    <div class="input-group">
      <div class="input-wrapper">
        <input type="password" name="new_password" placeholder="New Password" required class="form-input" style="padding: 0 6px;">
      </div>
    </div>
    <button type="submit" class="submit-button">Reset Password</button>
    <button type="button" class="submit-button" onclick="window.location.href='login.php';">Cancel</button>
  </form>
</body>
</html>