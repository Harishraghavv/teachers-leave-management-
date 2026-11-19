<?php
require_once '../includes/db_config.php';

if (!isStaffLoggedIn()) {
    redirect('../index.php');
}

$staff_id = $_SESSION['staff_id'];
$staff = getStaffDetails($conn, $staff_id);
$balance = getLeaveBalance($conn, $staff_id);
$notification_count = getNotificationCount($conn, $staff_id);

// Get recent leave requests
$sql = "SELECT * FROM leave_requests WHERE staff_id = ? ORDER BY applied_on DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$recent_leaves = $stmt->get_result();

// Get upcoming leaves
$sql = "SELECT * FROM leave_requests WHERE staff_id = ? AND from_date >= CURDATE() AND status = 'Approved' ORDER BY from_date LIMIT 3";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$upcoming_leaves = $stmt->get_result();

// Get notifications
$sql = "SELECT * FROM notifications WHERE staff_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$notifications = $stmt->get_result();

// Get leave statistics
$sql = "SELECT 
    COUNT(*) as total_leaves,
    SUM(CASE WHEN leave_type = 'CL' THEN 1 ELSE 0 END) as cl_count,
    SUM(CASE WHEN leave_type = 'OD' THEN 1 ELSE 0 END) as od_count,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM leave_requests WHERE staff_id = ? AND YEAR(from_date) = YEAR(CURDATE())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
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
    <title>Staff Dashboard | Leave & OD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Matrix Canvas -->
    <canvas id="pixelBlastCanvas" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.15;"></canvas>
    <!-- Sidebar -->
    
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
            <li class="nav-item"><a href="dashboard.php" class="nav-link active"><i data-lucide="home"></i><span>Dashboard</span></a></li>
            <li class="nav-item"><a href="apply_leave.php" class="nav-link"><i data-lucide="calendar-plus"></i><span>Apply Leave</span></a></li>
            <li class="nav-item"><a href="leave_history.php" class="nav-link"><i data-lucide="history"></i><span>Leave History</span></a></li>
            <li class="nav-item"><a href="calendar.php" class="nav-link"><i data-lucide="calendar"></i><span>Daily Calendar</span></a></li>
            <li class="nav-item"><a href="approval_messages.php" class="nav-link"><i data-lucide="mail-open"></i><span>Approval Messages</span></a></li>
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

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h4>Welcome back, <?php echo htmlspecialchars($staff['name']); ?>! 👋</h4>
                <p>Here's what's happening with your leave status</p>
            </div>
            <div class="top-actions">
                <button class="notification-btn" onclick="location.href='notifications.php'">
                    <i data-lucide="bell"></i>
                    <?php if ($notification_count > 0): ?>
                    <span class="badge"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['msg'])): ?>
            <div class="alert alert-<?php echo $_SESSION['msg_type']; ?> alert-dismissible fade show mb-4" role="alert">
                <?php echo $_SESSION['msg']; unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <!-- Stats Row -->
        <div id="bento-grid"><div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="balance-card bento-card h-100 d-flex flex-column justify-content-center text-center p-3">
                    <h6 class="text-uppercase mb-1" style="font-size: 0.8rem;">CL Remaining</h6>
                    <h3 class="fw-bold mb-1" style="font-size: 2.5rem;"><?php echo $balance['cl_balance'] ?? 12; ?></h3>
                    <p class="mb-0" style="font-size: 0.75rem;">For <?php echo date('Y'); ?></p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card bento-card h-100 p-3 d-flex flex-column justify-content-center align-items-center text-center" style="gap: 5px;">
                    <div class="stat-icon cl mb-1 mx-0"><i data-lucide="calendar-check-2"></i></div>
                    <p class="stat-label mb-0" style="font-size: 0.8rem;">CL Used</p>
                    <h3 class="stat-value mb-0" style="font-size: 1.8rem;"><?php echo $stats['cl_count'] ?? 0; ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card bento-card h-100 p-3 d-flex flex-column justify-content-center align-items-center text-center" style="gap: 5px;">
                    <div class="stat-icon od mb-1 mx-0"><i data-lucide="briefcase"></i></div>
                    <p class="stat-label mb-0" style="font-size: 0.8rem;">OD Records</p>
                    <h3 class="stat-value mb-0" style="font-size: 1.8rem;"><?php echo $stats['od_count'] ?? 0; ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card bento-card h-100 p-3 d-flex flex-column justify-content-center align-items-center text-center" style="gap: 5px;">
                    <p class="stat-label mb-2 w-100" style="font-size: 0.8rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">Requests</p>
                    <div class="d-flex justify-content-around w-100">
                        <div>
                            <div class="text-warning"><i data-lucide="clock"></i></div>
                            <h6 class="fw-bold mb-0 mt-1"><?php echo $stats['pending_count'] ?? 0; ?></h6>
                        </div>
                        <div>
                            <div class="text-success"><i data-lucide="check-circle"></i></div>
                            <h6 class="fw-bold mb-0 mt-1"><?php echo $stats['approved_count'] ?? 0; ?></h6>
                        </div>
                        <div>
                            <div class="text-danger"><i data-lucide="x-circle"></i></div>
                            <h6 class="fw-bold mb-0 mt-1"><?php echo $stats['rejected_count'] ?? 0; ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="section-card bento-card">
                    <div class="section-header">
                        <h5><i class="fas fa-bolt me-2" style="color: var(--accent);"></i>Quick Actions</h5>
                    </div>
                    <div class="section-body p-4">
                        <div class="quick-actions">
                            <a href="apply_leave.php?type=cl" class="quick-action-btn">
                                <i data-lucide="calendar-plus btn-cl"></i>
                                <span>Apply CL</span>
                            </a>
                            <a href="apply_leave.php?type=od" class="quick-action-btn">
                                <i data-lucide="briefcase btn-od"></i>
                                <span>Apply OD</span>
                            </a>
                            <a href="calendar.php" class="quick-action-btn">
                                <i data-lucide="calendar-alt text-danger"></i>
                                <span>Calendar</span>
                            </a>
                            <a href="leave_history.php" class="quick-action-btn">
                                <i data-lucide="history btn-history"></i>
                                <span>History</span>
                            </a>
                            <a href="reset_data.php?confirm=yes" class="quick-action-btn" onclick="return confirm('🚨 WARNING: This will permanently delete ALL your Leave Requests and Notifications. Do you want to reset everything?');">
                                <i data-lucide="trash-2" style="color: var(--danger);"></i>
                                <span style="color: var(--danger);">Reset Data</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Leaves -->
                <div class="section-card bento-card">
                    <div class="section-header">
                        <h5><i class="fas fa-calendar-alt me-2" style="color: var(--secondary);"></i>Upcoming Leaves</h5>
                    </div>
                    <div class="section-body">
                        <?php if ($upcoming_leaves->num_rows > 0): ?>
                            <?php while ($leave = $upcoming_leaves->fetch_assoc()): ?>
                            <div class="leave-item">
                                <div class="leave-icon <?php echo strtolower($leave['leave_type']); ?>">
                                    <i data-lucide="<?php echo $leave['leave_type'] == 'CL' ? 'calendar-check' : 'briefcase'; ?>"></i>
                                </div>
                                <div class="leave-details">
                                    <h6><?php echo $leave['leave_type']; ?> - <?php echo formatDate($leave['from_date']); ?></h6>
                                    <p><?php echo substr($leave['reason'], 0, 40); ?>...</p>
                                </div>
                                <span class="leave-status status-approved">Approved</span>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times" style="font-size: 2rem; color: #e2e8f0;"></i>
                                <p class="mt-2" style="color: #94a3b8; font-size: 0.85rem;">No upcoming leaves</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Leaves & Notifications -->
            <div class="col-lg-8">
                <div class="section-card bento-card">
                    <div class="section-header">
                        <h5><i class="fas fa-list-alt me-2" style="color: var(--success);"></i>Recent Leave Requests</h5>
                        <a href="leave_history.php">View All <i data-lucide="arrow-right ms-1"></i></a>
                    </div>
                    <div class="section-body">
                        <?php if ($recent_leaves->num_rows > 0): ?>
                            <?php while ($leave = $recent_leaves->fetch_assoc()): ?>
                            <div class="leave-item">
                                <div class="leave-icon <?php echo strtolower($leave['leave_type']); ?>">
                                    <i data-lucide="<?php echo $leave['leave_type'] == 'CL' ? 'calendar-check' : 'briefcase'; ?>"></i>
                                </div>
                                <div class="leave-details">
                                    <h6><?php echo $leave['leave_type']; ?> Request - <?php echo formatDate($leave['from_date']); ?> 
                                        <?php if ($leave['from_date'] != $leave['to_date']): ?>to <?php echo formatDate($leave['to_date']); endif; ?></h6>
                                    <p><?php echo $leave['reason']; ?></p>
                                </div>
                                <span class="leave-status status-<?php echo strtolower($leave['status']); ?>"><?php echo $leave['status']; ?></span>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox" style="font-size: 3rem; color: #e2e8f0;"></i>
                                <p class="mt-3" style="color: #94a3b8;">No leave requests yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="section-card bento-card">
                    <div class="section-header">
                        <h5><i class="fas fa-bell me-2" style="color: var(--warning);"></i>Notifications</h5>
                        <a href="notifications.php">View All <i data-lucide="arrow-right ms-1"></i></a>
                    </div>
                    <div class="section-body">
                        <?php if ($notifications->num_rows > 0): ?>
                            <?php while ($notif = $notifications->fetch_assoc()): ?>
                            <div class="notif-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                                <div class="notif-icon <?php echo strtolower($notif['type']); ?>">
                                    <i data-lucide="<?php 
                                        echo $notif['type'] == 'Leave' ? 'calendar-check' : 
                                            ($notif['type'] == 'OD' ? 'briefcase' : 'info-circle'); 
                                    ?>"></i>
                                </div>
                                <div class="notif-details">
                                    <h6><?php echo $notif['title']; ?></h6>
                                    <p><?php echo $notif['message']; ?></p>
                                </div>
                                <span class="notif-time"><?php echo date('M d', strtotime($notif['created_at'])); ?></span>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p style="color: #94a3b8; font-size: 0.85rem;">No notifications</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div></main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
    <script>
        // --- React Bits "Magic Bento" Hover Effect ---
        const bentoGrid = document.getElementById("bento-grid");
        if (bentoGrid) {
            bentoGrid.onmousemove = e => {
                for(const card of document.getElementsByClassName("bento-card")) {
                    const rect = card.getBoundingClientRect(),
                          x = e.clientX - rect.left,
                          y = e.clientY - rect.top;
                    card.style.setProperty("--mouse-x", `${x}px`);
                    card.style.setProperty("--mouse-y", `${y}px`);
                }
            }
        }
    </script>
    <script>
        // --- Matrix Digital Rain Background Animation ---
        (function() {
            const canvas = document.getElementById('pixelBlastCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let width, height;

            // Matrix characters
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$%^&*()_+-=[]{}|;:,.<>?/\\~';
            const charArray = chars.split('');
            const fontSize = 14;
            let columns, drops;

            function initMatrix() {
                width = canvas.width = window.innerWidth;
                height = canvas.height = window.innerHeight;
                columns = Math.floor(width / fontSize) + 1;
                drops = [];
                for(let i = 0; i < columns; i++) {
                    drops[i] = Math.random() * -100; 
                }
            }

            window.addEventListener('resize', initMatrix);
            initMatrix();

            function drawMatrix() {
                // Translucent fill to create trail effect
                ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
                ctx.fillRect(0, 0, width, height);

                // Matrix green color
                ctx.fillStyle = 'rgba(16, 185, 129, 0.8)';
                ctx.font = fontSize + 'px monospace';

                for(let i = 0; i < drops.length; i++) {
                    const text = charArray[Math.floor(Math.random() * charArray.length)];
                    ctx.fillText(text, i * fontSize, drops[i] * fontSize);

                    if(drops[i] * fontSize > height && Math.random() > 0.975) {
                        drops[i] = 0;
                    }
                    drops[i]++;
                }
            }
            
            // Limit framerate for classic matrix feel
            setInterval(drawMatrix, 40);
            
            // Interactive matrix burst on click
            document.body.addEventListener('click', (e) => {
                const col = Math.floor(e.clientX / fontSize);
                const spread = 8;
                for(let i = Math.max(0, col - spread); i < Math.min(columns, col + spread); i++) {
                    drops[i] = Math.floor((e.clientY / fontSize) - (Math.random() * 10));
                }
            });
        })();
    </script>
<script src="../assets/js/specular.js"></script>
<script src="../assets/js/star_border.js"></script>
<script src="../assets/js/magic_bento.js"></script>
</body>
</html>



