<?php
require_once '../includes/db_config.php';

if (!isStaffLoggedIn()) {
    redirect('../index.php');
}

$staff_id = $_SESSION['staff_id'];
$staff = getStaffDetails($conn, $staff_id);
$notification_count = getNotificationCount($conn, $staff_id);

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['leave_id'])) {
    $leave_id = (int)$_POST['leave_id'];
    $del_sql = "DELETE FROM leave_requests WHERE leave_id = ? AND staff_id = ?";
    $del_stmt = $conn->prepare($del_sql);
    $del_stmt->bind_param("ii", $leave_id, $staff_id);
    if ($del_stmt->execute()) {
        $success_msg = "Leave record deleted successfully.";
    } else {
        $error_msg = "Failed to delete leave record.";
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter
$filter_type = isset($_GET['type']) ? sanitize($conn, $_GET['type']) : '';
$filter_status = isset($_GET['status']) ? sanitize($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';
$date_range = isset($_GET['date_range']) ? sanitize($conn, $_GET['date_range']) : '';

$where = "WHERE lr.staff_id = ?";
$params = [$staff_id];
$types = "i";

if ($filter_type) {
    $where .= " AND lr.leave_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}
if ($filter_status) {
    $where .= " AND lr.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($date_range) {
    $today = date('Y-m-d');
    if ($date_range == 'today') {
        $where .= " AND lr.from_date <= ? AND lr.to_date >= ?";
        $params[] = $today;
        $params[] = $today;
        $types .= "ss";
    } elseif ($date_range == 'this_week') {
        $start = date('Y-m-d', strtotime('monday this week'));
        $end = date('Y-m-d', strtotime('sunday this week'));
        $where .= " AND lr.from_date >= ? AND lr.from_date <= ?";
        $params[] = $start;
        $params[] = $end;
        $types .= "ss";
    } elseif ($date_range == 'this_month') {
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        $where .= " AND lr.from_date >= ? AND lr.from_date <= ?";
        $params[] = $start;
        $params[] = $end;
        $types .= "ss";
    } elseif ($date_range == 'this_year') {
        $start = date('Y-01-01');
        $end = date('Y-12-31');
        $where .= " AND lr.from_date >= ? AND lr.from_date <= ?";
        $params[] = $start;
        $params[] = $end;
        $types .= "ss";
    }
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM leave_requests lr $where";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get leave requests
$sql = "SELECT lr.*, 
        a.name as approved_by_name,
        t.name as substitute_name
        FROM leave_requests lr
        LEFT JOIN admin a ON lr.approved_by = a.admin_id
        LEFT JOIN staff t ON lr.substitute_staff_id = t.staff_id
        $where
        ORDER BY lr.applied_on DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$types .= "ii";
$params[] = $limit;
$params[] = $offset;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$leaves = $stmt->get_result();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN leave_type = 'CL' THEN 1 ELSE 0 END) as cl_total,
    SUM(CASE WHEN leave_type = 'OD' THEN 1 ELSE 0 END) as od_total,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM leave_requests WHERE staff_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $staff_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
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
    <title>Leave History | Staff Portal</title>
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
            <li class="nav-item"><a href="leave_history.php" class="nav-link active"><i data-lucide="history"></i><span>Leave History</span></a></li>
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
                <h4>Leave History</h4>
                <p>View all your leave and OD requests</p>
            </div>
        </div>

        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success mt-3"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger mt-3"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row stats-row g-3">
            <div class="col-md-2 col-6">
                <div class="stat-mini">
                    <div class="stat-mini-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;"><i data-lucide="list"></i></div>
                    <div class="stat-mini-info">
                        <h4><?php echo $stats['total']; ?></h4>
                        <p>Total</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-mini">
                    <div class="stat-mini-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);"><i data-lucide="calendar-check-2"></i></div>
                    <div class="stat-mini-info">
                        <h4><?php echo $stats['cl_total']; ?></h4>
                        <p>CL</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-mini">
                    <div class="stat-mini-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;"><i data-lucide="briefcase"></i></div>
                    <div class="stat-mini-info">
                        <h4><?php echo $stats['od_total']; ?></h4>
                        <p>OD</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-mini">
                    <div class="stat-mini-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);"><i data-lucide="check-circle"></i></div>
                    <div class="stat-mini-info">
                        <h4><?php echo $stats['approved']; ?></h4>
                        <p>Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-mini">
                    <div class="stat-mini-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);"><i data-lucide="clock"></i></div>
                    <div class="stat-mini-info">
                        <h4><?php echo $stats['pending']; ?></h4>
                        <p>Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-mini">
                    <div class="stat-mini-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);"><i data-lucide="x-circle"></i></div>
                    <div class="stat-mini-info">
                        <h4><?php echo $stats['rejected']; ?></h4>
                        <p>Rejected</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <select name="date_range" class="form-select" style="width: auto;">
                <option value="">All Time</option>
                <option value="today" <?php echo $date_range == 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="this_week" <?php echo $date_range == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                <option value="this_month" <?php echo $date_range == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                <option value="this_year" <?php echo $date_range == 'this_year' ? 'selected' : ''; ?>>This Year</option>
            </select>
            <select name="type" class="form-select" style="width: auto;">
                <option value="">All Types</option>
                <option value="CL" <?php echo $filter_type == 'CL' ? 'selected' : ''; ?>>Casual Leave</option>
                <option value="OD" <?php echo $filter_type == 'OD' ? 'selected' : ''; ?>>On Duty</option>
            </select>
            <select name="status" class="form-select" style="width: auto;">
                <option value="">All Status</option>
                <option value="Pending" <?php echo $filter_status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="HOD Approved" <?php echo $filter_status == 'HOD Approved' ? 'selected' : ''; ?>>HOD Approved</option>
                <option value="Approved" <?php echo $filter_status == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="Rejected" <?php echo $filter_status == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            <button type="submit" class="btn-filter"><i data-lucide="filter me-2"></i>Apply</button>
            <a href="leave_history.php" class="btn-reset"><i data-lucide="undo me-2"></i>Reset</a>
        </form>

        <!-- History Table -->
        <div class="history-table">
            <div class="table-header">
                <h5><i class="fas fa-list-alt me-2" style="color: var(--secondary);"></i>Leave Records</h5>
                <span style="color: #94a3b8; font-size: 0.85rem;">Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?></span>
            </div>

            <?php if ($leaves->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>From Date</th>
                            <th>To Date</th>
                            <th>Periods</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Applied On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($leave = $leaves->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="leave-type-badge badge-<?php echo strtolower($leave['leave_type']); ?>">
                                    <?php echo $leave['leave_type']; ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($leave['from_date']); ?></td>
                            <td><?php echo formatDate($leave['to_date']); ?></td>
                            <td><?php echo $leave['period_no']; ?></td>
                            <td><?php echo substr($leave['reason'], 0, 30); ?><?php echo strlen($leave['reason']) > 30 ? '...' : ''; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $leave['status']; ?>">
                                    <?php echo $leave['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($leave['applied_on'])); ?></td>
                            <td>
                                <button class="view-btn" 
                                    data-id="<?php echo $leave['leave_id']; ?>"
                                    data-type="<?php echo $leave['leave_type']; ?>"
                                    data-status="<?php echo $leave['status']; ?>"
                                    data-from="<?php echo formatDate($leave['from_date']); ?>"
                                    data-to="<?php echo formatDate($leave['to_date']); ?>"
                                    data-reason="<?php echo htmlspecialchars($leave['reason']); ?>"
                                    data-periods="<?php echo $leave['period_no']; ?>"
                                    data-applied="<?php echo date('d M Y, h:i A', strtotime($leave['applied_on'])); ?>"
                                    data-substitute="<?php echo $leave['substitute_name'] ? $leave['substitute_name'] : 'N/A'; ?>"
                                    data-remarks="<?php echo htmlspecialchars($leave['admin_remarks'] ?? ''); ?>"
                                    onclick="viewDetails(this)">
                                    <i data-lucide="eye"></i>
                                </button>
                                <button class="delete-btn" 
                                    style="border: none; background: rgba(239, 68, 68, 0.1); color: var(--danger); width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; margin-left: 5px;"
                                    onmouseover="this.style.background='var(--danger)'; this.style.color='white';"
                                    onmouseout="this.style.background='rgba(239, 68, 68, 0.1)'; this.style.color='var(--danger)';"
                                    onclick="confirmDelete(<?php echo $leave['leave_id']; ?>)" title="Delete Record">
                                    <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $filter_type ? '&type='.$filter_type : ''; ?><?php echo $filter_status ? '&status='.$filter_status : ''; ?>">
                            <i data-lucide="chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filter_type ? '&type='.$filter_type : ''; ?><?php echo $filter_status ? '&status='.$filter_status : ''; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $filter_type ? '&type='.$filter_type : ''; ?><?php echo $filter_status ? '&status='.$filter_status : ''; ?>">
                            <i data-lucide="chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <i data-lucide="inbox"></i>
                <h5>No leave records found</h5>
                <p style="color: #94a3b8; font-size: 0.85rem;">Start by applying for your first leave</p>
                <a href="apply_leave.php" class="btn btn-primary mt-3" style="background: var(--secondary); border: none;">
                    <i data-lucide="plus me-2"></i>Apply Now
                </a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- View Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                    <h5 class="modal-title"><i data-lucide="file-alt me-2"></i>Leave Request Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetails(btn) {
            const type = btn.getAttribute('data-type');
            const status = btn.getAttribute('data-status');
            const from = btn.getAttribute('data-from');
            const to = btn.getAttribute('data-to');
            const reason = btn.getAttribute('data-reason');
            const periods = btn.getAttribute('data-periods');
            const applied = btn.getAttribute('data-applied');
            const substitute = btn.getAttribute('data-substitute');
            const remarks = btn.getAttribute('data-remarks');

            let statusClass = 'bg-warning text-dark';
            if(status === 'Approved') statusClass = 'bg-success';
            else if(status === 'Rejected') statusClass = 'bg-danger';
            else if(status === 'HOD Approved') statusClass = 'bg-info text-white';
            const typeLabel = type === 'CL' ? 'Casual Leave (CL)' : 'On Duty (OD)';

            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            
            document.getElementById('modalContent').innerHTML = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Leave Type</h6>
                        <p class="fw-bold mb-0">${typeLabel}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Status</h6>
                        <span class="badge ${statusClass}">${status}</span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">From Date</h6>
                        <p class="mb-0">${from}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">To Date</h6>
                        <p class="mb-0">${to}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Periods Affected</h6>
                        <p class="mb-0">${periods}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Applied On</h6>
                        <p class="mb-0">${applied}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted mb-1">Substitute Staff</h6>
                        <p class="mb-0">${substitute}</p>
                    </div>
                    <div class="col-12 mb-3">
                        <h6 class="text-muted mb-1">Reason/Purpose</h6>
                        <p class="mb-0 bg-light p-2 rounded border border-light">${reason}</p>
                    </div>
                    ${remarks ? `
                    <div class="col-12">
                        <h6 class="text-muted mb-1">Admin Remarks</h6>
                        <p class="mb-0 text-${status === 'Approved' ? 'success' : (status === 'Rejected' ? 'danger' : (status === 'HOD Approved' ? 'info' : 'warning'))}"><i data-lucide="comment-dots me-2"></i>${remarks}</p>
                    </div>
                    ` : ''}
                </div>
            `;
            modal.show();
        }

        function confirmDelete(id) {
            if (confirm("Are you sure you want to permanently delete this leave record? This action cannot be undone.")) {
                document.getElementById('deleteLeaveId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" action="">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="leave_id" id="deleteLeaveId">
    </form>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
<script src="../assets/js/specular.js"></script>
<script src="../assets/js/star_border.js"></script>
<script src="../assets/js/magic_bento.js"></script>
</body>
</html>



