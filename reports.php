<?php
require_once '../includes/db_config.php';

if (!isAdminLoggedIn()) {
    redirect('../index.php');
}

$admin_id = $_SESSION['admin_id'];
$admin = getAdminDetails($conn, $admin_id);
$is_principal = ($admin['role'] === 'Principal');

// Export to CSV Logic
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="leave_report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Leave ID', 'Staff Name', 'Department', 'Leave Type', 'From Date', 'To Date', 'Status', 'Applied On']);
    
    // Build query
    $sql = "SELECT lr.*, t.name as staff_name, t.department 
            FROM leave_requests lr
            JOIN staff t ON lr.staff_id = t.staff_id WHERE 1=1";
            
    $params = [];
    $types = "";
    
    if (!empty($_GET['dept'])) {
        $sql .= " AND t.department = ?";
        $params[] = sanitize($conn, $_GET['dept']);
        $types .= "s";
    }
    if (!empty($_GET['type'])) {
        $sql .= " AND lr.leave_type = ?";
        $params[] = sanitize($conn, $_GET['type']);
        $types .= "s";
    }
    if (!empty($_GET['reason'])) {
        $sql .= " AND lr.reason LIKE ?";
        $params[] = '%' . sanitize($conn, $_GET['reason']) . '%';
        $types .= "s";
    }
    
    if (!empty($_GET['date_range'])) {
        $range = $_GET['date_range'];
        $today = date('Y-m-d');
        if ($range == 'today') {
            $sql .= " AND lr.from_date <= ? AND lr.to_date >= ?";
            $params[] = $today;
            $params[] = $today;
            $types .= "ss";
        } elseif ($range == 'this_week') {
            $start = date('Y-m-d', strtotime('monday this week'));
            $end = date('Y-m-d', strtotime('sunday this week'));
            $sql .= " AND lr.from_date >= ? AND lr.from_date <= ?";
            $params[] = $start;
            $params[] = $end;
            $types .= "ss";
        } elseif ($range == 'this_month') {
            $start = date('Y-m-01');
            $end = date('Y-m-t');
            $sql .= " AND lr.from_date >= ? AND lr.from_date <= ?";
            $params[] = $start;
            $params[] = $end;
            $types .= "ss";
        } elseif ($range == 'this_year') {
            $start = date('Y-01-01');
            $end = date('Y-12-31');
            $sql .= " AND lr.from_date >= ? AND lr.from_date <= ?";
            $params[] = $start;
            $params[] = $end;
            $types .= "ss";
        }
    }
    
    $sql .= " ORDER BY lr.applied_on DESC";
    
    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['leave_id'],
            $row['staff_name'],
            $row['department'],
            $row['leave_type'],
            $row['from_date'],
            $row['to_date'],
            $row['status'],
            date('Y-m-d', strtotime($row['applied_on']))
        ]);
    }
    fclose($output);
    exit;
}

