<?php
session_start();
require_once 'config.php';

// Check if user is logged in and verified
if (!isAuthenticated()) {
    header("Location: login.php");
    exit();
}

// Check if password change is verified
if (!isset($_SESSION['password_change_verified']) || $_SESSION['password_change_verified'] !== true) {
    header("Location: verify_password_otp.php");
    exit();
}

// Check if verification is still valid (10 minute window)
if (time() - $_SESSION['password_change_verified_at'] > 600) {
    unset($_SESSION['password_change_verified']);
    unset($_SESSION['password_change_verified_at']);
    header("Location: verify_password_otp.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $user_id = $_SESSION['user_id'];
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        $conn = getDB();
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        
        if ($stmt->execute()) {
            $success = "✅ Password changed successfully!";
            // Clear verification session
            unset($_SESSION['password_change_verified']);
            unset($_SESSION['password_change_verified_at']);
        } else {
            $error = "❌ Failed to change password. Please try again.";
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <style>
        body { font-family: Arial; background: #0f172a; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #1e293b; padding: 40px; border-radius: 20px; max-width: 400px; width: 100%; }
        h2 { color: #e2e8f0; text-align: center; }
        .form-group { margin: 20px 0; }
        label { color: #94a3b8; display: block; margin-bottom: 5px; }
        input { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
        button { width: 100%; padding: 14px; background: #6366f1; color: white; border: none; border-radius: 10px; font-size: 16px; cursor: pointer; }
        .error { color: #ef4444; background: rgba(239,68,68,0.1); padding: 10px; border-radius: 10px; margin: 10px 0; }
        .success { color: #22c55e; background: rgba(34,197,94,0.1); padding: 10px; border-radius: 10px; margin: 10px 0; }
        .back { color: #64748b; text-decoration: none; display: block; text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔑 Change Password</h2>
        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit">Change Password</button>
        </form>
        <a href="profile.php" class="back">← Back to Profile</a>
    </div>
</body>
</html>
