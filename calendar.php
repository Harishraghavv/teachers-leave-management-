<?php
require_once '../includes/db_config.php';

if (!isStaffLoggedIn()) {
    redirect('../index.php');
}

$staff_id = $_SESSION['staff_id'];
$staff = getStaffDetails($conn, $staff_id);

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

// Fetch this staff's own leaves
$first_day = "$year-$month-01";
$last_day = date("Y-m-t", strtotime($first_day));

$sql = "SELECT * FROM leave_requests 
        WHERE staff_id = ? AND status = 'Approved' 
        AND (from_date <= ? AND to_date >= ?)
        ORDER BY from_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $staff_id, $last_day, $first_day);
$stmt->execute();
$leaves = $stmt->get_result();

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

// Government Holidays (Hardcoded standard list for the year)
$govt_holidays = [
    "$year-01-01" => "New Year's Day",
    "$year-01-14" => "Pongal / Makar Sankranti",
    "$year-01-26" => "Republic Day",
    "$year-04-14" => "Tamil New Year / Ambedkar Jayanti",
    "$year-05-01" => "May Day / Labour Day",
    "$year-08-15" => "Independence Day",
    "$year-10-02" => "Gandhi Jayanti",
    "$year-10-23" => "Ayudha Pooja",
    "$year-11-12" => "Diwali", // approximate
    "$year-12-25" => "Christmas"
];
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
    <title>Daily Calendar | Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .weekend-day {
            background-color: #fef2f2 !important;
            border-color: #fee2e2 !important;
        }
        .weekend-day .day-number {
            color: #ef4444;
            font-weight: 700;
        }
        .holiday-day {
            background-color: #f0fdf4 !important;
            border-color: #dcfce7 !important;
        }
        .holiday-day .day-number {
            color: #10b981;
            font-weight: 700;
        }
        .holiday-label {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 5px;
            display: inline-block;
            width: 100%;
            text-align: center;
        }
        .weekend-label {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 5px;
            display: inline-block;
            width: 100%;
            text-align: center;
        }
    </style>
</head>
<body>
    
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
        <i data-lucide="menu"></i>
    </button>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="text-center mb-3"><img src="../assets/images/logo.png" alt="College Logo" style="width: 90px; height: auto; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);"></div>
            <h5 style="font-size: 1.1rem;">Dept of <?php echo htmlspecialchars($staff['department']); ?></h5>
            <p>Leave & OD System</p>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home"></i><span>Dashboard</span></a></li>
            <li class="nav-item"><a href="apply_leave.php" class="nav-link"><i data-lucide="calendar-plus"></i><span>Apply Leave</span></a></li>
            <li class="nav-item"><a href="leave_history.php" class="nav-link"><i data-lucide="history"></i><span>Leave History</span></a></li>
            <li class="nav-item"><a href="calendar.php" class="nav-link active"><i data-lucide="calendar"></i><span>Daily Calendar</span></a></li>
            <li class="nav-item"><a href="approval_messages.php" class="nav-link"><i data-lucide="mail-open"></i><span>Approval Messages</span></a></li>
            <li class="nav-item"><a href="notifications.php" class="nav-link"><i data-lucide="bell"></i><span>Notifications</span></a></li>
            <li class="nav-item"><a href="profile.php" class="nav-link"><i data-lucide="user"></i><span>My Profile</span></a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h4>Daily Academic Calendar</h4>
                <p>View Govt Holidays, Weekends, and your personal leaves.</p>
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
                $current_day_index = $first_day_index;
                
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date_str = sprintf("%04d-%02d-%02d", $year, $month, $day);
                    $is_today = ($date_str == $today_str) ? 'today' : '';
                    
                    $is_weekend = ($current_day_index % 7 == 0 || $current_day_index % 7 == 6);
                    $is_holiday = isset($govt_holidays[$date_str]);
                    
                    $extra_classes = '';
                    if ($is_holiday) $extra_classes .= ' holiday-day';
                    else if ($is_weekend) $extra_classes .= ' weekend-day';
                    
                    echo "<div class='cal-day $is_today $extra_classes'>";
                    echo "<span class='day-number'>$day</span>";
                    
                    if ($is_holiday) {
                        echo "<div class='holiday-label'><i class='fas fa-star me-1'></i>" . $govt_holidays[$date_str] . "</div>";
                    } else if ($is_weekend) {
                        $day_name = ($current_day_index % 7 == 0) ? "Sunday" : "Saturday";
                        echo "<div class='weekend-label'>" . $day_name . "</div>";
                    }
                    
                    // Show staff's own leaves
                    if (isset($calendar_events[$date_str])) {
                        foreach ($calendar_events[$date_str] as $event) {
                            $type_class = strtolower($event['leave_type']) == 'cl' ? 'event-cl' : 'event-od';
                            echo "<div class='leave-event $type_class mt-2' title='{$event['reason']}'>";
                            echo "<i class='fas fa-user-check me-1'></i>My " . $event['leave_type'];
                            echo "</div>";
                        }
                    }
                    echo "</div>";
                    
                    $current_day_index++;
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

