<?php
require_once 'includes/db_config.php';

// Handle the simulated OAuth Callback
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['google_email'])) {
    $email = sanitize($conn, $_POST['google_email']);
    $name = sanitize($conn, $_POST['google_name']);
    
    // 1. Check if user exists in ADMIN table first
    $check_admin = $conn->prepare("SELECT * FROM admin WHERE email = ?");
    $check_admin->bind_param("s", $email);
    $check_admin->execute();
    $result_admin = $check_admin->get_result();
    
    if ($result_admin->num_rows > 0) {
        $admin = $result_admin->fetch_assoc();
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_role'] = $admin['role'];
        redirect('admin/dashboard.php');
        exit;
    }
    
    // 2. Check if user exists in STAFF table
    $check_staff = $conn->prepare("SELECT * FROM staff WHERE email = ?");
    $check_staff->bind_param("s", $email);
    $check_staff->execute();
    $result_staff = $check_staff->get_result();
    
    if ($result_staff->num_rows > 0) {
        $staff = $result_staff->fetch_assoc();
        $_SESSION['staff_id'] = $staff['staff_id'];
        $_SESSION['staff_name'] = $staff['name'];
        $_SESSION['staff_email'] = $staff['email'];
        redirect('staff/dashboard.php');
        exit;
    } else {
        // User does not exist -> Create account using Google data
        $designation = 'Assistant Professor'; // Default
        $department = 'Computer Science';
        $subject = 'Computer Science';
        $status = 'Active';
        $join_date = date('Y-m-d');
        
        $random_password = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
        
        $insert = $conn->prepare("INSERT INTO staff (name, email, designation, department, subject, password, join_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param("ssssssss", $name, $email, $designation, $department, $subject, $random_password, $join_date, $status);
        
        if ($insert->execute()) {
            $_SESSION['staff_id'] = $conn->insert_id;
            $_SESSION['staff_name'] = $name;
            $_SESSION['staff_email'] = $email;
            redirect('staff/dashboard.php');
        } else {
            die("Database Error during Google Registration: " . $conn->error);
        }
    }
}

// Fetch all existing users to display in the "Google" chooser
$google_accounts = [];
$res_admin = $conn->query("SELECT name, email, 'Admin' as role FROM admin");
while ($row = $res_admin->fetch_assoc()) { $google_accounts[] = $row; }
$res_staff = $conn->query("SELECT name, email, 'Staff' as role FROM staff");
while ($row = $res_staff->fetch_assoc()) { $google_accounts[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - Google Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Roboto', sans-serif;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; margin: 0;
        }
        .google-box {
            background: white;
            width: 100%; max-width: 450px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 40px; text-align: center;
            border: 1px solid #dadce0;
        }
        .google-logo { width: 75px; margin-bottom: 15px; }
        .google-title { font-size: 1.5rem; font-weight: 400; color: #202124; margin-bottom: 10px; }
        .google-subtitle { font-size: 1rem; color: #202124; margin-bottom: 30px; }
        
        .account-card {
            display: flex; align-items: center; padding: 15px 20px;
            border-top: 1px solid #dadce0; cursor: pointer; transition: background 0.2s;
            text-align: left;
        }
        .account-card:hover { background: #f8f9fa; }
        .account-card:last-child { border-bottom: 1px solid #dadce0; }
        
        .avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: #1a73e8; color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; margin-right: 15px; font-weight: 500;
        }
        .account-info { flex: 1; }
        .account-name { font-size: 0.95rem; font-weight: 500; color: #3c4043; margin-bottom: 2px; }
        .account-email { font-size: 0.85rem; color: #5f6368; }
        
        .loader {
            display: none;
            width: 40px; height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #1a73e8;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 30px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="google-box" id="auth-box">
        <img src="https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg" alt="Google" class="google-logo">
        <h1 class="google-title">Choose an account</h1>
        <p class="google-subtitle">to continue to <strong>Dept of Computer Science Portal</strong></p>
        
        <div style="max-height: 400px; overflow-y: auto; margin-bottom: 20px; border-bottom: 1px solid #dadce0;">
        <?php foreach ($google_accounts as $index => $acc): 
            $colors = ['#1a73e8', '#ea4335', '#34a853', '#fbbc05', '#673ab7', '#e91e63'];
            $color = $colors[$index % count($colors)];
            $initial = strtoupper(substr($acc['name'], 0, 1));
        ?>
            <div class="account-card" onclick="simulateOAuth('<?php echo htmlspecialchars($acc['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($acc['email'], ENT_QUOTES); ?>')">
                <div class="avatar" style="background: <?php echo $color; ?>;"><?php echo $initial; ?></div>
                <div class="account-info">
                    <div class="account-name"><?php echo htmlspecialchars($acc['name']); ?> <span style="font-size: 10px; background: #f1f3f4; padding: 2px 6px; border-radius: 4px; margin-left: 5px; color: #5f6368;"><?php echo $acc['role']; ?></span></div>
                    <div class="account-email"><?php echo htmlspecialchars($acc['email']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        
        <p style="font-size: 0.85rem; color: #5f6368; margin-top: 20px; text-align: left;">
            To continue, Google will share your name, email address, and profile picture with the portal.
        </p>
    </div>

    <div class="google-box" id="loading-box" style="display: none; padding: 60px 40px;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg" alt="Google" class="google-logo">
        <div class="loader"></div>
        <p style="color: #5f6368; font-size: 1.1rem; margin-top: 20px;">Signing in...</p>
    </div>

    <!-- Hidden form to submit OAuth data to backend -->
    <form id="oauth-form" method="POST" action="">
        <input type="hidden" name="google_name" id="google_name">
        <input type="hidden" name="google_email" id="google_email">
    </form>

    <script>
        function simulateOAuth(name, email) {
            // Show loading UI to simulate network request
            document.getElementById('auth-box').style.display = 'none';
            document.getElementById('loading-box').style.display = 'block';
            
            // Set form values
            document.getElementById('google_name').value = name;
            document.getElementById('google_email').value = email;
            
            // Wait 1.5 seconds then submit
            setTimeout(() => {
                document.getElementById('oauth-form').submit();
            }, 1500);
        }
    </script>
<script src="assets/js/specular.js"></script>
<script src="assets/js/star_border.js"></script>
<script src="assets/js/magic_bento.js"></script>
</body>
</html>

