<?php
session_start();
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Initialize error array
    $errors = [];

    

    // Sanitize and validate inputs
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);

    // Validate inputs
    if (empty($email) || empty($password)) {
        $errors[] = "All fields are required";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if (empty($errors)) {
        // Check user credentials
        $stmt = $conn->prepare("SELECT id, firstname, password, user_role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['user_role'];
                $_SESSION['firstname'] = $user['firstname'];
                $_SESSION['last_login'] = time();
                // After setting session variables (user_id, user_role, etc.)
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Add this line

                // Redirect based on user role
                switch ($user['user_role']) {
                    case 'user':
                        header("Location: dashboard.php");
                        break;
                    case 'doctor':
                        header("Location: doctor_dashboard.php");
                        break;
                    case 'admin':
                        header("Location: admin_dashboard.php");
                        break;
                    default:
                        header("Location: ../index.html");
                }
                exit();
            } else {
                $errors[] = "Invalid credentials";
            }
        } else {
            $errors[] = "Invalid credentials";
        }
        
        $stmt->close();
    }

    // Handle errors
    $_SESSION['login_errors'] = $errors;
    header("Location: ../index.html");
    exit();
}

// Close connection
$conn->close(); 