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

$dept_sql_condition = $filter_dept !== '' ? " AND s.department = '" . $conn->real_escape_string($filter_dept) . "'" : "";

// Staff Leave Balances List
$staff_balance_sql = "
    SELECT s.name, s.designation, lb.cl_balance, lb.cl_used, lb.od_count,
           (SELECT from_date FROM leave_requests lr WHERE lr.staff_id = s.staff_id AND lr.status = 'Approved' AND lr.from_date <= CURDATE() ORDER BY lr.from_date DESC LIMIT 1) as last_leave
    FROM staff s 
    LEFT JOIN leave_balance lb ON s.staff_id = lb.staff_id AND lb.year = YEAR(CURDATE())
    WHERE s.status = 'Active' $dept_sql_condition
    ORDER BY s.name";
$staff_balances = $conn->query($staff_balance_sql);
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
    <title>Analytics | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
        <i data-lucide="menu"></i>
    </button>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i data-lucide="layers"></i></div>
            <h5 style="font-size: 1.1rem;"><?php echo $is_principal && $filter_dept == '' ? 'College Administration' : 'Dept of ' . htmlspecialchars($filter_dept !== '' ? $filter_dept : $admin['department']); ?></h5>
            <p>Admin Portal</p>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home"></i><span>Dashboard</span></a></li>
            <li class="nav-item"><a href="pending_requests.php" class="nav-link"><i data-lucide="clock"></i><span>Pending Requests</span></a></li>
            <li class="nav-item"><a href="leave_calendar.php" class="nav-link"><i data-lucide="calendar"></i><span>Leave Calendar</span></a></li>
            <li class="nav-item"><a href="analytics.php" class="nav-link active"><i data-lucide="line-chart"></i><span>Analytics</span></a></li>
            <li class="nav-item"><a href="staff.php" class="nav-link"><i data-lucide="users"></i><span>Staffs</span></a></li>
            
            <li class="nav-item"><a href="reports.php" class="nav-link"><i data-lucide="file-text"></i><span>Reports</span></a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h4>System Analytics</h4>
                <p>Data visualization for college leave patterns</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if ($is_principal): ?>
                <form method="GET" action="" class="d-flex align-items-center m-0" id="deptFilterForm">
                    <select name="dept" class="form-select form-select-sm" onchange="document.getElementById('deptFilterForm').submit();" style="width: 200px; border-radius: 8px; border-color: #e2e8f0; padding: 8px 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                        <option value="">All Departments</option>
                        <?php
                        $dept_list = $conn->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL");
                        while($d = $dept_list->fetch_assoc()):
                        ?>
                        <option value="<?php echo $d['department']; ?>" <?php echo $filter_dept == $d['department'] ? 'selected' : ''; ?>><?php echo $d['department']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </form>
                <?php endif; ?>
                <span class="admin-badge"><i data-lucide="shield-alt me-2"></i><?php echo $admin['role']; ?></span>
            </div>
        </div>

        <!-- Department Staff CL/OD List -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="chart-card">
                    <div class="chart-header mb-3">
                        <div class="chart-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i data-lucide="list-ul"></i></div>
                        <h5>Department Staff CL & OD List</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff Name</th>
                                    <th>Designation</th>
                                    <th class="text-center">Total CL Limit</th>
                                    <th class="text-center">CL Taken</th>
                                    <th class="text-center">CL Remaining</th>
                                    <th class="text-center">OD Taken</th>
                                    <th class="text-right">Last Leave</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($staff_balances && $staff_balances->num_rows > 0): ?>
                                    <?php while ($row = $staff_balances->fetch_assoc()): 
                                        $cl_limit = $row['cl_balance'] !== null ? $row['cl_balance'] : 12;
                                        $cl_taken = $row['cl_used'] !== null ? $row['cl_used'] : 0;
                                        $cl_remaining = $cl_limit - $cl_taken;
                                        $od_taken = $row['od_count'] !== null ? $row['od_count'] : 0;
                                        $last_leave = $row['last_leave'] ? date('d M Y', strtotime($row['last_leave'])) : 'None';
                                    ?>
                                    <tr>
                                        <td class="fw-bold" style="color: var(--primary);"><?php echo $row['name']; ?></td>
                                        <td style="color: #64748b; font-size: 0.9rem;"><?php echo $row['designation']; ?></td>
                                        <td class="text-center"><?php echo $cl_limit; ?></td>
                                        <td class="text-center"><span class="badge" style="background: #e0e7ff; color: #4f46e5; font-size: 0.85rem; padding: 6px 10px;"><?php echo $cl_taken; ?></span></td>
                                        <td class="text-center"><span class="badge" style="background: #dcfce7; color: #16a34a; font-size: 0.85rem; padding: 6px 10px;"><?php echo $cl_remaining; ?></span></td>
                                        <td class="text-center"><span class="badge" style="background: #fce7f3; color: #db2777; font-size: 0.85rem; padding: 6px 10px;"><?php echo $od_taken; ?></span></td>
                                        <td class="text-right" style="color: #64748b; font-size: 0.9rem; font-weight: 500;"><?php echo $last_leave; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No staff data available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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