// Regular page rendering
if ($is_principal) {
    $filter_dept = isset($_GET['dept']) ? sanitize($conn, $_GET['dept']) : '';
} else {
    // Force HOD's department
    $filter_dept = $admin['department'];
    $_GET['dept'] = $admin['department'];
}
$filter_type = isset($_GET['type']) ? sanitize($conn, $_GET['type']) : '';
$filter_reason = isset($_GET['reason']) ? sanitize($conn, $_GET['reason']) : '';
$filter_date_range = isset($_GET['date_range']) ? sanitize($conn, $_GET['date_range']) : '';

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($filter_dept) {
    $where .= " AND t.department = ?";
    $params[] = $filter_dept;
    $types .= "s";
}
if ($filter_type) {
    $where .= " AND lr.leave_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}
if ($filter_reason) {
    $where .= " AND lr.reason LIKE ?";
    $params[] = '%' . $filter_reason . '%';
    $types .= "s";
}

if ($filter_date_range) {
    $range = $filter_date_range;
    $today = date('Y-m-d');
    if ($range == 'today') {
        $where .= " AND lr.from_date <= ? AND lr.to_date >= ?";
        $params[] = $today;
        $params[] = $today;
        $types .= "ss";
    } elseif ($range == 'this_week') {
        $start = date('Y-m-d', strtotime('monday this week'));
        $end = date('Y-m-d', strtotime('sunday this week'));
        $where .= " AND lr.from_date >= ? AND lr.from_date <= ?";
        $params[] = $start;
        $params[] = $end;
        $types .= "ss";
    } elseif ($range == 'this_month') {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        $where .= " AND lr.from_date >= ? AND lr.from_date <= ?";
        $params[] = $start;
        $params[] = $end;
        $types .= "ss";
    } elseif ($range == 'this_year') {
        $start = date('Y-01-01');
        $end = date('Y-12-31');
        $where .= " AND lr.from_date >= ? AND lr.from_date <= ?";
        $params[] = $start;
        $params[] = $end;
        $types .= "ss";
    }
}

$sql = "SELECT lr.*, t.name as staff_name, t.department 
        FROM leave_requests lr
        JOIN staff t ON lr.staff_id = t.staff_id 
        $where
        ORDER BY lr.applied_on DESC";
        
$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports = $stmt->get_result();

// Get unique departments for filter
$dept_result = $conn->query("SELECT DISTINCT department FROM staff WHERE status = 'Active'");
$departments = [];
while ($row = $dept_result->fetch_assoc()) {
    $departments[] = $row['department'];
}
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
    <title>Reports | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
        <i data-lucide="menu"></i>
    </button>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/images/logo.png" alt="College Logo" style="width: 85px; height: auto; margin-bottom: 15px;">
            <h5 style="font-size: 1.1rem;"><?php echo $is_principal ? 'College Administration' : 'Dept of ' . htmlspecialchars($admin['department']); ?></h5>
            <p>Admin Portal</p>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home"></i><span>Dashboard</span></a></li>
            <li class="nav-item"><a href="pending_requests.php" class="nav-link"><i data-lucide="clock"></i><span>Pending Requests</span></a></li>
            <li class="nav-item"><a href="leave_calendar.php" class="nav-link"><i data-lucide="calendar"></i><span>Leave Calendar</span></a></li>
            <li class="nav-item"><a href="analytics.php" class="nav-link"><i data-lucide="line-chart"></i><span>Analytics</span></a></li>
            <li class="nav-item"><a href="staff.php" class="nav-link"><i data-lucide="users"></i><span>Staffs</span></a></li>
            
            <li class="nav-item"><a href="reports.php" class="nav-link active"><i data-lucide="file-text"></i><span>Reports</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-profile">
                <?php if (!empty($admin['profile_image']) && $admin['profile_image'] !== 'default.png'): ?>
                    <img src="../assets/images/profiles/<?php echo $admin['profile_image']; ?>" alt="Profile" class="user-avatar" style="object-fit: cover;">
                <?php else: ?>
                    <div class="user-avatar"><?php echo strtoupper(substr($admin['name'], 0, 1)); ?></div>
                <?php endif; ?>
                <div class="user-info">
                    <h6><?php echo $admin['name']; ?></h6>
                    <p><?php echo $admin['role']; ?></p>
                </div>
            </div>
            <a href="../logout.php" class="nav-link mt-3" style="padding: 10px 0;"><i data-lucide="log-out"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h4>Custom Reports</h4>
                <p>Generate and export leave/OD records</p>
            </div>
            <div>
                <?php
                    // Build current query string for export
                    $qs = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
                    $export_link = "?export=csv" . ($qs ? "&" . str_replace("export=csv", "", $qs) : "");
                ?>
                <a href="<?php echo $export_link; ?>" class="btn btn-success"><i data-lucide="file-csv me-2"></i>Export to CSV</a>
            </div>
        </div>

        <div class="filter-card">
            <form method="GET" action="">
                <!-- First Row -->
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <?php if ($is_principal): ?>
                        <select name="dept" class="form-select">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d; ?>" <?php echo $filter_dept == $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['department']); ?>" readonly>
                        <input type="hidden" name="dept" value="<?php echo htmlspecialchars($admin['department']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Leave Type</label>
                        <select name="type" class="form-select" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <option value="CL" <?php echo $filter_type == 'CL' ? 'selected' : ''; ?>>CL (Casual Leave)</option>
                            <option value="OD" <?php echo $filter_type == 'OD' ? 'selected' : ''; ?>>OD (On Duty)</option>
                            <option value="EL" <?php echo $filter_type == 'EL' ? 'selected' : ''; ?>>EL (Earned Leave)</option>
                            <option value="ML" <?php echo $filter_type == 'ML' ? 'selected' : ''; ?>>ML (Medical Leave)</option>
                            <option value="LPA" <?php echo $filter_type == 'LPA' ? 'selected' : ''; ?>>LPA Leave</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <div class="report-table-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Staff Name</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Date Range</th>
                            <th>Status</th>
                            <th>Applied On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reports->num_rows > 0): ?>
                            <?php while ($row = $reports->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo $row['staff_name']; ?></div>
                                    </td>
                                    <td><?php echo $row['department']; ?></td>
                                    <td><span class="type-badge type-<?php echo $row['leave_type']; ?>"><?php echo $row['leave_type']; ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($row['from_date'])); ?> - <?php echo date('d M Y', strtotime($row['to_date'])); ?></td>
                                    <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($row['applied_on'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No records found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
<script src="../assets/js/specular.js"></script>
<script src="../assets/js/star_border.js"></script>
<script src="../assets/js/magic_bento.js"></script>
</body>
</html>


