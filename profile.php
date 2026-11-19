<?php
require_once '../includes/db_config.php';

if (!isStaffLoggedIn()) {
    redirect('../index.php');
}

$staff_id = $_SESSION['staff_id'];
$staff = getStaffDetails($conn, $staff_id);
$balance = getLeaveBalance($conn, $staff_id);
$notification_count = getNotificationCount($conn, $staff_id);

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = sanitize($conn, $_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];

    if (!empty($new_password)) {
        if (password_verify($current_password, $staff['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE staff SET phone = ?, password = ? WHERE staff_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $phone, $hashed, $staff_id);
        } else {
            $message = 'Current password is incorrect!';
            $message_type = 'danger';
        }
    } else {
        $sql = "UPDATE staff SET phone = ? WHERE staff_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $phone, $staff_id);
    }

    if (empty($message)) {
        if ($stmt->execute()) {
            $message = 'Profile updated successfully!';
            $staff = getStaffDetails($conn, $staff_id);
        } else {
            $message = 'Error updating profile!';
            $message_type = 'danger';
        }
    }
}

// Get leave statistics for chart
$chart_sql = "SELECT 
    MONTHNAME(from_date) as month,
    COUNT(*) as count,
    SUM(CASE WHEN leave_type = 'CL' THEN 1 ELSE 0 END) as cl_count,
    SUM(CASE WHEN leave_type = 'OD' THEN 1 ELSE 0 END) as od_count
    FROM leave_requests 
    WHERE staff_id = ? AND YEAR(from_date) = YEAR(CURDATE())
    GROUP BY MONTH(from_date), MONTHNAME(from_date)
    ORDER BY MONTH(from_date)";
$chart_stmt = $conn->prepare($chart_sql);
$chart_stmt->bind_param("i", $staff_id);
$chart_stmt->execute();
$chart_data = $chart_stmt->get_result();

$months = [];
$cl_data = [];
$od_data = [];
while ($row = $chart_data->fetch_assoc()) {
    $months[] = $row['month'];
    $cl_data[] = $row['cl_count'];
    $od_data[] = $row['od_count'];
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
    <title>My Profile | Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <li class="nav-item"><a href="notifications.php" class="nav-link"><i data-lucide="bell"></i><span>Notifications</span></a></li>
            <li class="nav-item"><a href="profile.php" class="nav-link active"><i data-lucide="user"></i><span>My Profile</span></a></li>
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
                <h4>My Profile</h4>
                <p>Manage your account information</p>
            </div>
        </div>

        <?php if ($message): ?>
            <?php echo showMessage($message, $message_type); ?>
        <?php endif; ?>

        <div class="row g-4" id="bento-grid">
            <div class="col-lg-4">
                <div class="profile-card bento-card">
                    <div class="profile-header text-center">
                        <div class="profile-picture-container" style="position: relative; width: 120px; height: 120px; margin: 0 auto 15px auto;">
                            <?php if (!empty($staff['profile_image']) && $staff['profile_image'] !== 'default.png'): ?>
                                <img src="../assets/images/profiles/<?php echo $staff['profile_image']; ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 4px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <?php else: ?>
                                <div class="profile-avatar" style="width: 100%; height: 100%; font-size: 2.5rem; margin: 0;"><?php echo strtoupper(substr($staff['name'], 0, 1)); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="profile-photo-actions mb-3 d-flex flex-wrap justify-content-center gap-2">
                            <form action="../upload_profile_pic.php" method="POST" enctype="multipart/form-data" id="galleryForm" style="display: none;">
                                <input type="file" name="profile_image" id="galleryInput" accept="image/*" onchange="document.getElementById('galleryForm').submit();">
                            </form>
                            <form action="../upload_profile_pic.php" method="POST" id="cameraForm" style="display: none;">
    <input type="hidden" name="camera_image" id="cameraImageData">
</form>

<!-- Camera Modal -->
<div id="cameraModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center; flex-direction: column;">
    <div style="background: white; padding: 20px; border-radius: 12px; width: 90%; max-width: 500px; text-align: center;">
        <h5 style="margin-bottom: 15px; color: var(--primary);">Take a Profile Photo</h5>
        <video id="cameraVideo" style="width: 100%; border-radius: 8px; background: #000;" autoplay playsinline></video>
        <canvas id="cameraCanvas" style="display: none;"></canvas>
        <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
            <button onclick="closeCamera()" class="btn btn-secondary" style="background: #e2e8f0; color: #334155; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600;">Cancel</button>
            <button onclick="takeSnapshot()" class="btn btn-primary" style="background: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600;">Capture & Save</button>
        </div>
    </div>
</div>

<script>
let videoStream = null;
function openCamera() {
    document.getElementById('cameraModal').style.display = 'flex';
    navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => {
            videoStream = stream;
            document.getElementById('cameraVideo').srcObject = stream;
        })
        .catch(err => {
            alert("Camera access denied or unavailable.");
            closeCamera();
        });
}
function closeCamera() {
    document.getElementById('cameraModal').style.display = 'none';
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
}
function takeSnapshot() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    const dataUrl = canvas.toDataURL('image/png');
    document.getElementById('cameraImageData').value = dataUrl;
    closeCamera();
    document.getElementById('cameraForm').submit();
}
</script>
                            
                            <button type="button" class="btn btn-sm btn-outline-primary" style="border-radius: 8px; font-weight: 600;" onclick="document.getElementById('galleryInput').click();" title="Choose from Gallery">
                                <i data-lucide="image me-1"></i> Gallery
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;" onclick="openCamera();" title="Take Photo">
                                <i data-lucide="camera me-1"></i> Camera
                            </button>
                            <?php if (!empty($staff['profile_image']) && $staff['profile_image'] !== 'default.png'): ?>
                            <form action="../upload_profile_pic.php" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 8px; font-weight: 600;" title="Remove Picture" onclick="return confirm('Remove profile picture?');">
                                    <i data-lucide="trash me-1"></i> Remove
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        
                        <h3><?php echo $staff['name']; ?></h3>
                        <p><?php echo $staff['designation']; ?> | <?php echo $staff['department']; ?></p>
                    </div>
                    <div class="profile-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <h6><i data-lucide="envelope"></i>Email</h6>
                                <p><?php echo $staff['email']; ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i data-lucide="phone"></i>Phone</h6>
                                <p><?php echo $staff['phone'] ?? 'Not set'; ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i data-lucide="book"></i>Subject</h6>
                                <p><?php echo $staff['subject']; ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i data-lucide="building"></i>Department</h6>
                                <p><?php echo $staff['department']; ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i data-lucide="calendar"></i>Joined</h6>
                                <p><?php echo formatDate($staff['join_date']); ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i data-lucide="id-card"></i>ID</h6>
                                <p>#<?php echo str_pad($staff['staff_id'], 4, '0', STR_PAD_LEFT); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="profile-card bento-card mb-4">
                    <div class="p-4 border-bottom">
                        <h5><i class="fas fa-edit me-2" style="color: var(--secondary);"></i>Edit Profile</h5>
                    </div>
                    <div class="p-4">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?php echo $staff['name']; ?>" disabled>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo $staff['email']; ?>" disabled>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo $staff['phone']; ?>" placeholder="Enter phone number">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" value="<?php echo $staff['department']; ?>" disabled>
                                </div>
                            </div>
                            <hr class="my-4">
                            <h6 class="mb-3" style="color: var(--dark); font-weight: 600;"><i class="fas fa-lock me-2" style="color: var(--accent);"></i>Change Password</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" placeholder="Enter current password">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" placeholder="Enter new password">
                                </div>
                            </div>
                            <button type="submit" class="btn-update"><i data-lucide="save me-2"></i>Update Profile</button>
                        </form>
                    </div>
                </div>

                <!-- Leave Statistics Chart -->
                <div class="chart-card bento-card p-4">
                    <h5 class="mb-4"><i class="fas fa-chart-bar me-2" style="color: var(--secondary);"></i>Leave Statistics (This Year)</h5>
                    <div style="max-width: 500px; height: 250px; margin: 0 auto;">
                        <canvas id="leaveChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctx = document.getElementById('leaveChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Casual Leave',
                    data: <?php echo json_encode($cl_data); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderColor: '#10b981',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }, {
                    label: 'On Duty',
                    data: <?php echo json_encode($od_data); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    </script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
<script src="../assets/js/specular.js"></script>
<script src="../assets/js/star_border.js"></script>
<script src="../assets/js/magic_bento.js"></script>
</body>
</html>



