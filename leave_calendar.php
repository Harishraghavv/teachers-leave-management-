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

$dept_sql_condition = $filter_dept !== '' ? " AND t.department = '" . $conn->real_escape_string($filter_dept) . "'" : "";

// Get month and year for calendar (default to current)
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Calculate previous and next months
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month == 0) { $prev_month = 12; $prev_year--; }

$next_month = $month + 1;
$next_year = $year;
if ($next_month == 13) { $next_month = 1; $next_year++; }

$month_name = date("F", mktime(0, 0, 0, $month, 10));

// Fetch approved leaves overlapping with the selected month
$first_day = "$year-$month-01";
$last_day = date("Y-m-t", strtotime($first_day));

$sql = "SELECT lr.*, t.name as staff_name, t.department 
        FROM leave_requests lr
        JOIN staff t ON lr.staff_id = t.staff_id
        WHERE lr.status = 'Approved' 
        AND (lr.from_date <= ? AND lr.to_date >= ?) $dept_sql_condition
        ORDER BY lr.from_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $last_day, $first_day);
$stmt->execute();
$leaves = $stmt->get_result();

// Create an array mapping dates to leave events
$calendar_events = [];
while ($row = $leaves->fetch_assoc()) {
    $start = new DateTime($row['from_date']);
    $end = new DateTime($row['to_date']);
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
    
    foreach ($period as $dt) {
        $date_str = $dt->format("Y-m-d");
        if (!isset($calendar_events[$date_str])) {
            $calendar_events[$date_str] = [];
        }
        $calendar_events[$date_str][] = $row;
    }
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
    <title>Leave Calendar | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            <li class="nav-item"><a href="leave_calendar.php" class="nav-link active"><i data-lucide="calendar"></i><span>Leave Calendar</span></a></li>
            <li class="nav-item"><a href="analytics.php" class="nav-link"><i data-lucide="line-chart"></i><span>Analytics</span></a></li>
            <li class="nav-item"><a href="staff.php" class="nav-link"><i data-lucide="users"></i><span>Staffs</span></a></li>
            
            <li class="nav-item"><a href="reports.php" class="nav-link"><i data-lucide="file-text"></i><span>Reports</span></a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h4>Leave Calendar</h4>
                <p>Visual overview of staff availability</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if ($is_principal): ?>
                <form method="GET" action="" class="d-flex align-items-center m-0" id="deptFilterForm">
                    <input type="hidden" name="month" value="<?php echo $month; ?>">
                    <input type="hidden" name="year" value="<?php echo $year; ?>">
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

        <div class="calendar-header">
            <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="cal-nav-btn"><i data-lucide="chevron-left"></i> Previous</a>
            <h3 class="cal-title"><?php echo $month_name . ' ' . $year; ?></h3>
            <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="cal-nav-btn">Next <i data-lucide="chevron-right"></i></a>
        </div>

        <div class="calendar-grid">
            <?php 
                $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                foreach($days as $day) echo "<div class='day-header'>$day</div>";

                $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $first_day_index = date('w', strtotime("$year-$month-01"));

                // Blank days
                for ($i = 0; $i < $first_day_index; $i++) {
                    echo "<div class='cal-day empty'></div>";
                }

                // Days of month
                $today_str = date('Y-m-d');
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date_str = sprintf("%04d-%02d-%02d", $year, $month, $day);
                    $is_today = ($date_str == $today_str) ? 'today' : '';
                    
                    echo "<div class='cal-day $is_today'>";
                    echo "<span class='day-number'>$day</span>";
                    
                    if (isset($calendar_events[$date_str])) {
                        foreach ($calendar_events[$date_str] as $event) {
                            $type_class = strtolower($event['leave_type']) == 'cl' ? 'event-cl' : 'event-od';
                            $title = $event['staff_name'] . ' (' . $event['leave_type'] . ')';
                            echo "<div class='leave-event $type_class' title='{$event['department']} - {$event['reason']}'>";
                            echo "<i class='fas fa-user me-1'></i>" . mb_strimwidth($event['staff_name'], 0, 12, '...');
                            echo "</div>";
                        }
                    }
                    echo "</div>";
                }
            ?>
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


