<?php
// dashboard.php - Fixed with database connection
require_once 'config.php';

// ===== DEBUG DATABASE CONNECTION =====
error_log("=== DASHBOARD DEBUG ===");
$test_conn = getDB();
if ($test_conn) {
    error_log("✅ Database connection successful!");
    $test_conn->close();
} else {
    error_log("❌ Database connection FAILED!");
}

// Check authentication - SIMPLE VERSION (no validateSession)
if (!isAuthenticated()) {
    header("Location: login.php");
    exit();
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$user_id = $_SESSION['user_id'];

// ===== TEST DATABASE CONNECTION =====
$db_connected = false;
$db_error = '';
$total_readings = 0;
$total_violations = 0;
$todays_readings = 0;
$latest_reading = null;

// Try to connect with detailed error reporting
try {
    $conn = getDB();
    
    if ($conn) {
        // Check if connection is alive
        if ($conn->ping()) {
            $db_connected = true;
            error_log("✅ Database ping successful!");
            
            // Get total readings
            $result = $conn->query("SELECT COUNT(*) as count FROM noise_readings");
            if ($result) {
                $row = $result->fetch_assoc();
                $total_readings = $row['count'] ?? 0;
            } else {
                error_log("⚠️ Query failed: " . $conn->error);
            }
            
            // Get today's readings
            $result = $conn->query("SELECT COUNT(*) as count FROM noise_readings WHERE DATE(created_at) = CURDATE()");
            if ($result) {
                $row = $result->fetch_assoc();
                $todays_readings = $row['count'] ?? 0;
            }
            
            // Get total violations (noise > threshold)
            $result = $conn->query("SELECT COUNT(*) as count FROM noise_readings WHERE noise_level > 150");
            if ($result) {
                $row = $result->fetch_assoc();
                $total_violations = $row['count'] ?? 0;
            }
            
            // Get latest reading
            $result = $conn->query("SELECT * FROM noise_readings ORDER BY id DESC LIMIT 1");
            if ($result && $result->num_rows > 0) {
                $latest_reading = $result->fetch_assoc();
            }
            
            $conn->close();
        } else {
            $db_error = "Connection ping failed";
            error_log("❌ Database ping failed!");
        }
    } else {
        $db_error = "getDB() returned null";
        error_log("❌ getDB() returned null!");
    }
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $db_connected = false;
    error_log("❌ Database exception: " . $e->getMessage());
}

// Log the final status
error_log("=== DB STATUS ===");
error_log("db_connected: " . ($db_connected ? 'true' : 'false'));
error_log("total_readings: " . $total_readings);
error_log("db_error: " . $db_error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Noise Monitor</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
        .alert-overlay.show { display: flex; }
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
            position: relative;
        }
        .alert-box .icon { font-size: 60px; margin-bottom: 15px; }
        .alert-box .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .alert-box .message { font-size: 16px; color: #94a3b8; margin-bottom: 25px; line-height: 1.6; }
        .alert-box .btn-close {
            background: #22c55e;
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .alert-box .btn-close:hover { background: #16a34a; transform: scale(1.05); }
        .alert-box.success .icon { color: #22c55e; }
        .alert-box.success .title { color: #22c55e; }
        .alert-box.success .btn-close { background: #22c55e; }
        .alert-box.success .btn-close:hover { background: #16a34a; }
        .alert-box.error .icon { color: #ef4444; }
        .alert-box.error .title { color: #ef4444; }
        .alert-box.error .btn-close { background: #ef4444; }
        .alert-box.error .btn-close:hover { background: #dc2626; }
        .alert-box.warning .icon { color: #eab308; }
        .alert-box.warning .title { color: #eab308; }
        .alert-box.warning .btn-close { background: #eab308; }
        .alert-box.warning .btn-close:hover { background: #ca8a04; }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* ===== BUTTONS ===== */
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .btn.small {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        .btn.small:hover { transform: translateY(-2px); opacity: 0.9; }
        .btn.warning.small {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid #f59e0b;
            color: #f59e0b;
        }
        .btn.warning.small:hover { background: rgba(245, 158, 11, 0.3); }
        .btn.info.small {
            background: rgba(56, 189, 248, 0.2);
            border: 1px solid #38bdf8;
            color: #38bdf8;
        }
        .btn.info.small:hover { background: rgba(56, 189, 248, 0.3); }
        .btn.success.small {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid #22c55e;
            color: #22c55e;
        }
        .btn.success.small:hover { background: rgba(34, 197, 94, 0.3); }
        .btn.danger.small {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
        .btn.danger.small:hover { background: rgba(239, 68, 68, 0.3); }

        /* ===== CONTROLS ===== */
        .control-group {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .control-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #a5b4fc;
        }
        .control-group .value-display {
            font-size: 20px;
            color: #22c55e;
            font-weight: bold;
        }
        input[type="range"] {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #334155;
            outline: none;
            -webkit-appearance: none;
            margin: 10px 0;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #6366f1;
            cursor: pointer;
        }
        input[type="range"]::-webkit-slider-thumb:hover {
            background: #4f46e5;
            transform: scale(1.1);
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        /* ===== STATUS INDICATORS ===== */
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-badge.connected {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            animation: pulseGreen 2s infinite;
        }
        .status-badge.disconnected {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        .status-badge.connecting {
            background: rgba(234, 179, 8, 0.2);
            color: #eab308;
            animation: pulseYellow 1s infinite;
        }

        @keyframes pulseGreen {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @keyframes pulseYellow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* ===== CONNECTION BAR ===== */
        .connection-bar {
            display: flex;
            justify-content: center;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            padding: 10px;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
        }
        .db-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 0, 0, 0.3);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        .db-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .db-indicator.connected {
            background: #22c55e;
            animation: pulseGreen 2s infinite;
        }
        .db-indicator.disconnected {
            background: #ef4444;
        }
        .db-indicator.checking {
            background: #eab308;
            animation: pulseYellow 1s infinite;
        }

        /* ===== STATS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: rgba(15, 23, 42, 0.8);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
        }
        .stat-card.reading { border-top: 3px solid #22c55e; }
        .stat-title {
            font-size: 14px;
            color: #94a3b8;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #f8fafc;
        }
        .stat-violation {
            font-size: 28px;
            font-weight: bold;
            color: #ef4444;
            margin-top: 10px;
        }
        .stat-label {
            font-size: 11px;
            color: #64748b;
            margin-top: 5px;
        }

        /* ===== CURRENT STATS ===== */
        .current-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        .stat-item {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }
        .stat-item label {
            display: block;
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #a5b4fc;
        }
        .highlight {
            font-size: 18px;
            font-weight: bold;
            color: #a5b4fc;
        }

        /* ===== NOISE DISPLAY ===== */
        .noise-value {
            text-align: center;
            font-size: 64px;
            font-weight: bold;
            margin: 20px 0;
            color: #f8fafc;
        }
        .percentage {
            text-align: center;
            font-size: 28px;
            color: #a5b4fc;
            margin-bottom: 20px;
        }
        .bar-container {
            background: #1e293b;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 20px 0;
        }
        .sound-bar {
            background: linear-gradient(90deg, #22c55e, #eab308, #ef4444);
            height: 100%;
            width: 0%;
            transition: width 0.1s;
        }
        .status {
            text-align: center;
            padding: 15px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 18px;
        }
        .status.quiet {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }
        .status.warning {
            background: rgba(234, 179, 8, 0.2);
            color: #eab308;
            animation: pulseWarning 1s infinite;
        }
        .status.noise {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            animation: pulseNoise 0.5s infinite;
        }
        @keyframes pulseWarning {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @keyframes pulseNoise {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* ===== INCIDENTS ===== */
        .incidents-list {
            background: #0f172a;
            border-radius: 12px;
            padding: 15px;
            max-height: 200px;
            overflow-y: auto;
        }
        .incident-item {
            padding: 10px;
            border-bottom: 1px solid #1e293b;
            color: #f87171;
            font-size: 13px;
        }
        .incident-item:last-child { border-bottom: none; }
        .incident-time {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 5px;
        }
        .empty-message {
            text-align: center;
            padding: 20px;
            color: #64748b;
        }

        /* ===== SERIAL MONITOR ===== */
        .serial-output {
            background: #0f172a;
            border-radius: 12px;
            padding: 15px;
            height: 150px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 11px;
            margin-bottom: 10px;
        }
        .serial-output div {
            margin-bottom: 5px;
            border-left: 2px solid #6366f1;
            padding-left: 8px;
        }

        /* ===== DB INFO ===== */
        .db-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .db-info-row span:first-child {
            color: #94a3b8;
        }
        .db-info-row strong {
            color: #a5b4fc;
        }
        #dbConnStatus.connected { color: #22c55e; }
        #dbConnStatus.disconnected { color: #ef4444; }
        #dbConnStatus.checking { color: #eab308; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .current-stats { grid-template-columns: repeat(2, 1fr); }
            .noise-value { font-size: 48px; }
            .connection-bar { flex-direction: column; gap: 10px; }
        }
        @media (max-width: 500px) {
            .alert-box { padding: 30px 25px; }
            .current-stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- ===== ALERT OVERLAY ===== -->
    <div id="alertOverlay" class="alert-overlay">
        <div id="alertBox" class="alert-box success">
            <div class="icon" id="alertIcon">✅</div>
            <div class="title" id="alertTitle">Success!</div>
            <div class="message" id="alertMessage">Operation completed successfully.</div>
            <button class="btn-close" onclick="closeAlert()">OK</button>
        </div>
    </div>

    <div class="container">
        <!-- ===== HEADER ===== -->
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h1>📚 Library Noise Monitor</h1>
                <div>
                    <span style="color: #a5b4fc; margin-right: 15px;">
                        👤 <?php echo htmlspecialchars($_SESSION['username']); ?>
                        <?php if ($is_admin): ?>
                            <span style="background: #ef4444; padding: 2px 10px; border-radius: 12px; font-size: 11px;">ADMIN</span>
                        <?php endif; ?>
                    </span>
                    <a href="profile.php" class="btn small" style="background: #22c55e; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">👤 Profile</a>
                    <?php if ($is_admin): ?>
                        <a href="admin.php" class="btn small" style="background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">⚙️ Admin</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn small" style="background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 10px;">Logout</a>
                </div>
            </div>
            
            <!-- Connection Bar -->
            <div class="connection-bar" style="margin-top: 15px;">
                <button id="connectBtn" class="btn">🔌 Connect to Arduino</button>
                <span id="statusText" class="status-badge <?php echo $db_connected ? 'connected' : 'disconnected'; ?>">
                    <?php echo $db_connected ? '✅ Connected' : ($db_error ? '⚠️ Error' : '⚫ Disconnected'); ?>
                </span>
                <div class="db-status">
                    <span id="dbIndicator" class="db-indicator <?php echo $db_connected ? 'connected' : 'disconnected'; ?>"></span>
                    <span id="dbStatusText">
                        <?php 
                        if ($db_connected) {
                            echo 'Database connected';
                        } elseif ($db_error) {
                            echo 'Error: ' . $db_error;
                        } else {
                            echo 'Database disconnected';
                        }
                        ?>
                    </span>
                </div>
                <button id="exportDataBtn" class="btn info">📥 Export</button>
                <button id="clearDataBtn" class="btn warning">🗑️ Clear</button>
            </div>
        </div>

        <!-- ===== REAL TIME NOISE LEVEL ===== -->
        <div class="card">
            <h3>📊 Real Time Noise Level</h3>
            <div class="noise-value"><span id="soundValue"><?php echo $latest_reading ? $latest_reading['noise_level'] : '0'; ?></span> / 1023</div>
            <div class="percentage"><span id="percentValue"><?php echo $latest_reading ? round(($latest_reading['noise_level'] / 1023) * 100) : '0'; ?></span>%</div>
            <div class="bar-container"><div id="soundBar" class="sound-bar" style="width: <?php echo $latest_reading ? round(($latest_reading['noise_level'] / 1023) * 100) : '0'; ?>%;"></div></div>
            <div id="statusBadge" class="status <?php 
                if ($latest_reading) {
                    $pct = round(($latest_reading['noise_level'] / 1023) * 100);
                    echo $pct < 30 ? 'quiet' : ($pct < 60 ? 'warning' : 'noise');
                } else {
                    echo 'quiet';
                }
            ?>">
                <?php 
                    if ($latest_reading) {
                        $pct = round(($latest_reading['noise_level'] / 1023) * 100);
                        echo $pct < 30 ? '🔇 QUIET' : ($pct < 60 ? '⚠️ MODERATE' : '🔊 LOUD');
                    } else {
                        echo '🔇 QUIET';
                    }
                ?>
            </div>
        </div>

        <!-- ===== DATABASE STATISTICS ===== -->
        <div class="stats-grid">
            <div class="stat-card reading">
                <div class="stat-title">📖 Reading Room</div>
                <div class="stat-value" id="reading-readings"><?php echo $total_readings; ?></div>
                <div class="stat-label">Total Readings</div>
                <div class="stat-violation" id="reading-violations"><?php echo $total_violations; ?></div>
                <div class="stat-label">Violations</div>
            </div>
            <div class="stat-card reading" style="border-top-color: #6366f1;">
                <div class="stat-title">📊 Today's Stats</div>
                <div class="stat-value" id="todays-readings"><?php echo $todays_readings ?? 0; ?></div>
                <div class="stat-label">Today's Readings</div>
                <div class="stat-violation" style="color: #a5b4fc; font-size: 20px;" id="latest-time">
                    <?php echo $latest_reading ? date('h:i A', strtotime($latest_reading['created_at'])) : 'No data'; ?>
                </div>
                <div class="stat-label">Latest Reading</div>
            </div>
        </div>

        <!-- ===== CURRENT MODE DETAILS ===== -->
        <div class="card">
            <h3>📍 Current Mode Details</h3>
            <div class="current-stats">
                <div class="stat-item">
                    <label>Active Mode</label>
                    <span id="currentModeDisplay" class="highlight">Reading Room</span>
                </div>
                <div class="stat-item">
                    <label>Readings Today</label>
                    <span id="currentReadings" class="stat-number"><?php echo $todays_readings ?? 0; ?></span>
                </div>
                <div class="stat-item">
                    <label>Violations Today</label>
                    <span id="currentViolations" class="stat-number"><?php echo $total_violations; ?></span>
                </div>
                <div class="stat-item">
                    <label>Average Noise</label>
                    <span id="currentAvg" class="stat-number"><?php echo $total_readings > 0 ? round($total_readings / ($total_readings > 0 ? 1 : 1)) : '0'; ?></span>
                </div>
            </div>
        </div>

        <!-- ===== ARDUINO LIVE STATS ===== -->
        <div class="card">
            <h3>📊 Arduino Live Stats</h3>
            <div class="current-stats">
                <div class="stat-item">
                    <label>Violations</label>
                    <span id="violationsCount" class="stat-number"><?php echo $total_violations; ?></span>
                </div>
                <div class="stat-item">
                    <label>Baseline</label>
                    <span id="baselineValue" class="stat-number">0</span>
                </div>
                <div class="stat-item">
                    <label>Sensitivity</label>
                    <span id="sensitivityValue" class="stat-number">1.0</span>
                </div>
                <div class="stat-item">
                    <label>Threshold</label>
                    <span id="thresholdDisplay" class="stat-number">150</span>
                </div>
            </div>
        </div>

        <!-- ===== DATABASE CONNECTION ===== -->
        <div class="card" id="dbConnectionCard">
            <h3>🔄 Database Status</h3>
            <div id="dbConnectionInfo">
                <div class="db-info-row"><span>Status:</span><strong id="dbConnStatus" class="<?php echo $db_connected ? 'connected' : 'disconnected'; ?>"><?php echo $db_connected ? '✅ Connected' : '❌ Disconnected'; ?></strong></div>
                <div class="db-info-row"><span>Host:</span><strong><?php echo DB_HOST; ?>:<?php echo DB_PORT; ?></strong></div>
                <div class="db-info-row"><span>Database:</span><strong><?php echo DB_NAME; ?></strong></div>
                <div class="db-info-row"><span>Total Readings:</span><strong><?php echo $total_readings; ?></strong></div>
                <div class="db-info-row"><span>Last Save:</span><strong id="lastSaveTime"><?php echo $latest_reading ? date('Y-m-d H:i:s', strtotime($latest_reading['created_at'])) : 'Never'; ?></strong></div>
            </div>
            <button id="testDbBtn" class="btn small">Test Connection</button>
        </div>

        <!-- ===== CONTROLS ===== -->
        <div class="card">
            <h3>⚙️ Controls</h3>
            
            <!-- Threshold Control -->
            <div class="control-group">
                <label>🔊 Threshold: <span id="thresholdValue" class="value-display">150</span></label>
                <input type="range" id="thresholdSlider" min="10" max="500" value="150">
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #64748b;">
                    <span>10</span>
                    <span>500</span>
                </div>
                <div class="btn-group">
                    <button id="applyThresholdBtn" class="btn small" style="background: #6366f1; color: white;">✅ Apply Threshold</button>
                    <button id="resetThresholdBtn" class="btn warning small">↩️ Reset to 150</button>
                </div>
            </div>
            
            <!-- Sensitivity Control -->
            <div class="control-group">
                <label>🎯 Sensitivity: x<span id="sensitivityVal" class="value-display">1.0</span></label>
                <input type="range" id="sensitivitySlider" min="0.1" max="5.0" step="0.1" value="1.0">
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #64748b;">
                    <span>0.1</span>
                    <span>5.0</span>
                </div>
                <div class="btn-group">
                    <button id="applySensitivityBtn" class="btn small" style="background: #6366f1; color: white;">✅ Apply Sensitivity</button>
                    <button id="resetSensitivityBtn" class="btn warning small">↩️ Reset to 1.0</button>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="button-group">
                <button id="resetViolationsBtn" class="btn warning small">🔄 Reset Violations</button>
                <button id="recalibrateBtn" class="btn info small">📡 Recalibrate</button>
                <button id="getStatusBtn" class="btn small" style="background: #6366f1; color: white;">📊 Get Status</button>
            </div>
        </div>

        <!-- ===== INCIDENTS LIST ===== -->
        <div class="card">
            <h3>⚠️ Recent Incidents</h3>
            <div id="incidentsList" class="incidents-list">
                <?php 
                // Fetch recent incidents
                try {
                    $conn = getDB();
                    if ($conn) {
                        $result = $conn->query("SELECT * FROM noise_readings WHERE noise_level > 150 ORDER BY id DESC LIMIT 10");
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo '<div class="incident-item">';
                                echo '<div class="incident-time">' . date('Y-m-d H:i:s', strtotime($row['created_at'])) . '</div>';
                                echo '🔊 Noise level: ' . $row['noise_level'] . ' / 1023';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="empty-message">No incidents recorded</div>';
                        }
                        $conn->close();
                    } else {
                        echo '<div class="empty-message">Cannot connect to database</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="empty-message">Error loading incidents: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                ?>
            </div>
        </div>

        <!-- ===== SERIAL MONITOR ===== -->
        <div class="card">
            <h3>📟 Serial Monitor</h3>
            <div id="serialOutput" class="serial-output">
                <div class="empty-message">Waiting for connection...</div>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button id="clearSerialBtn" class="btn small">Clear Log</button>
                <button id="autoScrollBtn" class="btn small success">📌 Auto Scroll: ON</button>
            </div>
        </div>
    </div>

    <!-- ===== JAVASCRIPT ===== -->
    <script>
        // ============================================================
        // API URL for Railway
        // ============================================================
        const API_URL = '/api.php';

        // ============================================================
        // SERIAL MONITOR
        // ============================================================
        function addSerialMessage(message) {
            const serialOutput = document.getElementById('serialOutput');
            if (!serialOutput) return;
            
            // Remove empty message if exists
            const emptyMsg = serialOutput.querySelector('.empty-message');
            if (emptyMsg) emptyMsg.remove();
            
            // Add new message
            const div = document.createElement('div');
            const timestamp = new Date().toLocaleTimeString();
            div.textContent = `[${timestamp}] ${message}`;
            
            // Color coding
            if (message.includes('❌') || message.includes('ERROR')) {
                div.style.color = '#ef4444';
            } else if (message.includes('✅')) {
                div.style.color = '#22c55e';
            } else if (message.includes('⚠️')) {
                div.style.color = '#eab308';
            } else if (message.includes('📤') || message.includes('📥') || message.includes('📡')) {
                div.style.color = '#38bdf8';
            } else {
                div.style.color = '#94a3b8';
            }
            
            serialOutput.appendChild(div);
            serialOutput.scrollTop = serialOutput.scrollHeight;
        }

        // ============================================================
        // ARDUINO CONNECTION FUNCTIONS
        // ============================================================

        // Global variables for serial connection
        let port = null;
        let isConnected = false;
        let reader = null;

        function delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        // ============================================================
        // CONNECT TO ARDUINO - MAIN FUNCTION
        // ============================================================
        async function connectSerial() {
            addSerialMessage('🔌 Connect button clicked!');
            addSerialMessage('📌 Checking Web Serial API support...');
            
            // Check if Web Serial is supported
            if (!('serial' in navigator)) {
                addSerialMessage('❌ Web Serial API is NOT supported in this browser.');
                addSerialMessage('📌 Please use Chrome or Edge with HTTPS.');
                showAlert('error', '❌', 'Not Supported', 'Web Serial API is not supported. Please use Chrome or Edge.');
                return;
            }
            
            addSerialMessage('✅ Web Serial API is supported!');
            
            try {
                addSerialMessage('🔌 Requesting serial port...');
                addSerialMessage('📌 Please select the COM port your Arduino is connected to.');
                
                // Request a serial port - this triggers the browser popup
                port = await navigator.serial.requestPort();
                
                addSerialMessage('📡 Opening connection at 9600 baud...');
                await port.open({ baudRate: 9600 });
                
                isConnected = true;
                
                // Update UI
                const statusText = document.getElementById('statusText');
                const connectBtn = document.getElementById('connectBtn');
                
                if (statusText) {
                    statusText.className = 'status-badge connected';
                    statusText.innerHTML = '🟢 Connected';
                }
                if (connectBtn) {
                    connectBtn.textContent = '✅ Connected';
                    connectBtn.disabled = true;
                    connectBtn.style.background = '#22c55e';
                }
                
                addSerialMessage('✅ Connected to Arduino!');
                addSerialMessage('📊 Waiting for data...');
                showAlert('success', '✅', 'Connected', 'Arduino connected successfully!');
                
                await delay(1000);
                
                // Send initial config
                await sendCommand('SET_THRESHOLD', 150);
                await delay(200);
                await sendCommand('SET_SENSITIVITY', 1.0);
                
                // Start reading
                readSerialData();
            } catch (err) {
                if (err.message && (err.message.includes('cancelled') || err.message.includes('cancel'))) {
                    addSerialMessage('⏹️ Connection cancelled by user.');
                } else {
                    addSerialMessage(`❌ Error: ${err.message}`);
                    showAlert('error', '❌', 'Connection Error', err.message);
                }
                isConnected = false;
                
                // Reset UI
                const statusText = document.getElementById('statusText');
                const connectBtn = document.getElementById('connectBtn');
                
                if (statusText) {
                    statusText.className = 'status-badge disconnected';
                    statusText.innerHTML = '⚫ Disconnected';
                }
                if (connectBtn) {
                    connectBtn.textContent = '🔌 Connect to Arduino';
                    connectBtn.disabled = false;
                    connectBtn.style.background = '';
                }
            }
        }

        // ============================================================
        // SEND COMMAND TO ARDUINO
        // ============================================================
        async function sendCommand(command, value) {
            if (!port || !isConnected) {
                addSerialMessage(`⚠️ Not connected. Command: ${command} ${value || ''}`);
                return false;
            }
            
            try {
                const writer = port.writable.getWriter();
                
                let msg;
                if (value !== undefined) {
                    msg = `CMD:{"command":"${command}","value":${value}}\n`;
                } else {
                    msg = `CMD:{"command":"${command}"}\n`;
                }
                
                console.log('📤 SENDING:', msg);
                addSerialMessage(`📤 Sending: ${command} ${value !== undefined ? '= ' + value : ''}`);
                
                const encoder = new TextEncoder();
                await writer.write(encoder.encode(msg));
                writer.releaseLock();
                
                addSerialMessage(`✅ Command sent successfully`);
                return true;
            } catch (err) {
                console.error('❌ Send error:', err);
                addSerialMessage(`❌ Send error: ${err.message}`);
                return false;
            }
        }

        // ============================================================
        // READ SERIAL DATA
        // ============================================================
        async function readSerialData() {
            try {
                const reader = port.readable.getReader();
                let buffer = "";
                
                addSerialMessage('📡 Reading serial data...');
                
                while (isConnected) {
                    try {
                        const { value, done } = await reader.read();
                        if (done) {
                            addSerialMessage('⚠️ Stream ended');
                            break;
                        }
                        
                        const text = new TextDecoder().decode(value);
                        buffer += text;
                        
                        let lines = buffer.split('\n');
                        buffer = lines.pop() || '';
                        
                        for (const line of lines) {
                            const trimmed = line.trim();
                            if (trimmed) {
                                console.log('📥 Received:', trimmed);
                                addSerialMessage(`📥 ${trimmed}`);
                                processLine(trimmed);
                            }
                        }
                    } catch (err) {
                        if (err.name === 'TypeError') {
                            break;
                        }
                        console.error('Read error:', err);
                    }
                }
            } catch (err) {
                addSerialMessage(`❌ Read error: ${err.message}`);
            }
            
            // Cleanup on disconnect
            isConnected = false;
            const statusText = document.getElementById('statusText');
            const connectBtn = document.getElementById('connectBtn');
            
            if (statusText) {
                statusText.className = 'status-badge disconnected';
                statusText.innerHTML = '⚫ Disconnected';
            }
            if (connectBtn) {
                connectBtn.textContent = '🔌 Connect to Arduino';
                connectBtn.disabled = false;
                connectBtn.style.background = '';
            }
            addSerialMessage('⚠️ Disconnected from Arduino');
        }

        // ============================================================
        // PROCESS INCOMING DATA
        // ============================================================
        function processLine(line) {
            if (!line) return;
            
            console.log('📥 RAW LINE:', line);
            
            // Command responses
            if (line.includes('"command"')) {
                try {
                    let jsonStr = line;
                    if (line.startsWith('CMD:')) {
                        jsonStr = line.substring(4).trim();
                    }
                    if (jsonStr.startsWith('{') && jsonStr.includes('}')) {
                        const jsonData = JSON.parse(jsonStr);
                        console.log('📊 Command response:', jsonData);
                        
                        if (jsonData.command === 'SET_THRESHOLD' && jsonData.value !== undefined) {
                            const thresholdDisplay = document.getElementById('thresholdDisplay');
                            const displayThreshold = document.getElementById('displayThreshold');
                            if (thresholdDisplay) thresholdDisplay.textContent = jsonData.value;
                            if (displayThreshold) displayThreshold.textContent = jsonData.value;
                            addSerialMessage(`✅ Threshold set to ${jsonData.value}`);
                            showAlert('success', '✅', 'Threshold Updated', `Threshold set to ${jsonData.value}`);
                        }
                        if (jsonData.command === 'SET_SENSITIVITY' && jsonData.value !== undefined) {
                            const sensitivityValue = document.getElementById('sensitivityValue');
                            const displaySensitivity = document.getElementById('displaySensitivity');
                            if (sensitivityValue) sensitivityValue.textContent = parseFloat(jsonData.value).toFixed(2);
                            if (displaySensitivity) displaySensitivity.textContent = parseFloat(jsonData.value).toFixed(1);
                            addSerialMessage(`✅ Sensitivity set to ${jsonData.value}`);
                            showAlert('success', '✅', 'Sensitivity Updated', `Sensitivity set to ${jsonData.value}`);
                        }
                        if (jsonData.command === 'RESET_VIOLATIONS') {
                            const violationsCount = document.getElementById('violationsCount');
                            const currentViolations = document.getElementById('currentViolations');
                            if (violationsCount) {
                                violationsCount.textContent = '0';
                                violationsCount.style.color = '#a5b4fc';
                            }
                            if (currentViolations) currentViolations.textContent = '0';
                            addSerialMessage('✅ Violations reset to 0');
                            showAlert('success', '🔄', 'Violations Reset', 'Violation counter reset to 0');
                        }
                        if (jsonData.command === 'STATUS') {
                            addSerialMessage(`📊 Status: Threshold=${jsonData.threshold}, Sensitivity=${jsonData.sensitivity}, Violations=${jsonData.violations}`);
                            const thresholdDisplay = document.getElementById('thresholdDisplay');
                            const sensitivityValue = document.getElementById('sensitivityValue');
                            const violationsCount = document.getElementById('violationsCount');
                            const baselineValue = document.getElementById('baselineValue');
                            if (thresholdDisplay) thresholdDisplay.textContent = jsonData.threshold;
                            if (sensitivityValue) sensitivityValue.textContent = jsonData.sensitivity;
                            if (violationsCount) {
                                violationsCount.textContent = jsonData.violations;
                                violationsCount.style.color = jsonData.violations > 0 ? '#ef4444' : '#a5b4fc';
                            }
                            if (baselineValue) baselineValue.textContent = jsonData.baseline;
                        }
                        if (jsonData.command === 'RECALIBRATE') {
                            addSerialMessage('✅ Recalibration complete!');
                            showAlert('success', '✅', 'Recalibrated', 'Sensor recalibration complete!');
                        }
                    }
                } catch (e) {
                    console.warn('JSON parse error:', e.message);
                }
                return;
            }
            
            // Data lines
            if (line.startsWith('DATA:')) {
                let data = line.substring(5).trim();
                console.log('📥 DATA:', data);
                
                let parts = data.split(',').filter(p => p.length > 0);
                console.log('📊 Parts:', parts);
                
                if (parts.length >= 7) {
                    const sound = parseInt(parts[0]) || 0;
                    const percent = parseInt(parts[1]) || 0;
                    const status = parts[2].toUpperCase() || 'QUIET';
                    const threshold = parseInt(parts[3]) || 150;
                    const violations = parseInt(parts[4]) || 0;
                    const baseline = parseInt(parts[5]) || 50;
                    const sensitivity = parseFloat(parts[6]) || 1.0;
                    
                    console.log(`📊 SOUND: ${sound}, PERCENT: ${percent}%, STATUS: ${status}, VIOLATIONS: ${violations}`);
                    
                    updateUI(sound, percent, status, violations);
                    
                    // Save to database
                    saveReading(sound, percent, status);
                    
                    if (status === 'NOISE' || status === 'NOISE!') {
                        addSerialMessage('🚨 NOISE DETECTED!');
                    }
                    return;
                }
                
                if (parts.length >= 3) {
                    const sound = parseInt(parts[0]) || 0;
                    const percent = parseInt(parts[1]) || 0;
                    const status = parts[2].toUpperCase() || 'QUIET';
                    
                    console.log(`📊 Simple parse: SOUND: ${sound}, PERCENT: ${percent}%, STATUS: ${status}`);
                    updateUI(sound, percent, status, 0);
                    saveReading(sound, percent, status);
                    return;
                }
            }
            
            if (line.length > 0 && line !== 'READY' && !line.includes('Noise Monitor Started')) {
                console.log('⚠️ Unknown line:', line);
            }
        }

        // ============================================================
        // UPDATE UI
        // ============================================================
        function updateUI(sound, percent, status, violations) {
            console.log('🔄 UPDATING UI:', { sound, percent, status, violations });
            
            const soundValue = document.getElementById('soundValue');
            const percentValue = document.getElementById('percentValue');
            const soundBar = document.getElementById('soundBar');
            const statusBadge = document.getElementById('statusBadge');
            const violationsCount = document.getElementById('violationsCount');
            
            if (soundValue) {
                soundValue.textContent = sound;
                soundValue.style.color = percent > 70 ? '#ef4444' : percent > 40 ? '#eab308' : '#22c55e';
            }
            
            if (percentValue) {
                percentValue.textContent = percent;
                percentValue.style.color = percent > 70 ? '#ef4444' : percent > 40 ? '#eab308' : '#a5b4fc';
            }
            
            if (soundBar) {
                const width = Math.min(percent, 100);
                soundBar.style.width = width + '%';
                soundBar.style.background = width > 70 ? 'linear-gradient(90deg, #ef4444, #dc2626)' : 
                                          width > 40 ? 'linear-gradient(90deg, #eab308, #f59e0b)' : 
                                          'linear-gradient(90deg, #22c55e, #16a34a)';
            }
            
            if (statusBadge) {
                if (status === 'NOISE' || status === 'NOISE!') {
                    statusBadge.textContent = '🔊 NOISE DETECTED!';
                    statusBadge.className = 'status noise';
                } else if (status === 'WARNING') {
                    statusBadge.textContent = '⚠️ WARNING';
                    statusBadge.className = 'status warning';
                } else {
                    statusBadge.textContent = '🔇 QUIET';
                    statusBadge.className = 'status quiet';
                }
            }
            
            if (violationsCount) {
                violationsCount.textContent = violations;
                violationsCount.style.color = violations > 0 ? '#ef4444' : '#a5b4fc';
            }
        }

        // ============================================================
        // SAVE READING TO DATABASE
        // ============================================================
        async function saveReading(sound, percent, status) {
            try {
                const data = {
                    sound: parseInt(sound),
                    percent: parseInt(percent),
                    status: status,
                    threshold: 150,
                    sensitivity: 1.0,
                    area: 'reading'
                };
                
                console.log('💾 SAVING READING:', data);
                
                const response = await fetch('/api.php?action=save_reading', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                console.log('💾 Save result:', result);
                
                if (result.success) {
                    const lastSaveTime = document.getElementById('lastSaveTime');
                    if (lastSaveTime) {
                        const now = new Date();
                        lastSaveTime.textContent = now.toLocaleTimeString();
                    }
                }
            } catch (error) {
                console.error('❌ Save error:', error);
            }
        }

        // ============================================================
        // ALERT SYSTEM
        // ============================================================
        window.showAlert = function(type, icon, title, message) {
            const overlay = document.getElementById('alertOverlay');
            const box = document.getElementById('alertBox');
            if (!overlay || !box) return;
            
            document.getElementById('alertIcon').textContent = icon;
            document.getElementById('alertTitle').textContent = title;
            document.getElementById('alertMessage').textContent = message;
            box.className = 'alert-box';
            box.classList.add(type);
            overlay.classList.add('show');
            setTimeout(window.closeAlert, 3000);
        };
        
        window.closeAlert = function() {
            const overlay = document.getElementById('alertOverlay');
            if (overlay) overlay.classList.remove('show');
        };

        // ============================================================
        // FETCH REAL-TIME DATA FROM DATABASE
        // ============================================================
        function fetchNoiseData() {
            fetch('/api.php?action=get_latest')
                .then(response => response.json())
                .then(data => {
                    if (data.noise_level !== undefined) {
                        const soundValue = document.getElementById('soundValue');
                        const percentValue = document.getElementById('percentValue');
                        const soundBar = document.getElementById('soundBar');
                        const statusBadge = document.getElementById('statusBadge');
                        const lastSaveTime = document.getElementById('lastSaveTime');
                        
                        if (soundValue) soundValue.textContent = data.noise_level;
                        if (percentValue) percentValue.textContent = data.percentage + '%';
                        if (soundBar) soundBar.style.width = data.percentage + '%';
                        
                        if (statusBadge) {
                            statusBadge.className = 'status';
                            if (data.percentage < 30) {
                                statusBadge.classList.add('quiet');
                                statusBadge.textContent = '🔇 QUIET';
                            } else if (data.percentage < 60) {
                                statusBadge.classList.add('warning');
                                statusBadge.textContent = '⚠️ MODERATE';
                            } else {
                                statusBadge.classList.add('noise');
                                statusBadge.textContent = '🔊 LOUD';
                            }
                        }
                        
                        if (data.time && lastSaveTime) {
                            lastSaveTime.textContent = data.time;
                        }
                    }
                })
                .catch(error => console.error('Error fetching data:', error));
        }

        // ============================================================
        // DOM CONTENT LOADED
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Dashboard loaded!');
            addSerialMessage('🎯 System Ready! Click "Connect to Arduino" to start');
            
            // Check Web Serial API support
            if ('serial' in navigator) {
                addSerialMessage('✅ Web Serial API is supported!');
                console.log('✅ Web Serial API is supported!');
            } else {
                addSerialMessage('❌ Web Serial API is NOT supported in this browser.');
                addSerialMessage('📌 Please use Chrome or Edge with HTTPS.');
            }
            
            // ===== CONNECT BUTTON - FIXED =====
            const connectBtn = document.getElementById('connectBtn');
            if (connectBtn) {
                console.log('✅ Connect button found!');
                connectBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('🔌 Connect button clicked!');
                    connectSerial();
                });
            } else {
                console.error('❌ Connect button NOT found!');
            }
            
            // ===== OTHER EVENT LISTENERS =====
            const thresholdSlider = document.getElementById('thresholdSlider');
            const thresholdValue = document.getElementById('thresholdValue');
            const sensitivitySlider = document.getElementById('sensitivitySlider');
            const sensitivityVal = document.getElementById('sensitivityVal');
            
            if (thresholdSlider) {
                thresholdSlider.addEventListener('input', function() {
                    const val = parseInt(this.value);
                    if (thresholdValue) thresholdValue.textContent = val;
                    const thresholdDisplay = document.getElementById('thresholdDisplay');
                    if (thresholdDisplay) thresholdDisplay.textContent = val;
                });
            }
            
            if (sensitivitySlider) {
                sensitivitySlider.addEventListener('input', function() {
                    const val = parseFloat(this.value);
                    if (sensitivityVal) sensitivityVal.textContent = val.toFixed(1);
                    const sensitivityValue = document.getElementById('sensitivityValue');
                    if (sensitivityValue) sensitivityValue.textContent = val.toFixed(1);
                });
            }
            
            // ---- Apply Threshold ----
            document.getElementById('applyThresholdBtn')?.addEventListener('click', function() {
                const val = parseInt(thresholdSlider?.value || 150);
                addSerialMessage(`📤 Applying threshold: ${val}`);
                if (typeof sendCommand === 'function') {
                    sendCommand('SET_THRESHOLD', val);
                }
                showAlert('success', '✅', 'Threshold Applied', `Threshold set to ${val}`);
            });
            
            // ---- Reset Threshold ----
            document.getElementById('resetThresholdBtn')?.addEventListener('click', function() {
                const val = 150;
                if (thresholdSlider) thresholdSlider.value = val;
                if (thresholdValue) thresholdValue.textContent = val;
                const thresholdDisplay = document.getElementById('thresholdDisplay');
                if (thresholdDisplay) thresholdDisplay.textContent = val;
                addSerialMessage(`↩️ Threshold reset to ${val}`);
                if (typeof sendCommand === 'function') {
                    sendCommand('SET_THRESHOLD', val);
                }
            });
            
            // ---- Apply Sensitivity ----
            document.getElementById('applySensitivityBtn')?.addEventListener('click', function() {
                const val = parseFloat(sensitivitySlider?.value || 1.0);
                addSerialMessage(`📤 Applying sensitivity: ${val}`);
                if (typeof sendCommand === 'function') {
                    sendCommand('SET_SENSITIVITY', val);
                }
                showAlert('success', '✅', 'Sensitivity Applied', `Sensitivity set to ${val}`);
            });
            
            // ---- Reset Sensitivity ----
            document.getElementById('resetSensitivityBtn')?.addEventListener('click', function() {
                const val = 1.0;
                if (sensitivitySlider) sensitivitySlider.value = val;
                if (sensitivityVal) sensitivityVal.textContent = val.toFixed(1);
                const sensitivityValue = document.getElementById('sensitivityValue');
                if (sensitivityValue) sensitivityValue.textContent = val.toFixed(1);
                addSerialMessage(`↩️ Sensitivity reset to ${val}`);
                if (typeof sendCommand === 'function') {
                    sendCommand('SET_SENSITIVITY', val);
                }
            });
            
            // ---- Reset Violations ----
            document.getElementById('resetViolationsBtn')?.addEventListener('click', function() {
                addSerialMessage('🔄 Resetting violations...');
                if (typeof sendCommand === 'function') {
                    sendCommand('RESET_VIOLATIONS');
                }
                const violationsCount = document.getElementById('violationsCount');
                if (violationsCount) {
                    violationsCount.textContent = '0';
                    violationsCount.style.color = '#a5b4fc';
                }
                showAlert('success', '✅', 'Reset Complete', 'Violations have been reset.');
            });
            
            // ---- Recalibrate ----
            document.getElementById('recalibrateBtn')?.addEventListener('click', function() {
                addSerialMessage('📡 Recalibrating sensor...');
                if (typeof sendCommand === 'function') {
                    sendCommand('RECALIBRATE');
                }
                showAlert('warning', '📡', 'Recalibrating', 'Please keep the area quiet for 10 seconds...');
            });
            
            // ---- Get Status ----
            document.getElementById('getStatusBtn')?.addEventListener('click', function() {
                addSerialMessage('📊 Getting status...');
                if (typeof sendCommand === 'function') {
                    sendCommand('STATUS');
                }
            });
            
            // ---- Clear Serial ----
            document.getElementById('clearSerialBtn')?.addEventListener('click', function() {
                const serialOutput = document.getElementById('serialOutput');
                if (serialOutput) {
                    serialOutput.innerHTML = '<div class="empty-message">Cleared...</div>';
                }
            });
            
            // ---- Export ----
            document.getElementById('exportDataBtn')?.addEventListener('click', function() {
                showAlert('info', '📊', 'Export Data', 'Check your MySQL database: noise_monitor table');
            });
            
            // ---- Clear Data ----
            document.getElementById('clearDataBtn')?.addEventListener('click', function() {
                if (confirm('⚠️ WARNING: This will delete ALL data from database! Are you sure?')) {
                    fetch('/api.php?action=clear_data', { method: 'DELETE' })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                showAlert('success', '✅', 'Data Cleared', 'All data has been cleared!');
                                addSerialMessage('✅ Database cleared');
                            } else {
                                showAlert('error', '❌', 'Clear Failed', result.error || 'Unknown error');
                            }
                        })
                        .catch(error => {
                            showAlert('error', '❌', 'Clear Failed', 'Make sure database is connected.');
                        });
                }
            });
            
            // ---- Test DB ----
            document.getElementById('testDbBtn')?.addEventListener('click', function() {
                fetch('/api.php?action=test')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'ok') {
                            showAlert('success', '✅', 'Database Connected', 'Connection is working!');
                            addSerialMessage('✅ Database connection test passed!');
                        } else {
                            showAlert('error', '❌', 'Database Error', data.message || 'Connection failed');
                        }
                    })
                    .catch(error => {
                        showAlert('error', '❌', 'Database Error', 'Could not connect to database.');
                    });
            });
            
            // ===== FETCH REAL-TIME DATA =====
            setInterval(fetchNoiseData, 3000);
            fetchNoiseData();
            
            console.log('✅ Dashboard initialized!');
        });

        // ============================================================
        // EXPOSE FUNCTIONS GLOBALLY
        // ============================================================
        window.connectSerial = connectSerial;
        window.sendCommand = sendCommand;
        window.addSerialMessage = addSerialMessage;
        window.showAlert = showAlert;
        window.closeAlert = closeAlert;
    </script>
</body>
</html>
