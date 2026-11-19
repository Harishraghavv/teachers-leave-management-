<?php
require_once '../includes/db_config.php';

if (!isAdminLoggedIn()) {
    redirect('../index.php');
}

$admin_id = $_SESSION['admin_id'];

// Get admin role to determine approval level
$admin_details = getAdminDetails($conn, $admin_id);
$admin_role = $admin_details['role'];

// Handle both GET (legacy) and POST (new form with remarks)
$leave_id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$action = isset($_POST['action']) ? sanitize($conn, $_POST['action']) : (isset($_GET['action']) ? sanitize($conn, $_GET['action']) : '');
$remarks = isset($_POST['admin_remarks']) ? sanitize($conn, $_POST['admin_remarks']) : '';

if (!$leave_id || !$action) {
    redirect('dashboard.php');
}

if ($action == 'approve') {
    
    if ($admin_role === 'HOD') {
        // Step 1: HOD Approval
        $sql = "UPDATE leave_requests SET status = 'HOD Approved', approved_by = ?, approved_on = NOW(), admin_remarks = ? WHERE leave_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $admin_id, $remarks, $leave_id);

        if ($stmt->execute()) {
            $leave_sql = "SELECT * FROM leave_requests WHERE leave_id = ?";
            $leave_stmt = $conn->prepare($leave_sql);
            $leave_stmt->bind_param("i", $leave_id);
            $leave_stmt->execute();
            $leave = $leave_stmt->get_result()->fetch_assoc();

            $notif_sql = "INSERT INTO notifications (staff_id, title, message, type) VALUES (?, ?, ?, ?)";
            $notif_stmt = $conn->prepare($notif_sql);
            $title = $leave['leave_type'] . ' HOD Approved';
            $msg = 'Your ' . $leave['leave_type'] . ' request has been Approved by HOD. Awaiting Principal Approval.';
            if ($remarks) {
                $msg .= ' Remarks: ' . $remarks;
            }
            $notif_type = ($leave['leave_type'] === 'OD') ? 'OD' : 'Leave';
            $notif_stmt->bind_param("isss", $leave['staff_id'], $title, $msg, $notif_type);
            $notif_stmt->execute();

            $_SESSION['success'] = 'Leave request approved by HOD successfully! Forwarded to Principal.';
        }

    } else if ($admin_role === 'Principal' || $admin_role === 'Admin') {
        // Step 2: Final Approval
        $sql = "UPDATE leave_requests SET status = 'Approved', approved_by = ?, approved_on = NOW(), admin_remarks = ? WHERE leave_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $admin_id, $remarks, $leave_id);

        if ($stmt->execute()) {
            // Get leave details
            $leave_sql = "SELECT * FROM leave_requests WHERE leave_id = ?";
            $leave_stmt = $conn->prepare($leave_sql);
            $leave_stmt->bind_param("i", $leave_id);
            $leave_stmt->execute();
            $leave = $leave_stmt->get_result()->fetch_assoc();

            // Update leave balance for CL
            if ($leave['leave_type'] == 'CL') {
                $days = (strtotime($leave['to_date']) - strtotime($leave['from_date'])) / (60 * 60 * 24) + 1;
                $balance_sql = "UPDATE leave_balance SET cl_balance = cl_balance - ?, cl_used = cl_used + ? 
                               WHERE staff_id = ? AND year = YEAR(CURDATE())";
                $balance_stmt = $conn->prepare($balance_sql);
                $balance_stmt->bind_param("iii", $days, $days, $leave['staff_id']);
                $balance_stmt->execute();
            } else {
                // Update OD count
                $od_sql = "UPDATE leave_balance SET od_count = od_count + 1 
                          WHERE staff_id = ? AND year = YEAR(CURDATE())";
                $od_stmt = $conn->prepare($od_sql);
                $od_stmt->bind_param("i", $leave['staff_id']);
                $od_stmt->execute();
            }

            // Select best substitute suggestion
            $sub_sql = "SELECT substitute_staff_id FROM substitute_suggestions 
                       WHERE leave_id = ? ORDER BY match_score DESC LIMIT 1";
            $sub_stmt = $conn->prepare($sub_sql);
            $sub_stmt->bind_param("i", $leave_id);
            $sub_stmt->execute();
            $sub_result = $sub_stmt->get_result();
            if ($sub_result->num_rows > 0) {
                $sub = $sub_result->fetch_assoc();
                $update_sub_sql = "UPDATE leave_requests SET substitute_staff_id = ? WHERE leave_id = ?";
                $update_sub_stmt = $conn->prepare($update_sub_sql);
                $update_sub_stmt->bind_param("ii", $sub['substitute_staff_id'], $leave_id);
                $update_sub_stmt->execute();
            }

            // Add notification
            $notif_sql = "INSERT INTO notifications (staff_id, title, message, type) VALUES (?, ?, ?, ?)";
            $notif_stmt = $conn->prepare($notif_sql);
            $title = $leave['leave_type'] . ' Final Approved';
            $msg = 'Your ' . $leave['leave_type'] . ' request from ' . formatDate($leave['from_date']) . ' has received Final Approval by Principal.';
            if ($remarks) {
                $msg .= ' Remarks: ' . $remarks;
            }
            $notif_type = ($leave['leave_type'] === 'OD') ? 'OD' : 'Leave';
            $notif_stmt->bind_param("isss", $leave['staff_id'], $title, $msg, $notif_type);
            $notif_stmt->execute();

            $_SESSION['success'] = 'Leave request granted Final Approval successfully!';
        }
    }

} elseif ($action == 'reject') {
    $sql = "UPDATE leave_requests SET status = 'Rejected', approved_by = ?, approved_on = NOW(), admin_remarks = ? WHERE leave_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $admin_id, $remarks, $leave_id);

    if ($stmt->execute()) {
        $leave_sql = "SELECT * FROM leave_requests WHERE leave_id = ?";
        $leave_stmt = $conn->prepare($leave_sql);
        $leave_stmt->bind_param("i", $leave_id);
        $leave_stmt->execute();
        $leave = $leave_stmt->get_result()->fetch_assoc();

        $notif_sql = "INSERT INTO notifications (staff_id, title, message, type) VALUES (?, ?, ?, ?)";
        $notif_stmt = $conn->prepare($notif_sql);
        $title = $leave['leave_type'] . ' Rejected';
        $msg = 'Your ' . $leave['leave_type'] . ' request from ' . formatDate($leave['from_date']) . ' has been rejected by ' . $admin_role . '.';
        if ($remarks) {
            $msg .= ' Remarks: ' . $remarks;
        }
        $notif_type = ($leave['leave_type'] === 'OD') ? 'OD' : 'Leave';
        $notif_stmt->bind_param("isss", $leave['staff_id'], $title, $msg, $notif_type);
        $notif_stmt->execute();

        $_SESSION['success'] = 'Leave request rejected.';
    }
}

redirect('dashboard.php');
?>
