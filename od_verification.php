<?php
require_once '../includes/db_config.php';

if (!isAdminLoggedIn()) {
    redirect('../index.php');
}

$admin_id = $_SESSION['admin_id'];
$admin = getAdminDetails($conn, $admin_id);

$verification_result = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_od'])) {
    $qr_code_hash = sanitize($conn, $_POST['qr_code']);
    
    // In a real app, this would verify the hash against the leave_requests table
    // For this prototype, we'll just simulate a successful/failed lookup
    $sql = "SELECT lr.*, t.name as staff_name, t.department 
            FROM leave_requests lr
            JOIN staff t ON lr.staff_id = t.staff_id
            WHERE lr.leave_type = 'OD' AND lr.status = 'Approved' 
            ORDER BY lr.leave_id DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0 && strlen($qr_code_hash) > 5) {
        $verification_result = $result->fetch_assoc();
    } else {
        $verification_result = false;
    }
}

// Get recent verified ODs
$recent_sql = "SELECT lr.*, t.name as staff_name, t.department 
            FROM leave_requests lr
            JOIN staff t ON lr.staff_id = t.staff_id
            WHERE lr.leave_type = 'OD' AND lr.status = 'Approved' 
            ORDER BY lr.updated_at DESC LIMIT 5";
$recent_ods = $conn->query($recent_sql);
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
    <title>OD Verification | Admin Portal</title>
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
            <h5>Dept of Computer Science</h5>
            <p>Admin Portal</p>
        </div>
        <ul class="nav-menu">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i data-lucide="home"></i><span>Dashboard</span></a></li>
            <li class="nav-item"><a href="pending_requests.php" class="nav-link"><i data-lucide="clock"></i><span>Pending Requests</span></a></li>
            <li class="nav-item"><a href="leave_calendar.php" class="nav-link"><i data-lucide="calendar"></i><span>Leave Calendar</span></a></li>
            <li class="nav-item"><a href="analytics.php" class="nav-link"><i data-lucide="line-chart"></i><span>Analytics</span></a></li>
            <li class="nav-item"><a href="staff.php" class="nav-link"><i data-lucide="users"></i><span>Staffs</span></a></li>
            
            <li class="nav-item"><a href="reports.php" class="nav-link"><i data-lucide="file-text"></i><span>Reports</span></a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h4>OD QR Verification</h4>
                <p>Verify staff On-Duty validity instantly</p>
            </div>
            <span class="admin-badge"><i data-lucide="shield-alt me-2"></i><?php echo $admin['role']; ?></span>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="glass-card">
                    <h5 style="font-weight: 700; margin-bottom: 25px;"><i data-lucide="camera me-2 text-primary"></i>Scan QR Code</h5>
                    
                    <div class="scanner-container" id="scannerView">
                        <div class="scanner-line" id="scannerLine"></div>
                        <i class="fas fa-qrcode" style="font-size: 80px; color: #334155; opacity: 0.5;"></i>
                    </div>

                    <button class="btn-scan" onclick="simulateScan()">
                        <i data-lucide="qrcode me-2"></i>Activate Camera
                    </button>

                    <div class="mt-4">
                        <p class="text-center text-muted fw-bold mb-3">OR ENTER VERIFICATION ID</p>
                        <form method="POST" action="">
                            <div class="input-group">
                                <input type="text" name="qr_code" class="form-control form-control-modern" placeholder="e.g. OD-783291" required>
                                <button type="submit" name="verify_od" class="btn btn-primary" style="border-radius: 0 12px 12px 0; padding: 0 25px; font-weight: 600;">Verify</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($verification_result !== null): ?>
                    <?php if ($verification_result !== false): ?>
                        <div class="result-card">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-check-circle text-success" style="font-size: 32px; margin-right: 15px;"></i>
                                <div>
                                    <h5 class="mb-0 fw-bold text-success">Valid OD Record</h5>
                                    <p class="text-muted mb-0" style="font-size: 0.85rem;">Verified on <?php echo date('d M Y, h:i A'); ?></p>
                                </div>
                            </div>
                            <hr style="opacity: 0.1;">
                            <div class="row mt-3">
                                <div class="col-6 mb-3">
                                    <span class="text-muted" style="font-size: 0.8rem; font-weight: 600;">STAFF NAME</span>
                                    <div class="fw-bold"><?php echo $verification_result['staff_name']; ?></div>
                                </div>
                                <div class="col-6 mb-3">
                                    <span class="text-muted" style="font-size: 0.8rem; font-weight: 600;">DEPARTMENT</span>
                                    <div class="fw-bold"><?php echo $verification_result['department']; ?></div>
                                </div>
                                <div class="col-12 mb-3">
                                    <span class="text-muted" style="font-size: 0.8rem; font-weight: 600;">EVENT/REASON</span>
                                    <div class="fw-bold"><?php echo $verification_result['reason']; ?></div>
                                </div>
                                <div class="col-12">
                                    <span class="text-muted" style="font-size: 0.8rem; font-weight: 600;">VALIDITY DATE</span>
                                    <div class="fw-bold text-primary"><?php echo date('d M Y', strtotime($verification_result['from_date'])); ?> - <?php echo date('d M Y', strtotime($verification_result['to_date'])); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="result-card invalid">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-times-circle text-danger" style="font-size: 32px; margin-right: 15px;"></i>
                                <div>
                                    <h5 class="mb-0 fw-bold text-danger">Invalid or Expired QR Code</h5>
                                    <p class="text-muted mb-0" style="font-size: 0.85rem;">No matching approved OD record found.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="col-lg-6">
                <div class="glass-card h-100">
                    <h5 style="font-weight: 700; margin-bottom: 25px;"><i data-lucide="history me-2 text-secondary"></i>Recently Verified ODs</h5>
                    <ul class="recent-list">
                        <?php while($row = $recent_ods->fetch_assoc()): ?>
                            <li class="recent-item">
                                <div class="recent-icon"><i data-lucide="check"></i></div>
                                <div>
                                    <div style="font-weight: 600; color: #1e293b;"><?php echo $row['staff_name']; ?></div>
                                    <div style="font-size: 0.8rem; color: #64748b;">
                                        <?php echo date('d M', strtotime($row['from_date'])); ?> - <?php echo $row['reason']; ?>
                                    </div>
                                </div>
                                <span class="ms-auto badge bg-light text-dark border">ID: OD-<?php echo $row['leave_id']; ?></span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
        function simulateScan() {
            const container = document.getElementById('scannerView');
            const line = document.getElementById('scannerLine');
            const icon = container.querySelector('i');
            
            icon.style.color = '#10b981';
            icon.style.opacity = '1';
            line.style.display = 'block';
            
            // In a real app, this would open WebRTC camera stream
            setTimeout(() => {
                alert("Camera activated! (Simulation mode). Please use the manual entry for demo.");
                line.style.display = 'none';
                icon.style.color = '#334155';
                icon.style.opacity = '0.5';
            }, 2000);
        }
    </script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
<script src="../assets/js/specular.js"></script>
<script src="../assets/js/star_border.js"></script>
<script src="../assets/js/magic_bento.js"></script>
</body>
</html>


