<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Staff Leave & OD Management System
 * Database Configuration
 * Dept of Computer Science (Autonomous), Vaniyambadi
 */

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'teacher_leave_od');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to handle special characters
$conn->set_charset("utf8mb4");

// Session configuration
session_start();

// Time zone
date_default_timezone_set('Asia/Kolkata');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Helper function to sanitize input
function sanitize($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $conn->real_escape_string($data);
}

// Helper function to display messages
function showMessage($message, $type = 'success') {
    $icon = $type == 'success' ? 'check-circle' : ($type == 'danger' ? 'exclamation-circle' : 'info-circle');
    return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
            <i class='fas fa-{$icon} me-2'></i>{$message}
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
}

// Helper function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Check if user is logged in as staff
function isStaffLoggedIn() {
    return isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']);
}

// Check if user is logged in as admin
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Get staff details
function getStaffDetails($conn, $staff_id) {
    $sql = "SELECT * FROM staff WHERE staff_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get admin details
function getAdminDetails($conn, $admin_id) {
    $sql = "SELECT * FROM admin WHERE admin_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get leave balance
function getLeaveBalance($conn, $staff_id, $year = null) {
    if (!$year) $year = date('Y');
    $sql = "SELECT * FROM leave_balance WHERE staff_id = ? AND year = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $staff_id, $year);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Generate QR Code for OD
function generateQRCode($leave_id) {
    $token = 'OD-' . date('Y-n-j') . '-' . uniqid();
    return $token;
}

// Get day of week from date
function getDayOfWeek($date) {
    return date('l', strtotime($date));
}

// Format date
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

// Get notification count
function getNotificationCount($conn, $staff_id) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE staff_id = ? AND is_read = FALSE";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'];
}
?>


