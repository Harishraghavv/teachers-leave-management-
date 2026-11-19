<?php
require_once 'includes/db_config.php';

// Redirect if already logged in
if (isStaffLoggedIn()) {
    redirect('staff/dashboard.php');
}
if (isAdminLoggedIn()) {
    redirect('admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($conn, $_POST['email']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];

    if ($user_type == 'staff') {
        $sql = "SELECT * FROM staff WHERE email = ? AND status = 'Active'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $staff = $result->fetch_assoc();
            if (password_verify($password, $staff['password'])) {
                $_SESSION['staff_id'] = $staff['staff_id'];
                $_SESSION['staff_name'] = $staff['name'];
                $_SESSION['staff_email'] = $staff['email'];
                redirect('staff/dashboard.php');
            } else {
                $error = 'Invalid password!';
            }
        } else {
            $error = 'Staff not found or account inactive!';
        }
    } else {
        if ($user_type == 'hod') {
            $sql = "SELECT * FROM admin WHERE email = ? AND role = 'HOD'";
        } else {
            $sql = "SELECT * FROM admin WHERE email = ? AND role IN ('Admin', 'Principal')";
        }
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];
                redirect('admin/dashboard.php');
            } else {
                $error = 'Invalid password!';
            }
        } else {
            $error = 'Admin not found!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- PWA & Mobile Optimization -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1E40AF">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="TeacherLeave">
    <link rel="apple-touch-icon" href="assets/images/logo.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Portal | Computer Science</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>

        body {
            background-color: #F8FAFC !important;
            background: #F8FAFC !important;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #0F172A;
        }

        .login-container {
            background: #FFFFFF !important;
            border-radius: 20px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.07);
            width: 100%;
            max-width: 1000px;
            display: flex;
            overflow: hidden;
            border: 1px solid #E2E8F0;
        }

        /* Left Side (Branding) */
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #1E40AF 0%, #2563EB 100%) !important;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
            border-right: 1px solid #E2E8F0;
        }

        .login-left-content { position: relative; z-index: 1; }
        
        .college-logo {
            width: 80px; height: 80px;
            background: white;
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .college-logo i { font-size: 38px; color: #1E40AF; }
        .login-left h1 { font-size: 2.2rem; font-weight: 700; margin-bottom: 12px; line-height: 1.2; color: #FFFFFF; }
        .login-left h2 { font-size: 1.1rem; font-weight: 400; opacity: 0.9; margin-bottom: 40px; color: #E2E8F0; }
        
        /* Right Side (Form) */
        .login-right {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #FFFFFF !important;
        }
        .login-header { margin-bottom: 30px; }
        .login-header h3 { font-size: 1.85rem; font-weight: 700; color: #0F172A; margin-bottom: 5px; }
        .login-header p { color: #64748B; font-size: 0.95rem; }
        
        .user-type-selector {
            display: flex; gap: 10px; margin-bottom: 25px;
        }
        .user-type-btn {
            flex: 1; padding: 12px 10px;
            border: 1px solid #CBD5E1; border-radius: 12px;
            background: #F8FAFC; cursor: pointer; transition: all 0.2s;
            text-align: center; color: #475569; font-weight: 600;
        }
        .user-type-btn::before, .user-type-btn::after {
            display: none !important;
        }
        .user-type-btn:hover {
            background: #EDF2F7;
            color: #1E40AF;
        }
        .user-type-btn.active {
            color: #FFFFFF !important;
            background: #2563EB !important;
            border-color: #2563EB !important;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .user-type-btn i { font-size: 1.25rem; margin-bottom: 6px; display: block; }
        
        .form-floating { margin-bottom: 18px; }
        .form-floating .form-control {
            border-radius: 12px; height: 56px; border: 1px solid #CBD5E1;
            background: #FFFFFF; color: #0F172A;
        }
        .form-floating .form-control:focus {
            background: #FFFFFF; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }
        .form-floating label {
            color: #64748B;
        }
        
        .forgot-pass {
            text-align: right; margin-bottom: 20px;
        }
        .forgot-pass a {
            color: #2563EB; font-size: 0.9rem; font-weight: 600; text-decoration: none;
        }
        .forgot-pass a:hover { color: #1E40AF; text-decoration: underline; }

        .btn-login {
            width: 100%; padding: 14px;
            background: #2563EB; border: none; border-radius: 12px;
            color: white; font-weight: 600; font-size: 1.05rem;
            transition: all 0.2s; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .btn-login:hover {
            background: #1D4ED8; transform: translateY(-1px);
        }

        @media (max-width: 900px) {
            .login-container { flex-direction: column; }
            .login-left { padding: 40px 30px; }
            .login-right { padding: 40px 30px; }
            body { padding: 15px; }
        }

    </style>
</head>
<body>
    

        <!-- Right Side -->
        <div class="login-right">
            <div class="login-header">
                <h3>Welcome Back</h3>
                <p>Sign in to access your dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i data-lucide="exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="user-type-selector" style="gap: 10px;">
                    <div class="user-type-btn active" onclick="selectUserType('staff', this)">
                        <i data-lucide="user-tie"></i>
                        <span>Staff</span>
                    </div>
                    <div class="user-type-btn" onclick="selectUserType('hod', this)">
                        <i data-lucide="book-open"></i>
                        <span>HOD</span>
                    </div>
                    <div class="user-type-btn" onclick="selectUserType('admin', this)">
                        <i data-lucide="user-shield"></i>
                        <span>Admin</span>
                    </div>
                </div>
                <input type="hidden" name="user_type" id="user_type" value="staff">

                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                    <label for="email">Email Address</label>
                </div>

                <div class="form-floating mb-2">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password">Password</label>
                </div>
                
                <div class="forgot-pass">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i data-lucide="sign-in-alt me-2"></i>Secure Login
                </button>
                
                <div style="display: flex; align-items: center; margin: 20px 0; color: #94a3b8;">
                    <div style="flex: 1; border-bottom: 1px solid #cbd5e1;"></div>
                    <span style="padding: 0 15px; font-size: 0.85rem;">or</span>
                    <div style="flex: 1; border-bottom: 1px solid #cbd5e1;"></div>
                </div>

                <a href="google_auth.php?action=login" class="btn btn-outline-secondary w-100 mb-3" style="border-radius: 12px; padding: 12px; display: flex; align-items: center; justify-content: center; gap: 10px; font-weight: 600; color: #475569; border-color: #cbd5e1;">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google" style="width: 20px; height: 20px;">
                    Sign in with Google
                </a>

                <div class="text-center mt-3" style="font-size: 0.95rem; color: #64748b;">
                    Don't have an account? <a href="register.php" style="color: var(--accent); font-weight: 600; text-decoration: none;">Create one</a>
                </div>
            </form>
            
            <div style="text-align: center; margin-top: 40px; color: #64748b; font-size: 0.85rem;">
                Powered by <a href="#" style="color: var(--text); font-weight: 600; text-decoration: none;">Dept of CS</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectUserType(type, btn) {
            document.getElementById('user_type').value = type;
            document.querySelectorAll('.user-type-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
    </script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
    <script>
        // --- React Bits "Strands" Style Background Animation ---
        const canvas = document.getElementById('strandsCanvas');
        const ctx = canvas.getContext('2d');
        let width = canvas.width = canvas.parentElement.clientWidth;
        let height = canvas.height = canvas.parentElement.clientHeight;

        let particles = [];
        const particleCount = 60; // Density
        const maxDistance = 100; // Connection distance

        class Particle {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.vx = (Math.random() - 0.5) * 1.5;
                this.vy = (Math.random() - 0.5) * 1.5;
                this.radius = Math.random() * 2 + 1;
            }
            update() {
                this.x += this.vx;
                this.y += this.vy;
                if (this.x < 0 || this.x > width) this.vx *= -1;
                if (this.y < 0 || this.y > height) this.vy *= -1;
            }
            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
                ctx.fill();
            }
        }

        for (let i = 0; i < particleCount; i++) {
            particles.push(new Particle());
        }

        function animate() {
            ctx.clearRect(0, 0, width, height);
            
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
                
                // Draw connecting strands
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < maxDistance) {
                        ctx.beginPath();
                        ctx.strokeStyle = `rgba(255, 255, 255, ${1 - distance / maxDistance})`;
                        ctx.lineWidth = 1;
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(animate);
        }
        
        animate();

        window.addEventListener('resize', () => {
            width = canvas.width = canvas.parentElement.clientWidth;
            height = canvas.height = canvas.parentElement.clientHeight;
        });
    </script>
<script src="assets/js/specular.js"></script>
<script src="assets/js/star_border.js"></script>
<script src="assets/js/magic_bento.js"></script>
<script src="assets/js/ferrofluid.js"></script>
    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('PWA Service Worker registered:', reg.scope))
                    .catch(err => console.log('PWA Service Worker registration failed:', err));
            });
        }
    </script>

</body>
</html>


