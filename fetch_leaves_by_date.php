<?php
require_once '../includes/db_config.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['date'])) {
    echo json_encode(['status' => 'error', 'message' => 'Date is required']);
    exit;
}

$date = $_GET['date'];

$admin_id = $_SESSION['admin_id'];
$admin = getAdminDetails($conn, $admin_id);

$is_principal = ($admin['role'] === 'Principal');
$filter_dept = '';

if ($is_principal) {
    if (isset($_GET['dept']) && $_GET['dept'] !== '') {
        $filter_dept = $_GET['dept'];
    }
} else {
    $filter_dept = $admin['department'];
}

$dept_sql_condition = $filter_dept !== '' ? " AND t.department = '" . $conn->real_escape_string($filter_dept) . "'" : "";

$sql = "SELECT lr.*, t.name as staff_name, t.department, t.subject
        FROM leave_requests lr
        JOIN staff t ON lr.staff_id = t.staff_id
        WHERE lr.status = 'Approved' 
        AND ? BETWEEN lr.from_date AND lr.to_date $dept_sql_condition
        ORDER BY t.department, t.name";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

$leaves = [];
while ($row = $result->fetch_assoc()) {
    $leaves[] = [
        'leave_id' => $row['leave_id'],
        'staff_name' => $row['staff_name'],
        'department' => $row['department'],
        'subject' => $row['subject'],
        'leave_type' => $row['leave_type'],
        'period_no' => $row['period_no']
    ];
}

echo json_encode(['status' => 'success', 'data' => $leaves, 'date' => $date]);
?>

