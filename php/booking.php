<?php
session_start();
require_once 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if the user is already in the patients table
$patient_query = "SELECT COUNT(*) as count FROM patients WHERE user_id = ?";
$stmt = $conn->prepare($patient_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$is_patient = $row['count'] > 0;

// If not in patients table, process additional details
if (!$is_patient && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 120]]);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);

    if ($age && $gender && $phone) {
        $insert_patient = "INSERT INTO patients (user_id, age, gender, phone) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_patient);
        $stmt->bind_param("iiss", $user_id, $age, $gender, $phone);
        $stmt->execute();
        $is_patient = true;
    }
}

// Fetch visit types
$visit_types = ["General Dentistry", "Cosmetic Dentistry", "Orthodontics", "Periodontics", "Oral Surgery", "Prosthodontics"];

// Fetch doctors
$doctor_query = "SELECT doctors.id AS doctor_id, users.firstname, users.lastname FROM doctors JOIN users ON doctors.user_id = users.id";
$doctors = $conn->query($doctor_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 800px; }
        .form-control, .form-select { margin-bottom: 1rem; }
        .btn-primary, .btn-success { width: 100%; padding: 10px; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center mb-4">Book an Appointment</h2>

        <?php if (!$is_patient): ?>
            <script>
                Swal.fire({
                    icon: 'info',
                    title: 'Additional Information Required',
                    text: 'This is your first appointment. Please enter your details.'
                });
            </script>
            <form method="post">
                <div class="mb-3">
                    <label for="age" class="form-label">Age</label>
                    <input type="number" name="age" class="form-control" required min="1" max="120">
                </div>
                <div class="mb-3">
                    <label for="gender" class="form-label">Gender</label>
                    <select name="gender" class="form-select" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        <?php else: ?>
            <form method="post" action="process_booking.php" onsubmit="return validateDateTime()">
                <div class="mb-3">
                    <label for="visit_type" class="form-label">Type of Visit</label>
                    <select name="visit_type" class="form-select" required>
                        <option value="" disabled selected>Select Visit Type</option>
                        <?php foreach ($visit_types as $type): ?>
                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="doctor_id" class="form-label">Choose a Doctor</label>
                    <select name="doctor_id" id="doctor_id" class="form-select" required>
                        <option value="" disabled selected>Select a Doctor</option>
                        <?php while ($doctor = $doctors->fetch_assoc()): ?>
                            <option value="<?= $doctor['doctor_id']; ?>">
                                <?= htmlspecialchars($doctor['firstname'] . " " . $doctor['lastname']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="appointment_date" class="form-label">Select Date</label>
                    <select name="appointment_date" id="appointment_date" class="form-select" required>
                        <option value="" disabled selected>Select a Date</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="appointment_time" class="form-label">Select Time (9 AM - 9 PM)</label>
                    <input type="time" name="appointment_time" id="appointment_time" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-success">Book Appointment</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById("doctor_id").addEventListener("change", fetchDoctorAvailability);

        function fetchDoctorAvailability() {
            let doctorId = document.getElementById("doctor_id").value;
            let dateInput = document.getElementById("appointment_date");

            if (!doctorId) return;

            fetch("get_doctor_availability.php?doctor_id=" + doctorId)
                .then(response => response.json())
                .then(data => {
                    dateInput.innerHTML = "";
                    if (data.success) {
                        data.dates.forEach(date => {
                            let option = document.createElement("option");
                            let [fullDate, day] = date.split(", ");
                            option.value = fullDate;
                            option.textContent = `${fullDate} (${day})`;
                            dateInput.appendChild(option);
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message
                        });
                    }
                });
        }

        function validateDateTime() {
            const dateInput = document.getElementById("appointment_date").value;
            const timeInput = document.getElementById("appointment_time").value;
            const selectedDateTime = new Date(`${dateInput}T${timeInput}`);
            const now = new Date();

            // Restrict time selection between 9 AM and 9 PM
            let selectedHour = selectedDateTime.getHours();
            if (selectedHour < 9 || selectedHour >= 21) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Time',
                    text: 'Please select a time between 9 AM and 9 PM.'
                });
                return false;
            }

            if (selectedDateTime <= now) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Selection',
                    text: 'Please select a future date and time.'
                });
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
