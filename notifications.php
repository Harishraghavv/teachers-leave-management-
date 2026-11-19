<?php
require_once '../includes/db_config.php';

if (!isStaffLoggedIn()) {
    redirect('../index.php');
}

$staff_id = $_SESSION['staff_id'];
$staff = getStaffDetails($conn, $staff_id);

// Mark all as read
if (isset($_GET['mark_read'])) {
    $sql = "UPDATE notifications SET is_read = TRUE WHERE staff_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    redirect('notifications.php');
}

// Get notifications
$sql = "SELECT * FROM notifications WHERE staff_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$notifications = $stmt->get_result();
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
    <title>Notifications | Staff Portal</title>
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
            <h5>Dept of Computer Science</h5>
            <p>Leave & OD System</p>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home"></i><span>Dashboard</span></a></li>
            <li class="nav-item"><a href="apply_leave.php" class="nav-link"><i data-lucide="calendar-plus"></i><span>Apply Leave</span></a></li>
            <li class="nav-item"><a href="leave_history.php" class="nav-link"><i data-lucide="history"></i><span>Leave History</span></a></li>
            <li class="nav-item"><a href="approval_messages.php" class="nav-link"><i data-lucide="mail-open"></i><span>Approval Messages</span></a></li>
            <li class="nav-item"><a href="notifications.php" class="nav-link active"><i data-lucide="bell"></i><span>Notifications</span></a></li>
            <li class="nav-item"><a href="profile.php" class="nav-link"><i data-lucide="user"></i><span>My Profile</span></a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-profile">
                <?php if (!empty($staff['profile_image']) && $staff['profile_image'] !== 'default.png'): ?>
                    <img src="../assets/images/profiles/<?php echo $staff['profile_image']; ?>" alt="Profile" class="user-avatar" style="object-fit: cover;">
                <?php else: ?>
                    <div class="user-avatar"><?php echo strtoupper(substr($staff['name'], 0, 1)); ?></div>
                <?php endif; ?>
                <div class="user-info">
                    <h6><?php echo $staff['name']; ?></h6>
                    <p><?php echo $staff['department']; ?></p>
                </div>
            </div>
            <a href="../logout.php" class="nav-link mt-3" style="padding: 10px 0;"><i data-lucide="log-out"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h4>Notifications</h4>
                <p>Stay updated with your leave status</p>
            </div>
        </div>

        <div class="notifications-card">
            <div class="notif-header">
                <h5><i class="fas fa-bell me-2" style="color: var(--warning);"></i>All Notifications</h5>
                <a href="?mark_read=1" class="btn-mark-read"><i data-lucide="check-double me-2"></i>Mark All Read</a>
            </div>

            <?php if ($notifications->num_rows > 0): ?>
                <?php while ($notif = $notifications->fetch_assoc()): ?>
                <div class="notif-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                    <div class="notif-icon <?php echo strtolower($notif['type']); ?>">
                        <i data-lucide="<?php 
                            echo $notif['type'] == 'Leave' ? 'calendar-check' : 
                                ($notif['type'] == 'OD' ? 'briefcase' : 
                                    ($notif['type'] == 'Alert' ? 'exclamation-triangle' : 'info-circle')); 
                        ?>"></i>
                    </div>
                    <div class="notif-content">
                        <h6>
                            <?php echo $notif['title']; ?>
                            <?php if (!$notif['is_read']): ?><span class="notif-badge"></span><?php endif; ?>
                        </h6>
                        <p><?php echo $notif['message']; ?></p>
                    </div>
                    <span class="notif-time"><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></span>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="bell-off"></i>
                    <h5>No Notifications</h5>
                    <p style="color: #94a3b8; font-size: 0.85rem;">You're all caught up!</p>
                </div>
            <?php endif; ?>
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



