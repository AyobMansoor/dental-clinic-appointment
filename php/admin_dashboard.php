<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database configuration
$host = 'localhost';
$db   = 'dental_clinic';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Handle AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $section = $_GET['section'] ?? '';

    switch ($section) {
        // Dashboard Stats
        case 'dashboard_stats':
            $stats = [
                'doctors' => $pdo->query("SELECT COUNT(*) FROM users WHERE user_role = 'doctor'")->fetchColumn(),
                'patients' => $pdo->query("SELECT COUNT(*) FROM patients ")->fetchColumn(),
                'today_appointments' => $pdo->query("
                    SELECT COUNT(*) FROM appointments 
                    WHERE DATE(appointment_date) = CURDATE()
                ")->fetchColumn(),
                'confirmed' => $pdo->query("
                    SELECT COUNT(*) FROM appointments 
                    WHERE status = 'confirmed'
                ")->fetchColumn(),
                'canceled' => $pdo->query("
                    SELECT COUNT(*) FROM appointments 
                    WHERE status = 'cancelled'
                ")->fetchColumn(),
            ];
            echo json_encode($stats);
            exit;

        // Weekly Chart Data
        case 'weekly_chart':
            $stmt = $pdo->query("
                SELECT 
                    DAYNAME(appointment_date) AS day, 
                    COUNT(*) AS count 
                FROM appointments
                WHERE 
                    YEARWEEK(appointment_date, 3) = YEARWEEK(CURDATE(), 3)
                    AND (DAYOFWEEK(appointment_date) = 7 OR DAYOFWEEK(appointment_date) BETWEEN 1 AND 5)
                GROUP BY day
                ORDER BY 
                    FIELD(day, 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday')
            ");
            $chart_data = $stmt->fetchAll();
            echo json_encode($chart_data);
            exit;

        // List of Doctors
        case 'doctors_list':
            $stmt = $pdo->query("
                SELECT u.id, u.firstname, u.lastname, u.email, d.phone, s.name AS specialization, d.account_status 
                FROM users u
                JOIN doctors d ON u.id = d.user_id
                LEFT JOIN specializations s ON d.specialization_id = s.id
            ");
            $doctors = $stmt->fetchAll();
            include './partials/doctors.php';
            exit;

        // Save Doctor
        case 'save_doctor':
            try {
                // Generate a random password
                $random_password = bin2hex(random_bytes(4)); // Generates an 8-character random password
                $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
        
                // Insert into users table
                $stmt = $pdo->prepare("
                    INSERT INTO users (firstname, lastname, email, password, user_role) 
                    VALUES (?, ?, ?, ?, 'doctor')
                ");
                $stmt->execute([
                    $_POST['firstname'],
                    $_POST['lastname'],
                    $_POST['email'],
                    $hashed_password
                ]);
                $user_id = $pdo->lastInsertId();
        
                // Insert into doctors table
                $stmt = $pdo->prepare("
                    INSERT INTO doctors (user_id, specialization_id, phone) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $_POST['specialization_id'],
                    $_POST['phone']
                ]);
        
                // Send email to the doctor using PHPMailer
                require '../vendor/autoload.php'; // Include PHPMailer
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'aauoyb08@gmail.com'; // Replace with your email
                    $mail->Password = 'asgt kybo wdqv oheg'; // Use an App Password for Gmail
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;
        
                    $to = $_POST['email'];
                    $subject = "Your New Account at Dental Clinic";
                    $reset_password_link = "http://localhost/dental_clinic/php/forgot_password.php"; // Link to reset password page
        
                    $message = "
                        Hello {$_POST['firstname']} {$_POST['lastname']},
                        
                         You have been registered as a doctor at our Dental Clinic.
                        Your temporary password is: $random_password
                        
                        Please visit the following link to reset your password:
                        $reset_password_link
                        
                        Thank you!
                    ";
        
                    $mail->setFrom('aauoyb08@gmail.com', 'Dental Clinic');
                    $mail->addAddress($to);
                    $mail->Subject = $subject;
                    $mail->Body = $message;
        
                    $mail->send();
                    echo json_encode(['status' => 'success', 'message' => 'Doctor added successfully and email sent.']);
                } catch (Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Doctor added successfully but email failed to send. Error: ' . $mail->ErrorInfo]);
                }
            } catch (\Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;



        // Delete Doctor
        case 'delete_doctor':
            try {
                // Start a transaction to ensure atomicity
                $pdo->beginTransaction();
        
                // Delete from the doctors table
                $stmt = $pdo->prepare("DELETE FROM doctors WHERE user_id = ?");
                $stmt->execute([$_POST['id']]);
        
                // Delete from the users table
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$_POST['id']]);
        
                // Commit the transaction
                $pdo->commit();
        
                echo json_encode(['status' => 'success', 'message' => 'Doctor deleted successfully.']);
            } catch (\PDOException $e) {
                // Rollback the transaction in case of an error
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Error deleting doctor: ' . $e->getMessage()]);
            }
            exit;



            // Updating doctors Status
            case 'update_doctor_status':
                try {
                    $stmt = $pdo->prepare("UPDATE doctors SET account_status = ? WHERE user_id = ?");
                    $stmt->execute([$_POST['status'], $_POST['id']]);
            
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['status' => 'success', 'message' => 'Doctor status updated successfully.']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'No doctor found with the given ID.']);
                    }
                } catch (\PDOException $e) {
                    echo json_encode(['status' => 'error', 'message' => 'Error updating doctor status: ' . $e->getMessage()]);
                }
                exit;

        // List of Patients
        case 'patients_list':
            $search = $_GET['search'] ?? '';
            $stmt = $pdo->prepare("
                SELECT u.*, p.age, p.gender, p.phone 
                FROM users u
                JOIN patients p ON u.id = p.user_id
                WHERE u.user_role = 'user'
                AND (u.firstname LIKE ? OR u.lastname LIKE ? OR p.phone LIKE ?)
            ");
            $search_term = "%$search%";
            $stmt->execute([$search_term, $search_term, $search_term]);
            $patients = $stmt->fetchAll();
            include './partials/patients.php';
            exit;

        // List of Appointments
        case 'appointments_list':
            $search = $_GET['search'] ?? '';
            $stmt = $pdo->prepare("
                SELECT a.*, 
                       CONCAT(up.firstname, ' ', up.lastname) AS patient_name,
                       CONCAT(ud.firstname, ' ', ud.lastname) AS doctor_name
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN users up ON p.user_id = up.id
                JOIN doctors d ON a.doctor_id = d.user_id
                JOIN users ud ON d.user_id = ud.id
                WHERE up.firstname LIKE ? 
                   OR up.lastname LIKE ?
                   OR ud.firstname LIKE ?
                   OR ud.lastname LIKE ?
                   OR a.appointment_date = ?
            ");
            $date = ($search && strtotime($search)) ? date('Y-m-d', strtotime($search)) : '';
            $search_term = "%$search%";
            $stmt->execute([$search_term, $search_term, $search_term, $search_term, $date]);
            $appointments = $stmt->fetchAll();
            include './partials/appointments.php';
            exit;

        // Update Appointment Status
        case 'update_appointment':
            try {
                // Update appointment status
                $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                $stmt->execute([$_POST['status'], $_POST['id']]);
                
                if ($stmt->rowCount() > 0 && $_POST['status'] === 'cancelled') {
                    // Get appointment details and patient email
                    $appointment_id = $_POST['id'];
                    $stmt = $pdo->prepare("
                        SELECT 
                            a.appointment_date,
                            a.appointment_time,
                            a.visit_type,
                            CONCAT(ud.firstname, ' ', ud.lastname) AS doctor_name,
                            up.email AS patient_email
                        FROM appointments a
                        JOIN patients p ON a.patient_id = p.id
                        JOIN users up ON p.user_id = up.id
                        JOIN doctors d ON a.doctor_id = d.user_id
                        JOIN users ud ON d.user_id = ud.id
                        WHERE a.id = ?
                    ");
                    $stmt->execute([$appointment_id]);
                    $appointment = $stmt->fetch();
                    
                    if ($appointment) {
                        // Send cancellation email
                        require '../vendor/autoload.php';
                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'aauoyb08@gmail.com';
                            $mail->Password = 'asgt kybo wdqv oheg';
                            $mail->SMTPSecure = 'tls';
                            $mail->Port = 587;
        
                            $mail->setFrom('aauoyb08@gmail.com', 'Dental Clinic');
                            $mail->addAddress($appointment['patient_email']);
                            $mail->Subject = 'Appointment Cancellation';
        
                            $formatted_date = date('M j, Y', strtotime($appointment['appointment_date']));
                            $formatted_time = date('g:i A', strtotime($appointment['appointment_time']));
        
                            $body = "
                                <h3>Appointment Cancellation Notice</h3>
                                <p>Your appointment has been canceled by the clinic:</p>
                                <ul>
                                    <li><strong>Appointment ID:</strong> $appointment_id</li>
                                    <li><strong>Date:</strong> $formatted_date</li>
                                    <li><strong>Time:</strong> $formatted_time</li>
                                    <li><strong>Doctor:</strong> {$appointment['doctor_name']}</li>
                                    <li><strong>Type:</strong> {$appointment['visit_type']}</li>
                                </ul>
                                <p>Please contact us at <strong>774480038</strong> for rescheduling.</p>
                                <p>We apologize for any inconvenience.</p>
                            ";
        
                            $mail->msgHTML($body);
                            $mail->AltBody = strip_tags($body);
                            $mail->send();
                        } catch (Exception $e) {
                            error_log("Email failed: " . $mail->ErrorInfo);
                        }
                    }
                }
                echo json_encode(['status' => 'success', 'message' => 'Appointment updated successfully.']);
            } catch (\PDOException $e) {
                echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;

        // List of Schedules
        case 'schedules_list':
            $stmt = $pdo->query("
                SELECT da.*, CONCAT(u.firstname, ' ', u.lastname) AS doctor_name 
                FROM doctor_availability da
                JOIN doctors d ON da.doctor_id = d.user_id
                JOIN users u ON d.user_id = u.id
            ");
            $schedules = $stmt->fetchAll();
            include './partials/schedules.php';
            exit;

        // Save Schedule
        case 'save_schedule':
            $stmt = $pdo->prepare("
                INSERT INTO doctor_availability (doctor_id, available_day, created_by_admin)
                VALUES (?, ?, 1)
            ");
            $stmt->execute([
                $_POST['doctor_id'],
                $_POST['available_day']
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Schedule added successfully.']);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            background: #2c3e50;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            
        }
        .nav-link {
            color:rgb(0, 0, 0) !important;
            background:#2c3e50;

        }
        .nav-link.active {
            background:rgb(74, 126, 177);
            
            
        }
    
        
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3 class="text-white p-3">Clinic Admin</h3>
        <div class="list-group">
            <a href="#" class="list-group-item list-group-item-action nav-link active" data-section="dashboard">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a href="#" class="list-group-item list-group-item-action nav-link" data-section="doctors">
                <i class="fas fa-user-md me-2"></i> Doctors
            </a>
            <a href="#" class="list-group-item list-group-item-action nav-link" data-section="patients">
                <i class="fas fa-users me-2"></i> Patients
            </a>
            <a href="#" class="list-group-item list-group-item-action nav-link" data-section="appointments">
                <i class="fas fa-calendar-check me-2"></i> Appointments
            </a>
            <a href="#" class="list-group-item list-group-item-action nav-link" data-section="schedules">
                <i class="fas fa-clock me-2"></i> Schedules
            </a>
            <!-- In your admin_dashboard.php sidebar -->
            <form action="logout.php" method="POST" style="display: inline-block;">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" class="list-group-item list-group-item-action btn-logout" 
                        style="background: #2c3e50; color: crimson; border: none;">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </button>
            </form>
        </div>
    </div>
    <div class="content" id="mainContent">
        <!-- Dashboard Section -->
        <div id="dashboard" class="section">
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5>Doctors</h5>
                            <h2 id="doctorCount">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5>Patients</h5>
                            <h2 id="patientCount">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5>Today's Appointments</h5>
                            <h2 id="todayAppointments">0</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h5>Confirmed/Canceled</h5>
                            <h2 id="appointmentStatus">0 / 0</h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">Weekly Appointments</div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- Doctors Section -->
        <div id="doctors" class="section d-none">
            <div class="card">
                <div class="card-header">
                    Doctors List
                    <button class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                        Add Doctor
                    </button>
                </div>
                <div class="card-body" id="doctorsList"></div>
            </div>
        </div>
        <!-- Patients Section -->
        <div id="patients" class="section d-none">
            <div class="card">
                <div class="card-header">
                    Patients List
                    <div class="float-end">
                        <input type="text" id="patientSearch" class="form-control" placeholder="Search patients...">
                    </div>
                </div>
                <div class="card-body" id="patientsList"></div>
            </div>
        </div>
        <!-- Appointments Section -->
        <div id="appointments" class="section d-none">
            <div class="card">
                <div class="card-header">
                    Appointments
                    <div class="float-end">
                        <input type="text" id="appointmentSearch" class="form-control" placeholder="Search...">
                    </div>
                </div>
                <div class="card-body" id="appointmentsList"></div>
            </div>
        </div>
        <!-- Schedules Section -->
        <div id="schedules" class="section d-none">
            <div class="card">
                <div class="card-header">
                    Doctor Schedules
                    <button class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                        Add Schedule
                    </button>
                </div>
                <div class="card-body" id="schedulesList"></div>
            </div>
        </div>
    </div>
    <!-- Modals -->
    <div class="modal fade" id="addDoctorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Doctor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addDoctorForm" class="ajax-form">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>First Name</label>
                            <input type="text" name="firstname" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Last Name</label>
                            <input type="text" name="lastname" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Specialization</label>
                            <select name="specialization_id" class="form-control" required>
                                <?php 
                                $stmt = $pdo->query("SELECT * FROM specializations");
                                foreach ($stmt->fetchAll() as $specialization) {
                                    echo "<option value='{$specialization['id']}'>{$specialization['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addScheduleForm" class="ajax-form">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Doctor</label>
                            <select name="doctor_id" class="form-control" required>
                                <?php 
                                $stmt = $pdo->query("
                                    SELECT u.id, u.firstname, u.lastname 
                                    FROM users u
                                    JOIN doctors d ON u.id = d.user_id
                                ");
                                foreach ($stmt->fetchAll() as $doctor) {
                                    echo "<option value='{$doctor['id']}'>{$doctor['firstname']} {$doctor['lastname']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Available Day</label>
                            <select name="available_day" class="form-control" required>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            let weeklyChart;
            function loadSection(section) {
                $('.section').addClass('d-none');
                $(`#${section}`).removeClass('d-none');
                switch(section) {
                    case 'dashboard':
                        loadDashboard();
                        break;
                    case 'doctors':
                        loadDoctors();
                        break;
                    case 'patients':
                        loadPatients();
                        break;
                    case 'appointments':
                        loadAppointments();
                        break;
                    case 'schedules':
                        loadSchedules();
                        break;
                }
            }
            $('.nav-link').click(function(e) {
                e.preventDefault();
                $('.nav-link').removeClass('active');
                $(this).addClass('active');
                loadSection($(this).data('section'));
            });
            // Initial load
            loadSection('dashboard');
            // Dashboard
            function loadDashboard() {
                $.getJSON('admin_dashboard.php?section=dashboard_stats', function(data) {
                    $('#doctorCount').text(data.doctors);
                    $('#patientCount').text(data.patients);
                    $('#todayAppointments').text(data.today_appointments);
                    $('#appointmentStatus').text(`${data.confirmed} / ${data.canceled}`);
                });
                if (!weeklyChart) {
                    $.getJSON('admin_dashboard.php?section=weekly_chart', function(data) {
                        const days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
                        const counts = Array.from({length: 6}, () => 0);
                        // Fill counts based on data
                        data.forEach(item => {
                            const index = days.indexOf(item.day);
                            if (index !== -1) {
                                counts[index] = item.count;
                            }
                        });
                        const ctx = document.getElementById('weeklyChart').getContext('2d');
                        weeklyChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: days,
                                datasets: [{
                                    label: 'Weekly Report',
                                    data: counts,
                                    backgroundColor: '#ff69b4', // Pink color
                                    borderColor: '#ff69b4',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 50,
                                            font: {
                                                size: 14
                                            }
                                        }
                                    },
                                    x: {
                                        grid: {
                                            display: false
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        enabled: true
                                    },
                                    datalabels: {
                                        align: 'end',
                                        anchor: 'end',
                                        formatter: function(value, context) {
                                            return value > 0 ? value : '';
                                        }
                                    }
                                }
                            },
                            plugins: [ChartDataLabels]
                        });
                    });
                }
            }
            // Doctors
            function loadDoctors() {
                $('#doctorsList').load('admin_dashboard.php?section=doctors_list');
            }
            $('#addDoctorForm').submit(function(e) {
                e.preventDefault();
                $.post('admin_dashboard.php?section=save_doctor', $(this).serialize(), function(response) {
                    const result = JSON.parse(response);
                    alert(result.message);
                    $('#addDoctorModal').modal('hide');
                    loadDoctors();
                });
            });

            $(document).on('click', '.delete-doctor', function() {
                if (confirm('Are you sure?')) {
                    $.post('admin_dashboard.php?section=delete_doctor', {id: $(this).data('id')}, function(response) {
                        const result = JSON.parse(response);
                        alert(result.message);
                        loadDoctors();
                    });
                }
            });

            $(document).on('click', '.toggle-status', function(e) {
                    e.preventDefault();
                    const id = $(this).data('id');
                    const currentStatus = $(this).data('status');
                    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

                    // Confirm action with user
                    const confirmationMessage = `Are you sure you want to change this doctor's status to "${newStatus}"?`;
                    if (!confirm(confirmationMessage)) {
                        return;
                    }

                    $.post('admin_dashboard.php?section=update_doctor_status', {id: id, status: newStatus}, function(response) {
                        const result = JSON.parse(response);
                        alert(result.message);
                        loadDoctors(); // Reload the doctors list
                    });
                });

            // Patients
            function loadPatients() {
                const search = $('#patientSearch').val();
                $('#patientsList').load('admin_dashboard.php?section=patients_list', {search: search});
            }
            $('#patientSearch').on('input', function() {
                loadPatients();
            });
            // Appointments
            function loadAppointments() {
                const search = $('#appointmentSearch').val();
                $('#appointmentsList').load('admin_dashboard.php?section=appointments_list', {search: search});
            }
            $('#appointmentSearch').on('input', function() {
                loadAppointments();
            });
            $(document).on('click', '.update-status', function() {
                const id = $(this).data('id');
                const status = $(this).data('status');

                // Confirm action with user
                let confirmationMessage = '';
                if (status === 'cancelled') {
                    confirmationMessage = 'Are you sure you want to cancel this appointment?';
                } else if (status === 'confirmed') {
                    confirmationMessage = 'Are you sure you want to confirm this appointment?';
                }

                if (!confirm(confirmationMessage)) {
                    return;
                }

                $.post('admin_dashboard.php?section=update_appointment', {id: id, status: status}, function(response) {
                    const result = JSON.parse(response);
                    alert(result.message);
                    loadAppointments(); // Reload the appointments list
                });
            });

            


            // Schedules
            function loadSchedules() {
                $('#schedulesList').load('admin_dashboard.php?section=schedules_list');
            }
            $('#addScheduleForm').submit(function(e) {
                e.preventDefault();
                $.post('admin_dashboard.php?section=save_schedule', $(this).serialize(), function(response) {
                    const result = JSON.parse(response);
                    alert(result.message);
                    $('#addScheduleModal').modal('hide');
                    loadSchedules();
                });
            });


          

            // Logout Button
            $('.btn-logout').click(function() {
                if (confirm('Are you sure you want to log out?')) {
                    window.location.href = 'logout.php'; // Redirect to logout page
                }
            });



        });
      
    </script>
</body>
</html>