<?php
session_start();
require 'db.php';

// Log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/notification_errors.log');

// Validate CSRF token
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $csrf_token) {
    error_log("CSRF token mismatch: " . json_encode($_SERVER));
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit();
}

// Process request
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = (int)$data['id'];

// Update notification
$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
$stmt->bind_param('i', $notification_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    error_log("DB Error: " . $stmt->error);
    echo json_encode(['error' => 'Failed to mark notification as read']);
}

$stmt->close();
$conn->close();
?>