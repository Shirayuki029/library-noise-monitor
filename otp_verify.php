<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
// DON'T include mail_config.php - skip email for now

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

// Always show OTP for testing
$error = '⚠️ DEBUG: OTP displayed below for testing.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_otp = trim($_POST['otp'] ?? '');
    $stored_otp = $_SESSION['otp'] ?? '';
    $expiry = $_SESSION['otp_expiry'] ?? 0;
    
    if (empty($user_otp)) {
        $error = 'Please enter the OTP code';
    } elseif (time() > $expiry) {
        $error = 'OTP has expired. Please request a new one.';
    } elseif ($user_otp === $stored_otp) {
        // Login successful
        $_SESSION['user_id'] = $_SESSION['temp_user_id'];
        $_SESSION['username'] = $_SESSION['temp_username'];
        $_SESSION['role'] = $_SESSION['temp_role'];
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
        
        unset($_SESSION['temp_user_id']);
        unset($_SESSION['temp_username']);
        unset($_SESSION['temp_role']);
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
        unset($_SESSION['otp_sent']);
        
        $_SESSION['login_success'] = true;
        $_SESSION['login_username'] = $_SESSION['username'];
        
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "❌ Invalid OTP. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Authentication</title>
    <style>
        body { font-family: Arial; background: #0f172a; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #1e293b; padding: 40px; border-radius: 20px; max-width: 400px; width: 100%; text-align: center; }
        h2 { color: #a5b4fc; }
        .debug-box { background: #0f172a; padding: 20px; border-radius: 10px; margin: 20px 0; border: 2px solid #22c55e; }
        .debug-box .label { color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 2px; }
        .debug-box .code { color: #22c55e; font-size: 52px; font-weight: bold; letter-spacing: 15px; font-family: monospace; }
        .debug-box .hint { color: #64748b; font-size: 13px; margin-top: 5px; }
        input { width: 100%; padding: 14px; border-radius: 10px; border: 1px solid #6366f1; background: #0f172a; color: white; font-size: 24px; text-align: center; box-sizing: border-box; }
        button { width: 100%; padding: 14px; background: #6366f1; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 15px; }
        .error { color: #ef4444; background: rgba(239,68,68,0.1); padding: 10px; border-radius: 10px; margin: 10px 0; }
        .back-link { margin-top: 15px; }
        .back-link a { color: #64748b; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔐 OTP Verification</h2>
        
        <div class="debug-box">
            <div class="label">📱 YOUR OTP CODE</div>
            <div class="code"><?php echo $_SESSION['otp']; ?></div>
            <div class="hint">Enter this code to login (email is disabled for testing)</div>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" required autofocus>
            <button type="submit">Verify OTP</button>
        </form>
        
        <div class="back-link">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
</body>
</html>
