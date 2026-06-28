<?php
require_once 'config.php';
requireAuth();

$conn = getDB();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($username) || empty($email)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        // Check if username/email already taken by another user
        $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $check->bind_param("ssi", $username, $email, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Username or email already taken';
        } else {
            $update = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $update->bind_param("ssi", $username, $email, $user_id);
            if ($update->execute()) {
                $_SESSION['username'] = $username;
                $message = '✅ Profile updated successfully!';
                // Refresh user data
                $user['username'] = $username;
                $user['email'] = $email;
            } else {
                $error = 'Update failed: ' . $conn->error;
            }
            $update->close();
        }
        $check->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Library Noise Monitor</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container {
            max-width: 700px;
            margin: 0 auto;
        }
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-avatar {
            font-size: 80px;
            display: block;
            margin-bottom: 10px;
        }
        .profile-section {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .profile-section h3 {
            color: #a5b4fc;
            margin-bottom: 20px;
            font-size: 18px;
        }
        .form-group {
            margin-bottom: 18px;
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
        .form-group input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .btn-save {
            background: #6366f1;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-save:hover {
            background: #4f46e5;
            transform: translateY(-2px);
        }
        .btn-change-password {
            background: rgba(234, 179, 8, 0.2);
            color: #eab308;
            border: 1px solid #eab308;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-change-password:hover {
            background: rgba(234, 179, 8, 0.3);
            transform: translateY(-2px);
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
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .info-row .label {
            color: #94a3b8;
        }
        .info-row .value {
            color: #f8fafc;
            font-weight: 500;
        }
        .role-badge {
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
            padding: 2px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .role-badge.admin {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h1>👤 My Profile</h1>
                <div>
                    <a href="dashboard.php" class="btn small" style="background: #22c55e; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">📊 Dashboard</a>
                    <a href="logout.php" class="btn small" style="background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">Logout</a>
                </div>
            </div>
        </div>

        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <span class="profile-avatar">👤</span>
                <h2 style="color: #f8fafc;"><?php echo htmlspecialchars($user['username']); ?></h2>
                <span class="role-badge <?php echo $user['role']; ?>">
                    <?php echo strtoupper($user['role']); ?>
                </span>
                <p style="color: #94a3b8; margin-top: 5px;">
                    Member since <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                </p>
            </div>

            <?php if ($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Edit Profile -->
            <div class="profile-section">
                <h3>✏️ Edit Profile</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>👤 Username</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>📧 Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="btn-save">💾 Save Changes</button>
                </form>
            </div>

            <!-- Account Info -->
            <div class="profile-section">
                <h3>📋 Account Information</h3>
                <div class="info-row">
                    <span class="label">User ID</span>
                    <span class="value">#<?php echo $user['id']; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Role</span>
                    <span class="value"><?php echo ucfirst($user['role']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Status</span>
                    <span class="value" style="color: <?php echo $user['is_active'] ? '#22c55e' : '#ef4444'; ?>;">
                        <?php echo $user['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">Last Login</span>
                    <span class="value"><?php echo $user['last_login'] ? date('F d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Account Created</span>
                    <span class="value"><?php echo date('F d, Y H:i', strtotime($user['created_at'])); ?></span>
                </div>
            </div>

            <!-- Password Change -->
            <div class="profile-section">
                <h3>🔑 Change Password</h3>
                <p style="color: #94a3b8; font-size: 14px; margin-bottom: 15px;">
                    You will receive an OTP code to your email for verification.
                </p>
                <a href="change_password.php" class="btn-change-password">🔐 Change Password</a>
            </div>
        </div>
    </div>
</body>
</html>