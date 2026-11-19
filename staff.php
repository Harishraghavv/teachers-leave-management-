<?php
require_once '../includes/db_config.php';

if (!isAdminLoggedIn()) {
    redirect('../index.php');
}

$admin_id = $_SESSION['admin_id'];
$admin = getAdminDetails($conn, $admin_id);

// Role-Based Access Control Filtering
$is_principal = ($admin['role'] === 'Principal');
$filter_dept = '';

if ($is_principal) {
    if (isset($_GET['dept']) && $_GET['dept'] !== '') {
        $filter_dept = $_GET['dept'];
    }
<?php
require_once '../includes/db_config.php';

if (!isAdminLoggedIn()) {
    redirect('../index.php');
}

$admin_id = $_SESSION['admin_id'];
$admin = getAdminDetails($conn, $admin_id);

// Role-Based Access Control Filtering
$is_principal = ($admin['role'] === 'Principal');
$filter_dept = '';

if ($is_principal) {
    if (isset($_GET['dept']) && $_GET['dept'] !== '') {
        $filter_dept = $_GET['dept'];
    }
} else {
    $filter_dept = $admin['department'];
}

$dept_sql_condition = $filter_dept !== '' ? " WHERE s.department = '" . $conn->real_escape_string($filter_dept) . "'" : "";

// Handle Status Toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = sanitize($conn, $_GET['id']);
    $new_status = sanitize($conn, $_GET['toggle_status']);
    if (in_array($new_status, ['Active', 'Inactive'])) {
        $stmt = $conn->prepare("UPDATE staff SET status = ? WHERE staff_id = ?");
        $stmt->bind_param("si", $new_status, $id);
        $stmt->execute();
        $_SESSION['success'] = "Staff status updated successfully.";
    }
    redirect('staff.php');
}

// Handle Reset Staff Data
if (isset($_GET['reset_staff']) && isset($_GET['id'])) {
    $id = sanitize($conn, $_GET['id']);
    $stmt = $conn->prepare("DELETE FROM leave_requests WHERE staff_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt = $conn->prepare("DELETE FROM notifications WHERE staff_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['success'] = "Staff leave data and history completely reset.";
    redirect('staff.php');
}

// Handle Delete Staff
if (isset($_GET['delete_staff']) && isset($_GET['id'])) {
    $id = sanitize($conn, $_GET['id']);
    $stmt = $conn->prepare("DELETE FROM leave_requests WHERE staff_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt = $conn->prepare("DELETE FROM notifications WHERE staff_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['success'] = "Staff account permanently deleted.";
    redirect('staff.php');
}

// Fetch Staff
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM leave_requests lr WHERE lr.staff_id = s.staff_id AND lr.status = 'Approved' AND lr.leave_type = 'CL') as cl_taken,
        (SELECT COUNT(*) FROM leave_requests lr WHERE lr.staff_id = s.staff_id AND lr.status = 'Approved' AND lr.leave_type = 'OD') as od_taken
        FROM staff s $dept_sql_condition ORDER BY s.name ASC";
$staff_list = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#1E40AF">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Directory | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
        <i data-lucide="menu"></i>
    </button>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
<script src="../assets/js/specular.js"></script>
<script src="../assets/js/star_border.js"></script>
<script src="../assets/js/magic_bento.js"></script>
</body>
</html>


