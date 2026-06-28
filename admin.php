<?php
require_once 'config.php';
requireAdmin();

$conn = getDB();

// Get stats
$user_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$reading_count = $conn->query("SELECT COUNT(*) as count FROM noise_readings")->fetch_assoc()['count'];
$incident_count = $conn->query("SELECT COUNT(*) as count FROM noise_incidents")->fetch_assoc()['count'];
$active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .admin-card { background: rgba(15, 23, 42, 0.8); border-radius: 16px; padding: 20px; text-align: center; border-top: 3px solid #6366f1; }
        .admin-card .number { font-size: 36px; font-weight: bold; color: #a5b4fc; }
        .admin-card .label { font-size: 14px; color: #94a3b8; margin-top: 8px; }
        .admin-nav { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .admin-nav a { background: rgba(99, 102, 241, 0.2); padding: 12px 24px; border-radius: 10px; text-decoration: none; color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.3); transition: all 0.3s; }
        .admin-nav a:hover { background: #6366f1; color: white; }
        .admin-nav a.active { background: #6366f1; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h1>⚙️ Admin Panel</h1>
                <div>
                    <span style="color: #a5b4fc; margin-right: 15px;">👤 Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="dashboard.php" class="btn small" style="background: #22c55e; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">📊 Dashboard</a>
                    <a href="logout.php" class="btn small" style="background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">Logout</a>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="admin-grid">
            <div class="admin-card"><div class="number"><?php echo $user_count; ?></div><div class="label">Total Users</div></div>
            <div class="admin-card"><div class="number"><?php echo $active_users; ?></div><div class="label">Active Users</div></div>
            <div class="admin-card"><div class="number"><?php echo $reading_count; ?></div><div class="label">Total Readings</div></div>
            <div class="admin-card"><div class="number"><?php echo $incident_count; ?></div><div class="label">Total Incidents</div></div>
        </div>

        <!-- Admin Navigation -->
        <div class="admin-nav">
            <a href="admin.php" class="active">📊 Overview</a>
            <a href="admin_users.php">👥 Manage Users</a>
            <a href="admin_logs.php">📝 Activity Logs</a>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <h3>🛠️ Quick Actions</h3>
            <div class="button-group">
                <a href="admin_users.php" class="btn" style="text-decoration: none;">👥 Manage Users</a>
                <a href="admin_logs.php" class="btn info" style="text-decoration: none;">📝 View Logs</a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <h3>📋 Recent Activity</h3>
            <div id="recentActivity" style="max-height: 300px; overflow-y: auto;">
                <?php
                $conn = getDB();
                $result = $conn->query("
                    SELECT a.*, u.username 
                    FROM user_activity a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    ORDER BY a.timestamp DESC 
                    LIMIT 20
                ");
                
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<div style='padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05);'>";
                        echo "<strong>{$row['username']}</strong> - {$row['action']}";
                        echo "<span style='color: #64748b; font-size: 12px; float: right;'>{$row['timestamp']}</span>";
                        if ($row['details']) {
                            echo "<div style='color: #94a3b8; font-size: 12px;'>{$row['details']}</div>";
                        }
                        echo "</div>";
                    }
                } else {
                    echo "<div class='empty-message'>No activity recorded</div>";
                }
                $conn->close();
                ?>
            </div>
        </div>
    </div>
</body>
</html>