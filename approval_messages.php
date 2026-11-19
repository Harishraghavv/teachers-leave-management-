<?php
require_once '../includes/db_config.php';

if (!isStaffLoggedIn()) {
    redirect('../index.php');
}

$staff_id = $_SESSION['staff_id'];
$staff = getStaffDetails($conn, $staff_id);
$notification_count = getNotificationCount($conn, $staff_id);

// Get requests with admin remarks
$sql = "SELECT lr.*, a.name as admin_name 
        FROM leave_requests lr
        LEFT JOIN admin a ON lr.approved_by = a.admin_id
        WHERE lr.staff_id = ? AND lr.admin_remarks IS NOT NULL AND lr.admin_remarks != ''
        ORDER BY lr.applied_on DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$messages = $stmt->get_result();
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
    <title>Approval Messages | Staff Portal</title>
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
            <li class="nav-item"><a href="approval_messages.php" class="nav-link active"><i data-lucide="mail-open"></i><span>Approval Messages</span></a></li>
            <li class="nav-item"><a href="notifications.php" class="nav-link"><i data-lucide="bell"></i><span>Notifications</span></a></li>
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
                <h4>Approval Messages</h4>
                <p>Messages and remarks from the Principal regarding your requests</p>
            </div>
        </div>

        <?php if ($messages->num_rows > 0): ?>
            <div class="row g-4">
                <?php while ($msg = $messages->fetch_assoc()): ?>
                <div class="col-12">
                    <div class="message-card">
                        <div class="message-header">
                            <div>
                                <h5><i class="fas fa-<?php echo $msg['leave_type'] == 'OD' ? 'briefcase' : 'calendar-check'; ?> me-2" style="color: var(--secondary);"></i><?php echo $msg['leave_type']; ?> Request - <?php echo date('d M Y', strtotime($msg['from_date'])); ?></h5>
                                <p>Applied on: <?php echo date('d M Y, h:i A', strtotime($msg['applied_on'])); ?></p>
                            </div>
                            <span class="status-badge status-<?php echo $msg['status']; ?>"><?php echo $msg['status']; ?></span>
                        </div>
                        <div class="message-body">
                            <p style="color: #475569; font-size: 0.95rem; margin-bottom: 20px;"><strong>Your Reason:</strong> <?php echo htmlspecialchars($msg['reason']); ?></p>
                            
                            <div class="remark-box">
                                <div class="remark-content">
                                    <?php echo nl2br(htmlspecialchars($msg['admin_remarks'])); ?>
                                </div>
                                <div class="remark-author">
                                    &mdash; <?php echo $msg['admin_name'] ?? 'Principal/Admin'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i data-lucide="mail-open"></i>
                <h5>No Messages Available</h5>
                <p style="color: #94a3b8; font-size: 0.85rem;">You do not have any remarks or messages from the Principal yet.</p>
            </div>
        <?php endif; ?>
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


