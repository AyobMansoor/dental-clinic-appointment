<?php
session_start();
require_once 'db.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.htm;");
    exit();
}
$user_id = $_SESSION['user_id'];

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointment_id = $_POST['cancel_appointment'];
    
    // Verify ownership and delete
    $stmt = $conn->prepare("DELETE FROM appointments 
                           WHERE id = ? AND patient_id = ?");
    $stmt->bind_param("ii", $appointment_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['cancel_success'] = "Appointment canceled successfully.";
    } else {
        $_SESSION['cancel_error'] = "Cancellation failed. Please try again.";
    }
    
    header("Location: booking.php");
    exit();
}

// Initialize variables
$success = false;
$error_message = "";
$available_slots = [];
$form_data = $_POST ?? [];
$appointment = [];
$appointment_id = null; // Added appointment ID variable

// Fetch user data
$patient = $conn->query("SELECT firstname, lastname FROM users WHERE id = $user_id")->fetch_assoc();
$patient_name = htmlspecialchars($patient['firstname'] . ' ' . $patient['lastname']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['cancel_appointment'])) {
    // Sanitize inputs
    $visit_type = filter_input(INPUT_POST, 'visit_type', FILTER_SANITIZE_STRING);
    $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $appointment_date = filter_input(INPUT_POST, 'appointment_date', FILTER_SANITIZE_STRING);
    $appointment_time = filter_input(INPUT_POST, 'appointment_time', FILTER_SANITIZE_STRING);
    
    // Store form data for repopulation
    $form_data = [
        'visit_type' => $visit_type,
        'doctor_id' => $doctor_id,
        'appointment_date' => $appointment_date,
        'appointment_time' => $appointment_time,
        'age' => $_POST['age'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'phone' => $_POST['phone'] ?? ''
    ];

    try {
        // Date validation
        $selectedDate = new DateTime($appointment_date);
        $today = new DateTime('today');
        $now = new DateTime();
        
        // Convert to 24-hour time format
        $appointment_time_24h = date("H:i", strtotime($appointment_time));
        $selectedDateTime = new DateTime("$appointment_date $appointment_time_24h");
        
        // Validate date is not in past
        if ($selectedDate < $today) {
            throw new Exception("Cannot select past dates. Please choose a future date.");
        }
        
        // Validate datetime is in future
        if ($selectedDateTime <= $now) {
            throw new Exception("Please select a future date and time.");
        }
        
        // Validate doctor exists
        $stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception("Invalid doctor selection.");
        }
        
        // Check availability (exclude past dates)
        $stmt = $conn->prepare("SELECT appointment_time FROM appointments 
                              WHERE doctor_id = ? 
                              AND appointment_date = ? 
                              AND appointment_date >= CURDATE()");
        $stmt->bind_param("is", $doctor_id, $appointment_date);
        $stmt->execute();
        $busy_slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Generate available slots
        $start = new DateTime("09:00");
        $end = new DateTime("21:00");
        $available_slots = [];
        while ($start < $end) {
            $slot_available = true;
            $slot_time = $start->format('H:i');
            foreach ($busy_slots as $busy) {
                $busy_time = new DateTime($busy['appointment_time']);
                if (abs($start->getTimestamp() - $busy_time->getTimestamp()) < 1800) {
                    $slot_available = false;
                    break;
                }
            }
            if ($slot_available) $available_slots[] = $slot_time;
            $start->modify('+30 minutes');
        }
        
        // Validate selected time
        if (!in_array($appointment_time_24h, $available_slots)) {
            throw new Exception("Selected time is unavailable. Please choose another slot.");
        }
        
        // Database transaction
        $conn->begin_transaction();
        
                        // Insert patient info if first time
                    $user_id = intval($user_id); // Sanitize input
                    $existing_patient = $conn->query("SELECT id FROM patients WHERE user_id = $user_id");
                    if ($existing_patient->num_rows === 0) {
                        $stmt = $conn->prepare("INSERT INTO patients (user_id, age, gender, phone) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("iiss", $user_id, $form_data['age'], $form_data['gender'], $form_data['phone']);
                        $stmt->execute();
                        $patient_id = $stmt->insert_id; // Get the new patient's ID
                    } else {
                        $patient_id = $existing_patient->fetch_assoc()['id']; // Get existing patient ID
                    }

                    // Insert appointment using the CORRECT patient_id
                    $stmt = $conn->prepare("INSERT INTO appointments 
                                        (patient_id, doctor_id, visit_type, appointment_date, appointment_time) 
                                        VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iisss", $patient_id, $doctor_id, $visit_type, $appointment_date, $appointment_time_24h);
                    $stmt->execute();
                    $appointment_id = $stmt->insert_id;
                        // Get doctor's user_id
                $stmt = $conn->prepare("SELECT user_id FROM doctors WHERE id = ?");
                $stmt->bind_param("i", $doctor_id);
                $stmt->execute();
                $doctor = $stmt->get_result()->fetch_assoc();
                $doctor_user_id = $doctor['user_id'];

                // Create notification message
                $message = "New appointment booked by {$patient_name} on {$form_data['appointment_date']} at {$form_data['appointment_time']}.";

                // Insert notification
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif_stmt->bind_param("is", $doctor_user_id, $message);
                $notif_stmt->execute();
                        
        // Fetch doctor details
        $appointment = $conn->query("SELECT users.firstname, users.lastname 
                                   FROM doctors JOIN users ON doctors.user_id = users.id 
                                   WHERE doctors.id = $doctor_id")->fetch_assoc();
        $conn->commit();
        $success = true;
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .slot-btn { transition: transform 0.2s; }
        .slot-btn:hover { transform: scale(1.05); }
        .booking-card { max-width: 600px; }
        .confirmation-card { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container py-4">
        <?php if (isset($_SESSION['cancel_success'])): ?>
            <?php unset($_SESSION['cancel_success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['cancel_error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['cancel_error'] ?></div>
            <?php unset($_SESSION['cancel_error']); ?>
        <?php endif; ?>

        <?php if ($success): ?>
        <!-- Confirmation Section -->
        <div class="card shadow-sm confirmation-card mx-auto booking-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="text-success text-center flex-grow-1">✅ Appointment Booked!</h3>
                    <a href="dashboard.php" class="btn btn-danger btn-custom" style="padding: 10px; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <h5 class="text-primary">Appointment Details</h5>
                        <hr>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Patient:</strong><br><?= $patient_name ?></p>
                        <p><strong>Doctor:</strong><br>
                            Dr. <?= htmlspecialchars($appointment['firstname'] . ' ' . $appointment['lastname']) ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Date:</strong><br>
                            <?= date('F j, Y', strtotime($form_data['appointment_date'])) ?>
                        </p>
                        <p><strong>Time:</strong><br>
                            <?= date('g:i A', strtotime($form_data['appointment_time'])) ?>
                        </p>
                    </div>
                    <div class="col-12">
                        <p><strong>Visit Type:</strong><br>
                            <?= htmlspecialchars($form_data['visit_type']) ?>
                        </p>
                    </div>
                </div>
                
                <form method="post" class="d-inline">
                    <input type="hidden" name="cancel_appointment" value="<?= $appointment_id ?>">
                    <button type="submit" class="btn btn-danger mb-3">Cancel Appointment</button>
                </form>

                <div class="d-grid gap-2">
                    <button onclick="window.print()" class="btn btn-outline-primary">
                        Print Details
                    </button>
                    <a href="booking.php" class="btn btn-primary">
                        Book Another Appointment
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
            <!-- Booking Form -->
            <div class="card shadow-sm booking-card mx-auto">
                <div class="card-body">
                    <h3 class="text-center mb-4">Book Appointment</h3>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <!-- Doctor Selection -->
                        <div class="mb-3">
                            <label class="form-label">Select Doctor</label>
                            <select name="doctor_id" class="form-select" required>
                                <?php foreach ($conn->query("SELECT doctors.id, users.firstname, users.lastname 
                                                          FROM doctors JOIN users ON doctors.user_id = users.id") as $doc): ?>
                                    <option value="<?= $doc['id'] ?>" 
                                        <?= ($form_data['doctor_id'] ?? '') == $doc['id'] ? 'selected' : '' ?>>
                                        Dr. <?= htmlspecialchars($doc['firstname']) ?> <?= htmlspecialchars($doc['lastname']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Date and Time -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Appointment Date</label>
                                <input type="date" name="appointment_date" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($form_data['appointment_date'] ?? '') ?>"
                                       min="<?= date('Y-m-d') ?>" 
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Appointment Time</label>
                                <input type="time" name="appointment_time" 
                                       class="form-control"
                                       value="<?= htmlspecialchars($form_data['appointment_time'] ?? '') ?>"
                                       min="09:00" 
                                       max="20:30" 
                                       step="1800"
                                       required>
                            </div>
                        </div>
                        <!-- Patient Info for First-Time -->
                        <?php if (!$conn->query("SELECT * FROM patients WHERE user_id = $user_id")->num_rows): ?>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Age</label>
                                    <input type="number" name="age" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($form_data['age'] ?? '') ?>" 
                                           min="1" max="120" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="Male" <?= ($form_data['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($form_data['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>" 
                                           required>
                                </div>
                            </div>
                        <?php endif; ?>
                        <!-- Visit Type -->
                        <div class="mb-4">
                            <label class="form-label">Visit Type</label>
                            <input type="text" name="visit_type" 
                                   class="form-control"
                                   value="<?= htmlspecialchars($form_data['visit_type'] ?? 'General Checkup') ?>" 
                                   required>
                        </div>
                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            Confirm Booking
                        </button>
                    </form>
                </div>
            </div>
            <?php if ($error_message && !empty($available_slots)): ?>
                <script>
                    // Show available slots modal
                    const slots = <?= json_encode($available_slots) ?>;
                    const formData = <?= json_encode($form_data) ?>;
                    Swal.fire({
                        icon: 'error',
                        title: 'Time Slot Unavailable',
                        html: `<div class="mt-3">
                                <p>Available slots for <?= date('M j, Y', strtotime($form_data['appointment_date'])) ?>:</p>
                                <div class="row g-2">
                                    ${slots.map(slot => {
                                        const [h, m] = slot.split(':');
                                        const hours = h % 12 || 12;
                                        const ampm = h < 12 ? 'AM' : 'PM';
                                        return `
                                            <div class="col-6 col-md-4">
                                                <button class="btn btn-outline-success w-100 slot-btn py-2"
                                                        data-slot="${slot}">
                                                    ${hours}:${m} ${ampm}
                                                </button>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                              </div>`,
                        showConfirmButton: false,
                        showCloseButton: true
                    });
                    // Slot selection handler
                    document.querySelectorAll('.slot-btn').forEach(btn => {
                        btn.addEventListener('click', () => {
                            document.querySelector('[name="appointment_time"]').value = btn.dataset.slot;
                            document.querySelector('form').submit();
                        });
                    });
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>