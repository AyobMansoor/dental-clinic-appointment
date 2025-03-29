<?php
session_start();
require 'db.php';

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../login.html");
    exit();
}


try {
    // Fetch Doctor Data
    $stmt = $conn->prepare("SELECT 
        u.*, d.id AS doctor_id,
        (SELECT COUNT(*) FROM notifications WHERE user_id = u.id AND is_read = 0) AS unread_notifs
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        WHERE u.id = ?");
    
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('i', $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $doctor = $stmt->get_result()->fetch_assoc();
    
    if (!$doctor) {
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch(Exception $e) {
    die("Error loading doctor data: " . $e->getMessage());
}

// Date Handling with DateTime
$today = new DateTime();
$period = $_GET['period'] ?? 'today';

try {
    // Initialize dates
    $start_date = clone $today;
    $end_date = clone $today;

    switch ($period) {
        case 'week':
            $start_date->modify('last Saturday');  // Start at most recent Saturday
            $end_date = clone $start_date;
            $end_date->modify('+5 days');          // End 5 days later (Thursday)
            break;
        case 'month':
            $start_date->modify('first day of this month');
            $end_date->modify('last day of this month');
            break;
        default: // today
            break;
    }

    // Prepare date strings for SQL query
    $doctor_id = (int)$doctor['doctor_id'];
    $start_date_str = $start_date->format('Y-m-d');
    $end_date_str = $end_date->format('Y-m-d');

    // Fetch Appointments (Corrected SQL)
    $appointments_stmt = $conn->prepare("
        SELECT a.*, 
            u.firstname AS patient_firstname,
            u.lastname AS patient_lastname,
            p.age,
            p.gender,
            p.phone,
            a.visit_type
        FROM appointments a
        INNER JOIN patients p ON a.patient_id = p.id
        INNER JOIN users u ON p.user_id = u.id
        WHERE a.doctor_id = ?
        AND a.appointment_date BETWEEN ? AND ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");

    if ($appointments_stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $appointments_stmt->bind_param('iss', 
        $doctor_id,
        $start_date_str,
        $end_date_str
    );
    
    if (!$appointments_stmt->execute()) {
        throw new Exception("Execute failed: " . $appointments_stmt->error);
    }
    
    $appointments = $appointments_stmt->get_result();

} catch(Exception $e) {
    die("Error loading appointments: " . $e->getMessage());
}

// Fetch Availability Schedule
try {
    $availability_stmt = $conn->prepare("
        SELECT 
            da.available_day,
            da.created_by_admin,
            COUNT(a.id) AS booked_slots,
            (SELECT COUNT(*) FROM doctor_availability 
             WHERE doctor_id = ? AND available_day = da.available_day) AS total_slots
        FROM doctor_availability da
        LEFT JOIN appointments a ON da.doctor_id = a.doctor_id 
            AND da.available_day = DAYNAME(a.appointment_date)
        WHERE da.doctor_id = ?
        GROUP BY da.available_day
    ");
    
    if ($availability_stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $availability_stmt->bind_param('ii', $doctor['doctor_id'], $doctor['doctor_id']);
    
    if (!$availability_stmt->execute()) {
        throw new Exception("Execute failed: " . $availability_stmt->error);
    }
    
    $availability = $availability_stmt->get_result();
} catch(Exception $e) {
    die("Error loading schedule: " . $e->getMessage());
}

// Fetch Patients
try {
    $patients_stmt = $conn->prepare("
        SELECT DISTINCT 
            u.firstname, 
            u.lastname, 
            p.age, 
            p.gender, 
            p.phone,
            (SELECT COUNT(*) FROM appointments 
             WHERE patient_id = p.id) AS total_visits
        FROM patients p
        JOIN users u ON p.user_id = u.id
        JOIN appointments a ON a.patient_id = p.id
        WHERE a.doctor_id = ?
    ");
    
    if ($patients_stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $patients_stmt->bind_param('i', $doctor['doctor_id']);
    
    if (!$patients_stmt->execute()) {
        throw new Exception("Execute failed: " . $patients_stmt->error);
    }
    
    $patients = $patients_stmt->get_result();
} catch(Exception $e) {
    die("Error loading patients: " . $e->getMessage());
}

// Fetch Notifications
try {
    $notification_stmt = $conn->prepare("
        SELECT *, 
            DATE_FORMAT(created_at, '%b %d, %Y') AS notification_date,
            TIME_FORMAT(created_at, '%h:%i %p') AS notification_time 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    
    if ($notification_stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $notification_stmt->bind_param('i', $_SESSION['user_id']);
    
    if (!$notification_stmt->execute()) {
        throw new Exception("Execute failed: " . $notification_stmt->error);
    }
    
    $notifications = $notification_stmt->get_result();
} catch(Exception $e) {
    die("Error loading notifications: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Clinic - Doctor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --dental-primary: #2c3e50;
            --dental-secondary: #3498db;
            --dental-success: #27ae60;
            --dental-danger: #e74c3c;
        }

        .sidebar {
            background: var(--dental-primary);
            min-height: 100vh;
            width: 250px;
            position: fixed;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }

        .nav-link {
            transition: all 0.3s;
            position: relative;
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar text-white">
        <div class="p-4">
            <div class="d-flex align-items-center mb-4">
                <?php if(!empty($doctor['profile_pic'])): ?>
                    <img src="<?= htmlspecialchars($doctor['profile_pic']) ?>" 
                         class="rounded-circle me-3" 
                         style="width: 60px; height: 60px; object-fit: cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-secondary me-3 d-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-user-md fa-lg"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h5 class="mb-0">Dr. <?= htmlspecialchars($doctor['lastname']) ?></h5>
                    <small class="text-muted">Dental Surgeon</small>
                </div>
            </div>
            
            <nav class="nav flex-column">
                <a class="nav-link active" href="#appointments" data-tab="appointments">
                    <i class="fas fa-calendar-check me-2"></i>
                    Appointments
                    <span class="badge bg-danger ms-2"><?= $appointments->num_rows ?></span>
                </a>
                <a class="nav-link" href="#schedule" data-tab="schedule">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Availability
                </a>
                <a class="nav-link" href="#patients" data-tab="patients">
                    <i class="fas fa-users me-2"></i>
                    Patients
                </a>
                <a class="nav-link" href="#notifications" data-tab="notifications">
                    <i class="fas fa-bell me-2"></i>
                    Notifications
                    <span class="badge bg-danger ms-2"><?= $doctor['unread_notifs'] ?></span>
                </a>

                 <!-- Add Logout Button -->
                 <form id="logoutForm" action="logout.php" method="POST" style="display: none;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                         </form>
                    <a href="#" class="nav-link text-danger" onclick="document.getElementById('logoutForm').submit();">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        Logout
                    </a>

            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header bg-white shadow-sm mb-4 p-3 rounded">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1">Dental Appointment Manager</h3>
                    <div class="text-muted small">
                        <i class="fas fa-calendar-day me-1"></i>
                        <?= $today->format('l, F j, Y') ?>
                    </div>
                </div>
                <div class="btn-group">
                    <a href="?period=today" class="btn btn-sm btn-outline-primary <?= $period === 'today' ? 'active' : '' ?>">
                        Today
                    </a>
                    <a href="?period=week" class="btn btn-sm btn-outline-primary <?= $period === 'week' ? 'active' : '' ?>">
                        Week
                    </a>
                    <a href="?period=month" class="btn btn-sm btn-outline-primary <?= $period === 'month' ? 'active' : '' ?>">
                        Month
                    </a>
                </div>
            </div>
        </div>

        <!-- Appointments Section -->
        <section id="appointments" class="mb-5">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tooth me-2"></i>
                        Scheduled Procedures
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($appointments->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while ($apt = $appointments->fetch_assoc()): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <?= htmlspecialchars($apt['patient_firstname'] . ' ' . $apt['patient_lastname']) ?>
                                                <span class="badge bg-<?= 
                                                    ($apt['status'] == 'confirmed') ? 'success' : 
                                                    (($apt['status'] == 'cancelled') ? 'danger' : 'warning') ?>">
                                                    <?= ucfirst($apt['status']) ?>
                                                </span>
                                            </h6>
                                            <div class="text-muted small">
                                                <span class="me-3">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                                                </span>
                                                <span class="me-3">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?= date('M j, Y', strtotime($apt['appointment_date'])) ?>
                                                </span>
                                                <span class="badge bg-info">
                                                    <?= htmlspecialchars($apt['visit_type']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if($apt['status'] == 'pending'): ?>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-success" 
                                                onclick="updateAppointment(<?= $apt['id'] ?>, 'confirm')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="updateAppointment(<?= $apt['id'] ?>, 'cancel')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">No scheduled appointments found</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Schedule Section -->
        <section id="schedule" class="mb-5 d-none">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Weekly Availability
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Approval Status</th>
                                    <th>Booked Slots</th>
                                    <th>Availability</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($day = $availability->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($day['available_day']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $day['created_by_admin'] ? 'success' : 'warning' ?>">
                                            <?= $day['created_by_admin'] ? 'Approved' : 'Pending' ?>
                                        </span>
                                    </td>
                                    <td><?= $day['booked_slots'] ?> / <?= $day['total_slots'] ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?= ($day['booked_slots']/$day['total_slots'] > 0.75) ? 'danger' : 'success' ?>" 
                                                 role="progressbar" 
                                                 style="width: <?= ($day['booked_slots']/$day['total_slots'])*100 ?>%">
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Patients Section -->
        <section id="patients" class="mb-5 d-none">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>
                        Patient Directory
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Contact</th>
                                    <th>Visits</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($patient = $patients->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($patient['firstname'] . ' ' . $patient['lastname']) ?></td>
                                    <td><?= htmlspecialchars($patient['age']) ?></td>
                                    <td><?= htmlspecialchars($patient['gender']) ?></td>
                                    <td><?= htmlspecialchars($patient['phone']) ?></td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?= htmlspecialchars($patient['total_visits']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Notifications Section -->
        <section id="notifications" class="mb-5 d-none">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bell me-2"></i>
                        Notifications
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php while ($note = $notifications->fetch_assoc()): ?>
                        <div class="list-group-item <?= $note['is_read'] ? '' : 'fw-bold' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="mb-1"><?= htmlspecialchars($note['message']) ?></div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($note['notification_date']) ?> 
                                        at <?= htmlspecialchars($note['notification_time']) ?>
                                    </small>
                                </div>
                                <?php if(!$note['is_read']): ?>
                                <button class="btn btn-sm btn-link mark-read" 
                                        data-id="<?= $note['id'] ?>">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="actionToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">System Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab Handling
        document.querySelectorAll('[data-tab]').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const tabName = this.dataset.tab;
                window.location.hash = '#' + tabName;
                activateTab(tabName);
            });
        });

        function activateTab(tabName) {
            document.querySelectorAll('section').forEach(section => {
                section.classList.add('d-none');
            });
            
            document.querySelectorAll('.nav-link').forEach(tab => {
                tab.classList.remove('active');
            });

            document.querySelector(`#${tabName}`).classList.remove('d-none');
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.substring(1);
            activateTab(hash || 'appointments');
        });

        // Appointment Actions
        async function updateAppointment(id, action) {
            const toastEl = document.getElementById('actionToast');
            const toast = new bootstrap.Toast(toastEl);
            
            try {
                const response = await fetch('update_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
                    },
                    body: JSON.stringify({ id, action })
                });

                const result = await response.json();
                
                if (response.ok) {
                    toastEl.querySelector('.toast-body').textContent = result.message;
                    toastEl.classList.add('bg-success', 'text-white');
                    toast.show();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    throw new Error(result.error || 'Action failed');
                }
            } catch (error) {
                toastEl.querySelector('.toast-body').textContent = error.message;
                toastEl.classList.add('bg-danger', 'text-white');
                toast.show();
            }
        }

        // Notification Mark as Read
        document.querySelectorAll('.mark-read').forEach(btn => {
            btn.addEventListener('click', async function() {
                const notificationId = this.dataset.id;
                try {
                    const response = await fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
                        },
                        body: JSON.stringify({ id: notificationId })
                    });
                    
                    if (response.ok) {
                        this.closest('.list-group-item').classList.remove('fw-bold');
                        this.remove();
                    }
                } catch (error) {
                    console.error('Error marking notification read:', error);
                }
            });
        });
    </script>
</body>
</html>