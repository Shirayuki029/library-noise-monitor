<?php
require_once 'config.php';

if (isAuthenticated()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$logout_alert = false;
$logout_username = '';
$selected_role = 'user';

// Check if user just logged out
if (isset($_SESSION['logout_success']) && $_SESSION['logout_success']) {
    $logout_alert = true;
    $logout_username = $_SESSION['logout_username'] ?? 'User';
    unset($_SESSION['logout_success']);
    unset($_SESSION['logout_username']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $selected_role = $_POST['role'] ?? 'user';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        $conn = getDB();
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        if ($user && $user['is_active'] == 1 && password_verify($password, $user['password_hash'])) {
            // Check if role matches selected role
            if ($selected_role !== $user['role']) {
                $error = "This account is not registered as " . ucfirst($selected_role) . ". Please select the correct role.";
            } else {
                $otp = generateOTP();
                
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['temp_username'] = $user['username'];
                $_SESSION['temp_role'] = $user['role'];
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_expiry'] = time() + OTP_EXPIRY;
                
                header("Location: otp_verify.php");
                exit();
            }
        } else {
            $error = 'Invalid credentials or account disabled';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Library Noise Monitor</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            padding: 20px;
        }

        /* ===== LOGIN CONTAINER ===== */
        .login-wrapper {
            width: 100%;
            max-width: 450px;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== LOGO / BRAND ===== */
        .brand {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand .logo {
            font-size: 72px;
            display: block;
            margin-bottom: 10px;
            animation: pulseLogo 3s ease-in-out infinite;
        }

        @keyframes pulseLogo {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .brand h1 {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #a5b4fc, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -1px;
        }

        .brand .subtitle {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 5px;
            letter-spacing: 1px;
        }

        /* ===== LOGIN CARD ===== */
        .login-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px 35px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
        }

        .login-card h2 {
            color: #f8fafc;
            font-size: 22px;
            text-align: center;
            margin-bottom: 8px;
        }

        .login-card .login-sub {
            color: #94a3b8;
            text-align: center;
            font-size: 14px;
            margin-bottom: 28px;
        }

        /* ===== ROLE SELECTOR ===== */
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 25px;
        }

        .role-option {
            padding: 14px 10px;
            border-radius: 14px;
            border: 2px solid rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }

        .role-option:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .role-option .role-icon {
            font-size: 28px;
            display: block;
            margin-bottom: 4px;
        }

        .role-option .role-name {
            font-size: 14px;
            font-weight: 600;
            color: #94a3b8;
            transition: color 0.3s;
        }

        .role-option .role-badge {
            font-size: 10px;
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 4px;
        }

        /* Admin Role */
        .role-option.admin .role-badge {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        /* Selected State */
        .role-option.selected {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.1);
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.1);
        }

        .role-option.selected .role-name {
            color: #f8fafc;
        }

        .role-option.selected .role-badge {
            background: rgba(99, 102, 241, 0.3);
            color: #a5b4fc;
        }

        .role-option.admin.selected {
            border-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.1);
        }

        .role-option.admin.selected .role-badge {
            background: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        /* Hidden radio */
        .role-option input[type="radio"] {
            display: none;
        }

        /* ===== FORM FIELDS ===== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .form-group .input-wrapper {
            position: relative;
        }

        .form-group .input-wrapper .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #475569;
        }

        .form-group input {
            width: 100%;
            padding: 14px 14px 14px 48px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(0, 0, 0, 0.3);
            color: #f8fafc;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            background: rgba(0, 0, 0, 0.5);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .form-group input::placeholder {
            color: #475569;
        }

        /* ===== LOGIN BUTTON ===== */
        .auth-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .auth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.3);
        }

        .auth-btn:active {
            transform: translateY(0);
        }

        .auth-btn .btn-icon {
            margin-right: 8px;
        }

        /* ===== REGISTER LINK ===== */
        .auth-link {
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            font-size: 14px;
        }

        .auth-link a {
            color: #a5b4fc;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .auth-link a:hover {
            color: #818cf8;
            text-decoration: underline;
        }

        /* ===== DIVIDER ===== */
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            gap: 15px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.06);
        }

        .divider span {
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ===== ERROR ===== */
        .error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border: 1px solid rgba(239, 68, 68, 0.15);
        }

        /* ===== ALERT OVERLAY ===== */
        .alert-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .alert-overlay.show {
            display: flex;
        }

        .alert-box {
            background: rgba(15, 23, 42, 0.95);
            border-radius: 20px;
            padding: 40px 50px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: slideDown 0.4s ease;
        }

        .alert-box .icon { font-size: 60px; margin-bottom: 15px; }
        .alert-box .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .alert-box .message { font-size: 16px; color: #94a3b8; margin-bottom: 25px; line-height: 1.6; }
        .alert-box .btn-close {
            background: #6366f1;
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .alert-box .btn-close:hover { background: #4f46e5; transform: scale(1.05); }

        .alert-box.success .icon { color: #22c55e; }
        .alert-box.success .title { color: #22c55e; }
        .alert-box.success .btn-close { background: #22c55e; }
        .alert-box.success .btn-close:hover { background: #16a34a; }

        .alert-box.error .icon { color: #ef4444; }
        .alert-box.error .title { color: #ef4444; }
        .alert-box.error .btn-close { background: #ef4444; }
        .alert-box.error .btn-close:hover { background: #dc2626; }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 500px) {
            .login-card {
                padding: 30px 20px;
            }
            .brand .logo { font-size: 56px; }
            .brand h1 { font-size: 26px; }
            .role-selector { grid-template-columns: 1fr 1fr; gap: 10px; }
            .role-option { padding: 12px 8px; }
            .role-option .role-icon { font-size: 22px; }
            .alert-box { padding: 30px 25px; }
        }

        @media (max-width: 380px) {
            .role-selector { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- ===== ALERT OVERLAY ===== -->
    <div id="alertOverlay" class="alert-overlay">
        <div id="alertBox" class="alert-box">
            <div class="icon" id="alertIcon">✅</div>
            <div class="title" id="alertTitle">Success!</div>
            <div class="message" id="alertMessage">You have logged in successfully.</div>
            <button class="btn-close" onclick="closeAlert()">OK</button>
        </div>
    </div>

    <!-- ===== LOGIN PAGE ===== -->
    <div class="login-wrapper">
        
        <!-- Brand / Logo -->
        <div class="brand">
            <span class="logo">📚</span>
            <h1>Library Noise Monitor</h1>
            <p class="subtitle">🔊 Smart Noise Detection System</p>
        </div>

        <!-- Login Card -->
        <div class="login-card">
            <h2>🔐 Welcome Back</h2>
            <p class="login-sub">Sign in to access the noise monitoring dashboard</p>

            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" id="loginForm">

                <!-- Role Selector -->
                <div class="role-selector">
                    <label class="role-option user <?php echo ($selected_role === 'user') ? 'selected' : ''; ?>">
                        <input type="radio" name="role" value="user" <?php echo ($selected_role === 'user') ? 'checked' : ''; ?>>
                        <span class="role-icon">👤</span>
                        <span class="role-name">User</span>
                        <span class="role-badge">Standard</span>
                    </label>
                    <label class="role-option admin <?php echo ($selected_role === 'admin') ? 'selected' : ''; ?>">
                        <input type="radio" name="role" value="admin" <?php echo ($selected_role === 'admin') ? 'checked' : ''; ?>>
                        <span class="role-icon">⚙️</span>
                        <span class="role-name">Admin</span>
                        <span class="role-badge">Elevated</span>
                    </label>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label>👤 Username or Email</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📧</span>
                        <input type="text" name="username" placeholder="Enter your username or email" required>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label>🔑 Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <!-- Login Button -->
                <button type="submit" class="auth-btn">
                    <span class="btn-icon">🚀</span> Sign In
                </button>

                <!-- Divider -->
                <div class="divider">
                    <span>New here?</span>
                </div>

                <!-- Register Link -->
                <div class="auth-link">
                    Don't have an account? <a href="register.php">Create an account</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ===== ROLE SELECTOR CLICK =====
        document.querySelectorAll('.role-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected from all
                document.querySelectorAll('.role-option').forEach(o => o.classList.remove('selected'));
                // Add selected to clicked
                this.classList.add('selected');
                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });

        // ===== ALERT FUNCTIONS =====
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['login_success']) && $_SESSION['login_success']): ?>
                showAlert('success', '✅', 'Login Successful!', 'Welcome back, <?php echo $_SESSION['login_username']; ?>!');
                <?php 
                unset($_SESSION['login_success']);
                unset($_SESSION['login_username']);
                ?>
            <?php endif; ?>

            <?php if ($logout_alert): ?>
                showAlert('error', '🚪', 'Logged Out!', 'Goodbye, <?php echo $logout_username; ?>! You have been logged out successfully.');
            <?php endif; ?>
        });

        function showAlert(type, icon, title, message) {
            const overlay = document.getElementById('alertOverlay');
            const box = document.getElementById('alertBox');
            document.getElementById('alertIcon').textContent = icon;
            document.getElementById('alertTitle').textContent = title;
            document.getElementById('alertMessage').textContent = message;
            
            box.className = 'alert-box';
            box.classList.add(type);
            
            overlay.classList.add('show');
            setTimeout(closeAlert, 4000);
        }

        function closeAlert() {
            document.getElementById('alertOverlay').classList.remove('show');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAlert();
        });

        document.getElementById('alertOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeAlert();
        });
    </script>

</body>
</html>