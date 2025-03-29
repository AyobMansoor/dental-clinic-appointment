
<?php
include 'db.php'; // Ensure this file connects to your MySQL database

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    $errors = [];

    // Validate first and last name
    if (empty($firstname) || empty($lastname)) {
        $errors[] = "Firstname and Lastname are required.";
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Email is already registered.";
    }
    $stmt->close();

    // Validate password length
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    // Confirm passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // If no errors, register user
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT); // Encrypt password
        $user_role = 'user'; // Default role

        $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, email, password, user_role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $firstname, $lastname, $email, $hashed_password, $user_role);

        if ($stmt->execute()) {

             echo "<script>
            alert('Signup successful!');
            window.location.href = '../login.html'; 
          </script>";

        exit();

        } else {
            echo "<script>alert('error !');</script>" . $stmt->error;
        }
        $stmt->close();
    } else {
        // Show validation errors
        foreach ($errors as $error) {
            echo " <script>alert(' $error ');</script> "  ;
        }
    }

    // ✅ Close the database connection after all processing
    $conn->close();
}
?>




