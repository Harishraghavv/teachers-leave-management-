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

$status_condition = $is_principal ? "'HOD Approved'" : "'Pending'";

// Get all pending requests
$sql = "SELECT lr.*, t.name as staff_name, t.department, t.subject
        FROM leave_requests lr
        JOIN staff t ON lr.staff_id = t.staff_id
        WHERE lr.status = $status_condition $dept_sql_condition
        ORDER BY lr.applied_on DESC";
$pending_requests = $conn->query($sql);
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
    <title>Pending Requests | Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            <li class="nav-item"><a href="pending_requests.php" class="nav-link active"><i data-lucide="clock"></i><span>Pending Requests</span></a></li>
            <li class="nav-item"><a href="leave_calendar.php" class="nav-link"><i data-lucide="calendar"></i><span>Leave Calendar</span></a></li>
            <li class="nav-item"><a href="analytics.php" class="nav-link"><i data-lucide="line-chart"></i><span>Analytics</span></a></li>
            <li class="nav-item"><a href="staff.php" class="nav-link"><i data-lucide="users"></i><span>Staffs</span></a></li>
            
            <li class="nav-item"><a href="reports.php" class="nav-link"><i data-lucide="file-text"></i><span>Reports</span></a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h4>Pending Requests</h4>
                <p>Review and process staff leave applications</p>
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

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 15px; border: none; background: #d1fae5; color: #065f46;">
                <i data-lucide="check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($pending_requests->num_rows > 0): ?>
            <div class="request-grid">
                <?php while($req = $pending_requests->fetch_assoc()): ?>
                    <div class="req-card type-<?php echo strtolower($req['leave_type']); ?>">
                        <div class="req-header">
                            <div class="staff-info">
                                <div class="staff-avatar"><?php echo strtoupper(substr($req['staff_name'], 0, 1)); ?></div>
                                <div class="staff-details">
                                    <h5><?php echo $req['staff_name']; ?></h5>
                                    <p><?php echo $req['department']; ?></p>
                                </div>
                            </div>
                            <span class="req-type-badge type-<?php echo strtolower($req['leave_type']); ?>"><?php echo $req['leave_type']; ?></span>
                        </div>
                        
                        <div class="req-body">
                            <div class="req-row">
                                <span class="req-label">Date Range</span>
                                <span class="req-val"><?php echo date('d M Y', strtotime($req['from_date'])); ?> <br>to<br> <?php echo date('d M Y', strtotime($req['to_date'])); ?></span>
                            </div>
                            <hr style="opacity: 0.1; margin: 10px 0;">
                            <div class="req-row">
                                <span class="req-label">Reason</span>
                                <span class="req-val text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($req['reason']); ?>">
                                    <?php echo htmlspecialchars($req['reason']); ?>
                                </span>
                            </div>
                            <hr style="opacity: 0.1; margin: 10px 0;">
                            <div class="req-row">
                                <span class="req-label">Work Arr.</span>
                                <span class="req-val text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($req['work_arrangement']); ?>">
                                    <?php echo htmlspecialchars($req['work_arrangement']) ?: 'N/A'; ?>
                                </span>
                            </div>
                            <hr style="opacity: 0.1; margin: 10px 0;">
                            <div class="req-row">
                                <span class="req-label">Applied On</span>
                                <span class="req-val"><?php echo date('d M Y, h:i A', strtotime($req['applied_on'])); ?></span>
                            </div>
                        </div>

                        <div class="req-actions">
                            <button onclick="openActionModal(<?php echo $req['leave_id']; ?>, 'approve')" class="btn-modern btn-approve">
                                <i data-lucide="check"></i> Approve
                            </button>
                            <button onclick="openActionModal(<?php echo $req['leave_id']; ?>, 'reject')" class="btn-modern btn-reject">
                                <i data-lucide="times"></i> Reject
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="glass-card text-center" style="padding: 60px 20px;">
                <div style="width: 80px; height: 80px; background: #f8fafc; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                    <i class="fas fa-inbox" style="font-size: 32px; color: #cbd5e1;"></i>
                </div>
                <h4 style="font-weight: 700; color: #334155;">No Pending Requests</h4>
                <p style="color: #64748b;">You're all caught up! There are no leave or OD applications awaiting approval.</p>
            </div>
        <?php endif; ?>

    </main>

    <!-- Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden;">
                <form method="POST" action="approve_request.php" id="actionForm">
                    <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 20px 25px;">
                        <h5 class="modal-title" id="actionModalTitle" style="font-weight: 600;">Process Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding: 25px;">
                        <input type="hidden" name="id" id="actionLeaveId">
                        <input type="hidden" name="action" id="actionType">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted" style="font-size: 0.9rem; font-weight: 500;">Admin Remarks (Optional)</label>
                            <textarea class="form-control" name="admin_remarks" rows="3" placeholder="Enter any remarks or instructions for the staff member..." style="border-radius: 12px; resize: none;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: none; padding: 20px 25px; background: #f8fafc;">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 500;">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="actionSubmitBtn" style="border-radius: 10px; font-weight: 600; padding: 8px 25px;">Confirm Action</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openActionModal(id, action) {
            document.getElementById('actionLeaveId').value = id;
            document.getElementById('actionType').value = action;
            
            const title = action === 'approve' ? 'Approve Leave Request' : 'Reject Leave Request';
            const btnClass = action === 'approve' ? 'btn-success' : 'btn-danger';
            const btnText = action === 'approve' ? 'Approve' : 'Reject';
            
            document.getElementById('actionModalTitle').innerText = title;
            const submitBtn = document.getElementById('actionSubmitBtn');
            submitBtn.className = 'btn ' + btnClass;
            submitBtn.innerText = btnText;
            
            const modal = new bootstrap.Modal(document.getElementById('actionModal'));
            modal.show();
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


