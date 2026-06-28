<?php
require_once 'config.php';
requireAuth();

// Check if OTP is set
if (!isset($_SESSION['password_otp']) || !isset($_SESSION['password_otp_expiry'])) {
    header("Location: change_password.php");
    exit();
}

// Check attempts
if (!isset($_SESSION['password_otp_attempts'])) {
    $_SESSION['password_otp_attempts'] = 0;
}

$error = '';
$success = '';
$otp_verified = false;

// Check if OTP expired
if (time() > $_SESSION['password_otp_expiry']) {
    unset($_SESSION['password_otp']);
    unset($_SESSION['password_otp_expiry']);
    $error = '⏰ OTP has expired. Please request a new one.';
    header("Location: change_password.php?expired=1");
    exit();
}

// Check attempts
if ($_SESSION['password_otp_attempts'] >= 3) {
    unset($_SESSION['password_otp']);
    unset($_SESSION['password_otp_expiry']);
    header("Location: change_password.php?locked=1");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_otp = trim($_POST['otp'] ?? '');
    $stored_otp = $_SESSION['password_otp'] ?? '';
    
    if (empty($user_otp)) {
        $error = 'Please enter the OTP code';
    } elseif ($user_otp === $stored_otp) {
        // OTP verified - show password change form
        $otp_verified = true;
        $_SESSION['password_otp_verified'] = true;
        $_SESSION['password_otp_attempts'] = 0;
    } else {
        $_SESSION['password_otp_attempts']++;
        $remaining = 3 - $_SESSION['password_otp_attempts'];
        $error = "❌ Invalid OTP. $remaining attempts remaining.";
        
        if ($remaining == 0) {
            unset($_SESSION['password_otp']);
            unset($_SESSION['password_otp_expiry']);
            header("Location: change_password.php?locked=1");
            exit();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && $otp_verified) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $conn = getDB();
        $user_id = $_SESSION['user_id'];
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);
        
        if ($stmt->execute()) {
            // Clear OTP session
            unset($_SESSION['password_otp']);
            unset($_SESSION['password_otp_expiry']);
            unset($_SESSION['password_otp_verified']);
            
            logActivity($user_id, 'password_change', 'Password changed successfully');
            $success = '✅ Password changed successfully!';
            
            // Redirect after success
            header("Refresh: 2; url=profile.php?password_changed=1");
        } else {
            $error = 'Password change failed: ' . $conn->error;
        }
        $stmt->close();
        $conn->close();
    }
}

// Check if already verified (from previous step)
if (isset($_SESSION['password_otp_verified']) && $_SESSION['password_otp_verified'] === true) {
    $otp_verified = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP & Change Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .vp-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .vp-card {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.05);
            text-align: center;
        }
        .vp-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }
        .vp-card h2 {
            color: #f8fafc;
            margin-bottom: 10px;
        }
        .vp-card p {
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
        .btn-success {
            background: #22c55e;
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
        .btn-success:hover {
            background: #16a34a;
            transform: translateY(-2px);
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
            width: 100%;
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
        .otp-verified-badge {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid rgba(34, 197, 94, 0.15);
        }
        .password-requirements {
            font-size: 12px;
            color: #64748b;
            margin-top: 5px;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .button-group .btn-secondary {
            width: auto;
            flex: 1;
        }
        .success-icon {
            font-size: 80px;
            display: block;
            margin-bottom: 15px;
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

        <div class="vp-container">
            <div class="vp-card">
                <?php if ($success && !$otp_verified): ?>
                    <span class="success-icon">✅</span>
                    <h2 style="color: #22c55e;">Success!</h2>
                    <p><?php echo $success; ?></p>
                    <p style="color: #64748b; font-size: 13px;">Redirecting to profile...</p>
                    <a href="profile.php" class="btn-primary">Go to Profile</a>
                <?php elseif (!$otp_verified): ?>
                    <div class="vp-icon">📱</div>
                    <h2>Verify OTP</h2>
                    <p>
                        Enter the 6-digit OTP sent to your email address.
                    </p>

                    <?php if ($error): ?>
                        <div class="message error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label>🔑 OTP Code</label>
                            <input type="text" name="otp" placeholder="000000" maxlength="6" required autofocus>
                        </div>
                        <button type="submit" class="btn-primary">✅ Verify OTP</button>
                    </form>

                    <div class="button-group" style="margin-top: 15px;">
                        <a href="change_password.php" class="btn-secondary">🔄 Resend OTP</a>
                        <a href="profile.php" class="btn-secondary">❌ Cancel</a>
                    </div>

                    <div style="margin-top: 15px; font-size: 12px; color: #64748b;">
                        OTP expires in 2 minutes • <?php echo 3 - ($_SESSION['password_otp_attempts'] ?? 0); ?> attempts remaining
                    </div>
                <?php else: ?>
                    <!-- OTP Verified - Show password change form -->
                    <div class="otp-verified-badge">✅ OTP Verified Successfully!</div>
                    <div class="vp-icon">🔑</div>
                    <h2>Set New Password</h2>
                    <p>Enter your new password below.</p>

                    <?php if ($error): ?>
                        <div class="message error"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label>🔒 New Password</label>
                            <input type="password" name="new_password" placeholder="Enter new password" required minlength="8">
                            <div class="password-requirements">Minimum 8 characters</div>
                        </div>
                        <div class="form-group">
                            <label>✅ Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="Confirm new password" required minlength="8">
                        </div>
                        <button type="submit" name="change_password" class="btn-success">💾 Change Password</button>
                    </form>

                    <div style="margin-top: 15px;">
                        <a href="profile.php" class="btn-secondary">❌ Cancel</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>