<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST['new_password']);
    $email = $_SESSION['email'];

    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    // Update the password in the database
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $hashed_password, $email);
    $stmt->execute();

    // Clear the session
    session_unset();
    session_destroy();

    echo "<script>alert('Password reset successfully!'); window.location.href = '../login.html';</script>";
    $stmt->close();
    $conn->close();
}
?>