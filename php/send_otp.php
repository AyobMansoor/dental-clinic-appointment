<?php
session_start();
include 'db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'];

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if ($stmt === false) {
        die(json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]));
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        // Generate a 6-digit OTP
        $otp = rand(100000, 999999);

        // Set OTP expiry time (e.g., 5 minutes from now)
        $otp_expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

        // Store OTP and expiry time in the database
        $stmt = $conn->prepare("UPDATE users SET otp=?, otp_expiry=? WHERE email=?");
        if ($stmt === false) {
            die(json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]));
        }
        $stmt->bind_param("sss", $otp, $otp_expiry, $email);
        $stmt->execute();

        // Store the user's email in the session
        $_SESSION['email'] = $email;

        // Send OTP via PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'aauoyb08@gmail.com'; // Replace with your email
            $mail->Password = 'asgt kybo wdqv oheg'; // Use an App Password for Gmail
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('aauoyb08@gmail.com', 'Dental Clinic');
            $mail->addAddress($email);
            $mail->Subject = "Password Reset OTP";
            $mail->Body = "Your one-time password (OTP) is: $otp  This OTP is valid for 5 minutes.";

            $mail->send();
            echo json_encode(['status' => 'success', 'message' => 'OTP sent! Check your email.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Email could not be sent: ' . $mail->ErrorInfo]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No account found with this email.']);
    }
    $stmt->close();
    $conn->close();
}
?>