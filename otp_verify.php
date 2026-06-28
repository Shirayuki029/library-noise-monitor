<?php

// ===== DEBUG: Show all errors =====
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ===================================

require_once 'config.php';
require_once 'mail_config.php';
// ... rest of your code

if (isAuthenticated()) {
    $_SESSION['login_success'] = true;
    $_SESSION['login_username'] = $_SESSION['username'];
    header("Location: dashboard.php");
    exit();
}

if (!isset($_SESSION['otp']) || !isset($_SESSION['temp_user_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';
$otp_sent = $_SESSION['otp_sent'] ?? false;

if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

if ($_SESSION['otp_attempts'] >= 3) {
    $error = '⚠️ Too many failed OTP attempts. Please login again.';
    
    session_unset();
    session_destroy();
    
    session_start();
    $_SESSION['otp_locked'] = true;
    
    header("Location: login.php?locked=1");
    exit();
}

if (isset($_GET['resend'])) {
    $_SESSION['otp_attempts'] = 0;
    
    session_regenerate_id(true);
    
    $new_otp = generateOTP();
    $_SESSION['otp'] = $new_otp;
    $_SESSION['otp_expiry'] = time() + OTP_EXPIRY;
    $_SESSION['otp_sent'] = false;
    $otp_sent = false;
    
    $error = '';
    $success = '📧 New OTP sent! You have 3 attempts.';
    
    header("Location: otp_verify.php?resend_success=1");
    exit();
}

if (isset($_GET['resend_success'])) {
    $success = '📧 New OTP sent! You have 3 attempts.';
}

if (!$otp_sent) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['temp_user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    if ($user) {
        $email_sent = sendOTPEmail(
            $user['email'],
            $_SESSION['temp_username'],
            $_SESSION['otp']
        );
        
        if ($email_sent) {
            $_SESSION['otp_sent'] = true;
            $success = '📧 OTP sent to your email! Please check your inbox.';
        } else {
            $error = '⚠️ Could not send email. OTP displayed below for testing.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_otp = trim($_POST['otp'] ?? '');
    $stored_otp = $_SESSION['otp'] ?? '';
    $expiry = $_SESSION['otp_expiry'] ?? 0;
    
    if (empty($user_otp)) {
        $error = 'Please enter the OTP code';
    } elseif (time() > $expiry) {
        $error = 'OTP has expired. Please request a new one.';
        $_SESSION['otp_attempts'] = 0;
    } elseif ($user_otp === $stored_otp) {
        $_SESSION['otp_attempts'] = 0;
        
        $_SESSION['user_id'] = $_SESSION['temp_user_id'];
        $_SESSION['username'] = $_SESSION['temp_username'];
        $_SESSION['role'] = $_SESSION['temp_role'];
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
        
        session_regenerate_id(true);
        
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_username']);
        unset($_SESSION['temp_role']);
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
        unset($_SESSION['otp_sent']);
        
        logActivity($_SESSION['user_id'], 'login', 'User logged in');
        
        $conn = getDB();
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        
        $_SESSION['login_success'] = true;
        $_SESSION['login_username'] = $_SESSION['username'];
        
        header("Location: dashboard.php");
        exit();
    } else {
        $_SESSION['otp_attempts']++;
        $remaining = 3 - $_SESSION['otp_attempts'];
        $error = "Invalid OTP code. $remaining attempts remaining.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Authentication</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-container { max-width: 400px; margin: 50px auto; padding: 30px; background: rgba(15, 23, 42, 0.8); border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); text-align: center; }
        .auth-container h2 { margin-bottom: 10px; color: #a5b4fc; }
        .auth-container p { color: #94a3b8; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group input { width: 100%; padding: 14px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.3); color: white; font-size: 24px; text-align: center; letter-spacing: 4px; }
        .form-group input:focus { outline: none; border-color: #6366f1; }
        .auth-btn { width: 100%; padding: 14px; background: #6366f1; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        .auth-btn:hover { background: #4f46e5; }
        .resend-link { margin-top: 15px; color: #94a3b8; }
        .resend-link a { color: #a5b4fc; text-decoration: none; }
        .resend-link a:hover { text-decoration: underline; }
        .error { background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .success { background: rgba(34, 197, 94, 0.2); color: #22c55e; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .back-link { margin-top: 20px; }
        .back-link a { color: #64748b; text-decoration: none; font-size: 14px; }
        .otp-fallback { font-size: 36px; font-weight: bold; color: #22c55e; background: rgba(0,0,0,0.3); padding: 15px; border-radius: 12px; margin: 15px 0; letter-spacing: 8px; }
        .email-notice { background: rgba(99, 102, 241, 0.15); padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid rgba(99, 102, 241, 0.3); }
        .email-notice .icon { font-size: 32px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <h2>🔐 OTP Authentication</h2>
            
            <?php if (!isset($error) || strpos($error, 'displayed') === false): ?>
                <div class="email-notice">
                    <div class="icon">📧</div>
                    <p style="margin-top: 10px;">A 6-digit OTP has been sent to your email.</p>
                    <p style="font-size: 13px; color: #94a3b8;">Please check your inbox (and spam folder).</p>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error) && strpos($error, 'displayed below') !== false): ?>
                <div class="otp-fallback"><?php echo $_SESSION['otp']; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" required autofocus>
                </div>
                <button type="submit" class="auth-btn">Verify OTP</button>
            </form>
            
            <div class="resend-link">
                Didn't receive OTP? <a href="?resend=1">Resend OTP</a>
            </div>
            
            <div class="back-link">
                <a href="logout.php">Cancel & Logout</a>
            </div>
        </div>
    </div>
</body>
</html>
