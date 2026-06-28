<?php
require_once 'config.php';
requireAdmin();

$conn = getDB();
$result = $conn->query("
    SELECT a.*, u.username 
    FROM user_activity a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.timestamp DESC 
    LIMIT 100
");
$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-nav { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .admin-nav a { background: rgba(99, 102, 241, 0.2); padding: 12px 24px; border-radius: 10px; text-decoration: none; color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.3); transition: all 0.3s; }
        .admin-nav a:hover { background: #6366f1; color: white; }
        .admin-nav a.active { background: #6366f1; color: white; }
        .log-entry { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .log-entry .user { color: #a5b4fc; font-weight: bold; }
        .log-entry .action { color: #f8fafc; }
        .log-entry .time { color: #64748b; font-size: 12px; float: right; }
        .log-entry .details { color: #94a3b8; font-size: 13px; margin-top: 4px; }
        .log-entry .ip { color: #64748b; font-size: 11px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h1>📝 Activity Logs</h1>
                <div>
                    <a href="dashboard.php" class="btn small" style="background: #22c55e; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">📊 Dashboard</a>
                    <a href="logout.php" class="btn small" style="background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">Logout</a>
                </div>
            </div>
        </div>

        <div class="admin-nav">
            <a href="admin.php">📊 Overview</a>
            <a href="admin_users.php">👥 Manage Users</a>
            <a href="admin_logs.php" class="active">📝 Activity Logs</a>
        </div>

        <div class="card">
            <h3>Recent Activity (Last 100 entries)</h3>
            <div>
                <?php if (empty($logs)): ?>
                    <div class="empty-message">No activity logs found</div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="log-entry">
                            <span class="user"><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></span>
                            <span class="action"><?php echo htmlspecialchars($log['action']); ?></span>
                            <span class="time"><?php echo $log['timestamp']; ?></span>
                            <?php if ($log['details']): ?>
                                <div class="details"><?php echo htmlspecialchars($log['details']); ?></div>
                            <?php endif; ?>
                            <div class="ip">IP: <?php echo htmlspecialchars($log['ip_address']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>