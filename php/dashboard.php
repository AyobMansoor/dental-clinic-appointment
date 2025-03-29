<?php
// Start the session
session_start();

// At the start of your secured pages (e.g., dashboard.php)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

// Fetch user details from the session
$user_name = isset($_SESSION['firstname']) ? htmlspecialchars($_SESSION['firstname']) : 'User';
$user_role = $_SESSION['user_role'] ?? 'user';

// Silent redirect for admin (NEW)
if ($user_role === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Redirect doctors with message
if ($user_role === 'doctor') {
    header("Location: doctor_dashboard.php");
    exit();
}

// Continue with normal user flow
require_once 'db.php';
$user_id = $_SESSION['user_id'];

$upcoming_appointment_query = "
    SELECT 
        appointments.*, 
        users.firstname AS doctor_firstname, 
        users.lastname AS doctor_lastname
    FROM 
        appointments
    JOIN 
        doctors ON appointments.doctor_id = doctors.id
    JOIN 
        users ON doctors.user_id = users.id
    JOIN 
        patients ON appointments.patient_id = patients.id
    WHERE 
        patients.user_id = ? 
        AND appointments.appointment_date >= CURDATE()
    ORDER BY 
        appointments.appointment_date ASC, 
        appointments.appointment_time ASC
    LIMIT 1
";

$stmt = $conn->prepare($upcoming_appointment_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_result = $stmt->get_result();
$upcoming_appointment = $upcoming_result->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            background-image: url('../assets/images/back.jpg');
            background-size: cover;
            background-position: flex;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: rgba(68, 70, 71, 0.9);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        .header .welcome-message {
            font-size: 20px;
            font-weight: bold;
            color: #fff;
        }
        .header .btn-container {
            display: flex;
            gap: 10px;
        }
        .header .btn-custom {
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .header .btn-custom:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        .header .btn-book {
            background-color: rgb(11, 14, 12);
            color: #fff;
        }
        .dashboard-container {
            max-width: 600px;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin: 100px auto 50px;
        }
        .welcome-message {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            padding: 15px;
            background-color: rgba(57, 57, 58, 0.1);
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="welcome-message">
            Welcome <?php echo $user_name; ?> 
        </div>
        <div class="btn-container">
            <a href="#" id="book-appointment" class="btn btn-custom btn-book">
                Book Appointment <i class="fas fa-calendar-check"></i>
            </a>
          
            <form action="logout.php" method="POST" style="display: inline-block;">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <button type="submit" class="btn btn-danger btn-custom" 
            style="padding: 10px; border-radius: 50%; width: 40px; height: 40px;
                   display: flex; align-items: center; justify-content: center;"
            onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-times"></i>
                </button>
            </form>

          
        </div>
    </div>

    <div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="appointmentModalLabel">Upcoming Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card">
                        <div class="card-body">
                            <p class="card-text">
                                <strong>Patient Name:</strong> <?php echo $user_name; ?><br>
                                <strong>Doctor:</strong> <?= $upcoming_appointment['doctor_firstname'] . " " . $upcoming_appointment['doctor_lastname']; ?><br>
                                <strong>Visit Type:</strong> <?= $upcoming_appointment['visit_type']; ?><br>
                                <strong>Date:</strong> <?= $upcoming_appointment['appointment_date']; ?><br>
                                <strong>Time:</strong> <?= $upcoming_appointment['appointment_time']; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('book-appointment').addEventListener('click', function(event) {
            event.preventDefault();
            <?php if ($upcoming_appointment): ?>
                Swal.fire({
                    title: 'You have an existing appointment!',
                    text: 'You already have an upcoming appointment. Do you want to view it or book a new one?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'View Appointment',
                    cancelButtonText: 'Book New Appointment',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                }).then((result) => {
                    if (result.isConfirmed) {
                        const appointmentModal = new bootstrap.Modal(document.getElementById('appointmentModal'));
                        appointmentModal.show();
                    } else {
                        window.location.href = 'booking.php';
                    }
                });
            <?php else: ?>
                window.location.href = 'booking.php';
            <?php endif; ?>
        });
    </script>
</body>
</html>