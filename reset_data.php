<?php
require_once '../includes/db_config.php';

if (!isStaffLoggedIn()) {
    redirect('../index.php');
}

$staff_id = $_SESSION['staff_id'];

if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    // Delete all leave requests for this staff
    $stmt = $conn->prepare("DELETE FROM leave_requests WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();

    // Delete all notifications for this staff
    $stmt = $conn->prepare("DELETE FROM notifications WHERE staff_id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    
    $_SESSION['msg'] = "Your account data has been completely reset!";
    $_SESSION['msg_type'] = "success";
}

redirect('dashboard.php');
?>
