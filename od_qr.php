<?php
require_once '../includes/db_config.php';

if (!isStaffLoggedIn()) {
    redirect('../index.php');
}

$staff_id = $_SESSION['staff_id'];
$staff = getStaffDetails($conn, $staff_id);
$notification_count = getNotificationCount($conn, $staff_id);

// Get the latest approved OD for this staff member
$sql = "SELECT * FROM leave_requests 
        WHERE staff_id = ? AND leave_type = 'OD' AND status = 'Approved'
        ORDER BY to_date DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $staff_id);
$stmt->execute();
$result = $stmt->get_result();
$latest_od = $result->fetch_assoc();

$qr_data = "";
if ($latest_od) {
    $qr_data = urlencode("OD PASS | Name: " . $staff['name'] . " | Dept: " . $staff['department'] . " | Date: " . formatDate($latest_od['from_date']) . " to " . formatDate($latest_od['to_date']) . " | Status: APPROVED");
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
    <title>My OD QR Pass | Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .qr-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: var(--shadow-deep);
            border: 1px solid #cbd5e1;
            max-width: 500px;
            margin: 0 auto;
        }
        .qr-image-wrapper {
            background: #f8fafc;
            padding: 20px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 25px;
            border: 2px dashed #cbd5e1;
        }
        .pass-details h5 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 5px;
        }
        .pass-details p {
            color: #64748b;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        .badge-approved {
            background: rgba(16, 185, 129, 0.15); 
            color: #059669;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-block;
            margin-bottom: 20px;
        }
        .no-od {
            padding: 50px 20px;
            text-align: center;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
        }
        .no-od i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
        <i data-lucide="menu"></i>
    </button>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/images/logo.png" alt="College Logo" style="width: 85px; height: auto; margin-bottom: 15px;">
            <h5>Dept of <?php echo htmlspecialchars($staff['department']); ?></h5>
            <p>Leave & OD System</p>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home"></i><span>Dashboard</span></a></li>
            <li class="nav-item"><a href="apply_leave.php" class="nav-link"><i data-lucide="calendar-plus"></i><span>Apply Leave</span></a></li>
            <li class="nav-item"><a href="leave_history.php" class="nav-link"><i data-lucide="history"></i><span>Leave History</span></a></li>
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
                    <p>Staff</p>
                </div>
            </div>
            <a href="../logout.php" class="nav-link mt-3" style="padding: 10px 0;"><i data-lucide="log-out"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h4>Digital OD Pass</h4>
                <p>Scan this QR code for gate verification</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="notifications.php" class="position-relative text-dark text-decoration-none">
                    <i data-lucide="bell fs-5"></i>
                    <?php if($notification_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        <?php echo $notification_count; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <div class="container-fluid py-4">
            <?php if ($latest_od): ?>
                <div class="qr-card">
                    <div class="badge-approved"><i data-lucide="check-circle me-2"></i>APPROVED OD PASS</div>
                    
                    <div class="qr-image-wrapper">
                        <!-- Using free QR Server API -->
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo $qr_data; ?>" alt="OD QR Code" class="img-fluid" style="width: 250px; height: 250px;">
                    </div>

                    <div class="pass-details">
                        <table class="table table-bordered text-start mt-4 mb-0" style="font-size: 0.95rem;">
                            <tbody>
                                <tr>
                                    <th class="bg-light text-muted" style="width: 40%; font-weight: 600;">Staff Name</th>
                                    <td class="fw-bold"><?php echo htmlspecialchars($staff['name']); ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted" style="font-weight: 600;">Department</th>
                                    <td class="fw-bold"><?php echo htmlspecialchars($staff['department']); ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted" style="font-weight: 600;">Valid From</th>
                                    <td class="fw-bold text-success"><?php echo formatDate($latest_od['from_date']); ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted" style="font-weight: 600;">Valid To</th>
                                    <td class="fw-bold text-danger"><?php echo formatDate($latest_od['to_date']); ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted" style="font-weight: 600;">Reason</th>
                                    <td class="fw-bold"><?php echo htmlspecialchars($latest_od['reason']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-od">
                    <i data-lucide="qr-code"></i>
                    <h4>No Active OD Pass</h4>
                    <p class="text-muted">You do not have any recently approved On Duty (OD) requests to generate a QR pass for.</p>
                    <a href="apply_leave.php?type=od" class="btn btn-primary mt-3"><i data-lucide="plus me-2"></i>Apply for OD</a>
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


