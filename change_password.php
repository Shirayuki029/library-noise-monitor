<?php
require_once 'config.php';
require_once 'mail_config.php';
requireAuth();

$error = '';
$success = '';
$otp_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $conn = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Get user email
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    if ($user) {
        // Generate OTP
        $otp = generateOTP();
        $_SESSION['password_otp'] = $otp;
        $_SESSION['password_otp_expiry'] = time() + 120; // 2 minutes
        $_SESSION['password_otp_attempts'] = 0;
        
        // Send email
        $sent = sendPasswordChangeOTP($user['email'], $user['username'], $otp);
        
        if ($sent) {
            $otp_sent = true;
            $success = '📧 OTP sent to your email! Please check your inbox.';
        } else {
            $error = '⚠️ Could not send email. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Library Noise Monitor</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cp-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .cp-card {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }
        .cp-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }
        .cp-card h2 {
            color: #f8fafc;
            margin-bottom: 10px;
        }
        .cp-card p {
            color: #94a3b8;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        .form-group label {
            display: block;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.3);
            color: #f8fafc;
            font-size: 15px;
            transition: all 0.3s;
            text-align: center;
            letter-spacing: 4px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
        }
        .btn-primary {
            background: #6366f1;
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-primary:hover {
            background: #4f46e5;
            transform: translateY(-2px);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.05);
            color: #94a3b8;
            padding: 12px 30px;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
        }
        .message {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .message.success {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.15);
        }
        .message.error {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.15);
        }
        .otp-display {
            font-size: 42px;
            font-weight: bold;
            color: #22c55e;
            background: rgba(0,0,0,0.3);
            padding: 15px;
            border-radius: 12px;
            letter-spacing: 8px;
            margin: 15px 0;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .footer-links {
            margin-top: 20px;
        }
        .footer-links a {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
        }
        .footer-links a:hover {
            color: #a5b4fc;
            text-decoration: underline;
        }
        .expiry-note {
            font-size: 12px;
            color: #64748b;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h1>🔐 Change Password</h1>
                <div>
                    <a href="profile.php" class="btn small" style="background: #22c55e; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">👤 Profile</a>
                    <a href="dashboard.php" class="btn small" style="background: #6366f1; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">📊 Dashboard</a>
                </div>
            </div>
        </div>

        <div class="cp-container">
            <div class="cp-card">
                <div class="cp-icon">🔑</div>
                <h2>Verify Your Identity</h2>
                <p>
                    We'll send a 6-digit OTP to your registered email address 
                    to verify your identity before changing your password.
                </p>

                <?php if ($error): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="message success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if (!$otp_sent): ?>
                    <form method="POST">
                        <button type="submit" name="send_otp" class="btn-primary">📧 Send OTP</button>
                    </form>
                <?php else: ?>
                    <!-- Show OTP for testing if email fails -->
                    <?php if (strpos($error, 'Could not send email') !== false): ?>
                        <div class="otp-display"><?php echo $_SESSION['password_otp']; ?></div>
                        <p style="color: #94a3b8; font-size: 13px;">⚠️ Email failed. Use this OTP for testing.</p>
                    <?php endif; ?>
                    
                    <form method="POST" action="verify_password_otp.php">
                        <div class="form-group">
                            <label>📱 Enter OTP Code</label>
                            <input type="text" name="otp" placeholder="000000" maxlength="6" required autofocus>
                        </div>
                        <button type="submit" class="btn-primary">✅ Verify OTP</button>
                    </form>
                    
                    <div class="button-group">
                        <form method="POST">
                            <button type="submit" name="send_otp" class="btn-secondary">🔄 Resend OTP</button>
                        </form>
                        <a href="profile.php" class="btn-secondary">❌ Cancel</a>
                    </div>
                    
                    <div class="expiry-note">
                        OTP expires in 2 minutes • You have 3 attempts
                    </div>
                <?php endif; ?>

                <div class="footer-links">
                    <a href="profile.php">← Back to Profile</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>