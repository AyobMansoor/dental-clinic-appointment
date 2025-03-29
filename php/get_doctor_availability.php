<?php
require_once 'db.php';

if (isset($_GET['doctor_id'])) {
    $doctor_id = $_GET['doctor_id'];

    // Fetch available days for the doctor
    $query = "SELECT available_day FROM doctor_availability WHERE doctor_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $available_days = [];
    while ($row = $result->fetch_assoc()) {
        $available_days[] = $row['available_day'];
    }

    if (empty($available_days)) {
        echo json_encode(["success" => false, "message" => "No available dates for this doctor."]);
        exit();
    }

    // Map weekday names to numbers (to calculate upcoming dates)
    $days_map = [
        "Saturday" => 1,
        "Sunday" => 2,
        "Monday" => 3,
        "Tuesday" => 4,
        "Wednesday" => 5,
        "Thursday" => 6 
        
    ];

    // Convert available days to numeric values
    $available_days_numbers = array_map(fn($day) => $days_map[$day], $available_days);

    // Find the next 30 days that match the doctor's available days
    $dates = [];
    $today = new DateTime();
    for ($i = 0; $i < 30; $i++) {
        $check_date = clone $today;
        $check_date->modify("+$i day");
        if (in_array($check_date->format("N"), $available_days_numbers)) {
            $dates[] = $check_date->format("Y-m-d, l");
        }
    }

    echo json_encode(["success" => true, "dates" => $dates]);
}
?>
