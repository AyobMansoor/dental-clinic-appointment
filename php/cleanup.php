<?php
require 'db.php';

// Delete appointments where scheduled time has passed
$currentDateTime = date('Y-m-d H:i:s');
$stmt = $conn->prepare("
    DELETE FROM appointments 
    WHERE CONCAT(appointment_date, ' ', appointment_time) < ?
");
$stmt->bind_param('s', $currentDateTime);
$stmt->execute();

echo "Deleted " . $stmt->affected_rows . " expired appointments.";
$stmt->close();
$conn->close();
?>