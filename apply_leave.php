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
    $leave_type = sanitize($conn, $_POST['leave_type']);
    $from_date = sanitize($conn, $_POST['from_date']);
    $to_date = sanitize($conn, $_POST['to_date']);
    $period_no = '';
    $reason = sanitize($conn, $_POST['reason'] ?? '');
    $work_arrangement = sanitize($conn, $_POST['work_arrangement'] ?? '');
    $contact_info = '';
    $substitute_id = NULL;

    // Validate dates
    if (strtotime($from_date) > strtotime($to_date)) {
        $message = 'From date cannot be after To date!';
        $message_type = 'danger';
    } else {
        // Check CL balance
        if ($leave_type == 'CL') {
            $days = (strtotime($to_date) - strtotime($from_date)) / (60 * 60 * 24) + 1;
            if ($days > $balance['cl_balance']) {
                $message = 'Insufficient CL balance! You have ' . $balance['cl_balance'] . ' days remaining.';
                $message_type = 'danger';
            }
        }

        if (empty($message)) {
            $document_path = '';

            // Insert leave request
            $sql = "INSERT INTO leave_requests (staff_id, leave_type, from_date, to_date, period_no, reason, work_arrangement, document_path, contact_info, substitute_staff_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssssi", $staff_id, $leave_type, $from_date, $to_date, $period_no, $reason, $work_arrangement, $document_path, $contact_info, $substitute_id);

            if ($stmt->execute()) {
                $leave_id = $stmt->insert_id;

                // Add notification
                $notif_sql = "INSERT INTO notifications (staff_id, title, message, type) VALUES (?, ?, ?, ?)";
                $notif_stmt = $conn->prepare($notif_sql);
                $title = $leave_type . ' Request Submitted';
                $msg = 'Your ' . $leave_type . ' request from ' . formatDate($from_date) . ' to ' . formatDate($to_date) . ' has been submitted for approval.';
                $notif_type = ($leave_type === 'OD') ? 'OD' : 'Leave';
                $notif_stmt->bind_param("isss", $staff_id, $title, $msg, $notif_type);
                $notif_stmt->execute();

                $message = 'Leave request submitted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error submitting request. Please try again.';
                $message_type = 'danger';
            }
        }
    }
}

$default_type = isset($_GET['type']) ? $_GET['type'] : 'cl';
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
    <title>Apply Leave | Staff Portal</title>
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
            <li class="nav-item"><a href="apply_leave.php" class="nav-link active"><i data-lucide="calendar-plus"></i><span>Apply Leave</span></a></li>
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
                    <p><?php echo $staff['department']; ?></p>
                </div>
            </div>
            <a href="../logout.php" class="nav-link mt-3" style="padding: 10px 0;"><i data-lucide="log-out"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h4>Apply for Leave / OD</h4>
                <p>Submit your leave or on-duty request</p>
            </div>
        </div>

        <?php if ($message): ?>
            <?php echo showMessage($message, $message_type); ?>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="form-card">
                    <div class="form-header">
                        <h4><i data-lucide="file-alt me-2"></i>New Leave Application</h4>
                        <p>Fill in the details below to submit your request</p>
                    </div>
                    <div class="form-body">
                        <div class="balance-info">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6>Your Current CL Balance</h6>
                                    <h3><?php echo $balance['cl_balance'] ?? 12; ?> <small style="font-size: 1rem; color: #94a3b8;">days</small></h3>
                                    <p>Academic Year <?php echo date('Y'); ?></p>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <h6>OD Records This Year</h6>
                                    <h3><?php echo $balance['od_count'] ?? 0; ?> <small style="font-size: 1rem; color: #94a3b8;">events</small></h3>
                                    <p>Total official duty attended</p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="" id="leaveForm" enctype="multipart/form-data">
                            <input type="hidden" name="leave_type" id="leave_type" value="<?php echo $default_type == 'od' ? 'OD' : 'CL'; ?>">

                            <div class="leave-type-selector">
                                <div class="leave-type-option <?php echo $default_type == 'cl' ? 'active' : ''; ?>" onclick="selectLeaveType('CL', this)">
                                    <i data-lucide="calendar-check-2"></i>
                                    <h5>Casual Leave (CL)</h5>
                                </div>
                                <div class="leave-type-option <?php echo $default_type == 'el' ? 'active' : ''; ?>" onclick="selectLeaveType('EL', this)">
                                    <i data-lucide="calendar-plus"></i>
                                    <h5>Earned Leave (EL)</h5>
                                </div>
                                <div class="leave-type-option <?php echo $default_type == 'ml' ? 'active' : ''; ?>" onclick="selectLeaveType('ML', this)">
                                    <i data-lucide="notes-medical"></i>
                                    <h5>Medical Leave (ML)</h5>
                                </div>
                                <div class="leave-type-option <?php echo $default_type == 'od' ? 'active' : ''; ?>" onclick="selectLeaveType('OD', this)">
                                    <i data-lucide="briefcase"></i>
                                    <h5>On Duty (OD)</h5>
                                </div>
                                <div class="leave-type-option <?php echo $default_type == 'lpa' ? 'active' : ''; ?>" onclick="selectLeaveType('LPA', this)">
                                    <i data-lucide="user-clock"></i>
                                    <h5>LPA Leave</h5>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <label class="form-label">From Date</label>
                                    <input type="date" class="form-control" name="from_date" id="from_date" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-5 mb-3">
                                    <label class="form-label">To Date</label>
                                    <input type="date" class="form-control" name="to_date" id="to_date" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                    <div class="w-100 text-center p-2 rounded" style="background: rgba(44, 82, 130, 0.05); border: 2px solid #e2e8f0; height: 50px; display: flex; flex-direction: column; justify-content: center;">
                                        <span id="days_count" style="font-weight: 700; color: var(--secondary); font-size: 1.1rem;">0</span>
                                        <span style="font-size: 0.65rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Days</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <textarea class="form-control" name="reason" rows="2" placeholder="Please provide a detailed reason for your request..." required></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Work Arrangement</label>
                                <textarea class="form-control" name="work_arrangement" rows="3" placeholder="Specify who will cover your classes/duties during this absence (e.g., I B.Sc Class taken by Mr. John)..." required></textarea>
                            </div>

                            <button type="submit" class="btn-submit">
                                <i data-lucide="paper-plane me-2"></i>Submit Request
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectLeaveType(type, element) {
            document.getElementById('leave_type').value = type;
            document.querySelectorAll('.leave-type-option').forEach(opt => opt.classList.remove('active'));
            element.classList.add('active');
        }


        // Auto-calculate days
        const fromDateInput = document.getElementById('from_date');
        const toDateInput = document.getElementById('to_date');
        const daysCountSpan = document.getElementById('days_count');

        function calculateDays() {
            if (fromDateInput.value && toDateInput.value) {
                const start = new Date(fromDateInput.value);
                const end = new Date(toDateInput.value);
                const diffTime = end - start;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                
                if (diffDays > 0) {
                    daysCountSpan.textContent = diffDays;
                } else {
                    daysCountSpan.textContent = '0';
                }
            } else {
                daysCountSpan.textContent = '0';
            }
        }

        fromDateInput.addEventListener('change', calculateDays);
        toDateInput.addEventListener('change', calculateDays);


    </script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
<script src="../assets/js/specular.js"></script>
<script src="../assets/js/star_border.js"></script>
<script src="../assets/js/magic_bento.js"></script>
</body>
</html>



