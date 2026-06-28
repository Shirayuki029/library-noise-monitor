<?php
require_once 'config.php';
requireAdmin();

$conn = getDB();
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($action === 'toggle_status') {
        $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND id != ?");
        $stmt->bind_param("ii", $user_id, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $message = 'User status updated successfully';
            logActivity($_SESSION['user_id'], 'admin_toggle_user', "Toggled status for user ID: $user_id");
        }
        $stmt->close();
    }
    
    elseif ($action === 'delete_user') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND id != ?");
        $stmt->bind_param("ii", $user_id, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $message = 'User deleted successfully';
            logActivity($_SESSION['user_id'], 'admin_delete_user', "Deleted user ID: $user_id");
        }
        $stmt->close();
    }
    
    elseif ($action === 'make_admin') {
        $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ? AND id != ?");
        $stmt->bind_param("ii", $user_id, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $message = 'User promoted to admin';
            logActivity($_SESSION['user_id'], 'admin_make_admin', "Promoted user ID: $user_id to admin");
        }
        $stmt->close();
    }
    
    elseif ($action === 'remove_admin') {
        $stmt = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ? AND id != ?");
        $stmt->bind_param("ii", $user_id, $_SESSION['user_id']);
        if ($stmt->execute()) {
            $message = 'Admin role removed';
            logActivity($_SESSION['user_id'], 'admin_remove_admin', "Removed admin from user ID: $user_id");
        }
        $stmt->close();
    }
}

$users = getAllUsers();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-nav { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .admin-nav a { background: rgba(99, 102, 241, 0.2); padding: 12px 24px; border-radius: 10px; text-decoration: none; color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.3); transition: all 0.3s; }
        .admin-nav a:hover { background: #6366f1; color: white; }
        .admin-nav a.active { background: #6366f1; color: white; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 16px; color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.1); }
        td { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        .status-badge.active { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .status-badge.inactive { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .role-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        .role-badge.admin { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .role-badge.user { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; }
        .btn-sm { padding: 4px 12px; font-size: 11px; border: none; border-radius: 6px; cursor: pointer; margin: 2px; }
        .btn-sm.success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .btn-sm.danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .btn-sm.warning { background: rgba(234, 179, 8, 0.2); color: #eab308; }
        .message { padding: 12px; border-radius: 10px; margin-bottom: 20px; }
        .message.success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .message.error { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h1>👥 User Management</h1>
                <div>
                    <a href="dashboard.php" class="btn small" style="background: #22c55e; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">📊 Dashboard</a>
                    <a href="logout.php" class="btn small" style="background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">Logout</a>
                </div>
            </div>
        </div>

        <div class="admin-nav">
            <a href="admin.php">📊 Overview</a>
            <a href="admin_users.php" class="active">👥 Manage Users</a>
            <a href="admin_logs.php">📝 Activity Logs</a>
        </div>

        <?php if ($message): ?>
            <div class="message success">✅ <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error">❌ <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>All Users</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge <?php echo $user['role']; ?>">
                                    <?php echo strtoupper($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $user['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                </span>
                            </td>
                            <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <button type="submit" class="btn-sm <?php echo $user['is_active'] ? 'warning' : 'success'; ?>">
                                            <?php echo $user['is_active'] ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    </form>
                                    
                                    <?php if ($user['role'] === 'user'): ?>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="make_admin">
                                            <button type="submit" class="btn-sm danger">Make Admin</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="remove_admin">
                                            <button type="submit" class="btn-sm warning">Remove Admin</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline-block;" onsubmit="return confirm('Delete this user permanently?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <button type="submit" class="btn-sm danger">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #64748b; font-size: 12px;">(You)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>