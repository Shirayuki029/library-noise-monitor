<?php
// dashboard.php - Fixed with database connection
require_once 'config.php';

// Check authentication
if (!isAuthenticated()) {
    header("Location: login.php");
    exit();
}

// Validate session (one login only)
if (!validateSession()) {
    session_unset();
    session_destroy();
    header("Location: login.php?message=logged_out_elsewhere");
    exit();
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$user_id = $_SESSION['user_id'];

// ===== TEST DATABASE CONNECTION =====
$db_connected = false;
$db_error = '';
$total_readings = 0;
$total_violations = 0;
$latest_reading = null;

try {
    $conn = getDB();
    if ($conn) {
        $db_connected = true;
        
        // Get total readings
        $result = $conn->query("SELECT COUNT(*) as count FROM noise_readings");
        if ($result) {
            $row = $result->fetch_assoc();
            $total_readings = $row['count'] ?? 0;
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
    }
} catch (Exception $e) {
    $db_error = $e->getMessage();
    $db_connected = false;
}
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
                    <?php echo $db_connected ? '✅ Connected' : '⚫ Disconnected'; ?>
                </span>
                <div class="db-status">
                    <span id="dbIndicator" class="db-indicator <?php echo $db_connected ? 'connected' : 'disconnected'; ?>"></span>
                    <span id="dbStatusText"><?php echo $db_connected ? 'Database connected' : ($db_error ? 'Error: ' . $db_error : 'Database disconnected'); ?></span>
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
                } catch (Exception $e) {
                    echo '<div class="empty-message">Error loading incidents</div>';
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
    <script src="script.js"></script>
    <script>
        // ============================================================
        // DASHBOARD - FIXED
        // ============================================================
        
        document.addEventListener('DOMContentLoaded', function() {
            
            // ===== DOM REFERENCES =====
            const thresholdSlider = document.getElementById('thresholdSlider');
            const thresholdValue = document.getElementById('thresholdValue');
            const sensitivitySlider = document.getElementById('sensitivitySlider');
            const sensitivityVal = document.getElementById('sensitivityVal');
            const thresholdDisplay = document.getElementById('thresholdDisplay');
            const sensitivityValue = document.getElementById('sensitivityValue');
            
            // ===== ALERT SYSTEM =====
            window.showAlert = function(type, icon, title, message) {
                const overlay = document.getElementById('alertOverlay');
                const box = document.getElementById('alertBox');
                document.getElementById('alertIcon').textContent = icon;
                document.getElementById('alertTitle').textContent = title;
                document.getElementById('alertMessage').textContent = message;
                box.className = 'alert-box';
                box.classList.add(type);
                overlay.classList.add('show');
                setTimeout(window.closeAlert, 5000);
            };
            
            window.closeAlert = function() {
                document.getElementById('alertOverlay').classList.remove('show');
            };
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') window.closeAlert();
            });
            document.getElementById('alertOverlay').addEventListener('click', function(e) {
                if (e.target === this) window.closeAlert();
            });
            
            // ===== SLIDER DISPLAY UPDATES =====
            if (thresholdSlider) {
                thresholdSlider.addEventListener('input', function() {
                    const val = parseInt(this.value);
                    thresholdValue.textContent = val;
                    if (thresholdDisplay) thresholdDisplay.textContent = val;
                });
            }
            
            if (sensitivitySlider) {
                sensitivitySlider.addEventListener('input', function() {
                    const val = parseFloat(this.value);
                    sensitivityVal.textContent = val.toFixed(1);
                    if (sensitivityValue) sensitivityValue.textContent = val.toFixed(1);
                });
            }
            
            // ============================================================
            // ===== CONTROL BUTTONS =====
            // ============================================================
            
            // ---- Apply Threshold ----
            document.getElementById('applyThresholdBtn').addEventListener('click', function() {
                const val = parseInt(thresholdSlider.value);
                addSerialMessage(`📤 Applying threshold: ${val}`);
                if (typeof sendCommand === 'function') {
                    sendCommand('SET_THRESHOLD', val);
                }
                showAlert('success', '✅', 'Threshold Applied', `Threshold set to ${val}`);
            });
            
            // ---- Reset Threshold ----
            document.getElementById('resetThresholdBtn').addEventListener('click', function() {
                const val = 150;
                thresholdSlider.value = val;
                thresholdValue.textContent = val;
                if (thresholdDisplay) thresholdDisplay.textContent = val;
                addSerialMessage(`↩️ Threshold reset to ${val}`);
                if (typeof sendCommand === 'function') {
                    sendCommand('SET_THRESHOLD', val);
                }
            });
            
            // ---- Apply Sensitivity ----
            document.getElementById('applySensitivityBtn').addEventListener('click', function() {
                const val = parseFloat(sensitivitySlider.value);
                addSerialMessage(`📤 Applying sensitivity: ${val}`);
                if (typeof sendCommand === 'function') {
                    sendCommand('SET_SENSITIVITY', val);
                }
                showAlert('success', '✅', 'Sensitivity Applied', `Sensitivity set to ${val}`);
            });
            
            // ---- Reset Sensitivity ----
            document.getElementById('resetSensitivityBtn').addEventListener('click', function() {
                const val = 1.0;
                sensitivitySlider.value = val;
                sensitivityVal.textContent = val.toFixed(1);
                if (sensitivityValue) sensitivityValue.textContent = val.toFixed(1);
                addSerialMessage(`↩️ Sensitivity reset to ${val}`);
                if (typeof sendCommand === 'function') {
                    sendCommand('SET_SENSITIVITY', val);
                }
            });
            
            // ---- Reset Violations ----
            document.getElementById('resetViolationsBtn').addEventListener('click', function() {
                addSerialMessage('🔄 Resetting violations...');
                if (typeof sendCommand === 'function') {
                    sendCommand('RESET_VIOLATIONS');
                }
                document.getElementById('violationsCount').textContent = '0';
                showAlert('success', '✅', 'Reset Complete', 'Violations have been reset.');
            });
            
            // ---- Recalibrate ----
            document.getElementById('recalibrateBtn').addEventListener('click', function() {
                addSerialMessage('📡 Recalibrating sensor...');
                showAlert('warning', '📡', 'Recalibrating', 'Please keep the area quiet for 10 seconds...');
                if (typeof sendCommand === 'function') {
                    sendCommand('RECALIBRATE');
                }
            });
            
            // ---- Get Status ----
            document.getElementById('getStatusBtn').addEventListener('click', function() {
                addSerialMessage('📊 Getting status...');
                if (typeof sendCommand === 'function') {
                    sendCommand('STATUS');
                }
            });
            
            // ---- Clear Serial ----
            document.getElementById('clearSerialBtn').addEventListener('click', function() {
                const serialOutput = document.getElementById('serialOutput');
                serialOutput.innerHTML = '<div class="empty-message">Cleared...</div>';
            });
            
            // ---- Export ----
            document.getElementById('exportDataBtn').addEventListener('click', function() {
                showAlert('info', '📊', 'Export Data', 'Check your MySQL database: noise_monitor table');
            });
            
            // ---- Clear Data ----
            document.getElementById('clearDataBtn').addEventListener('click', function() {
                if (confirm('⚠️ WARNING: This will delete ALL data from database! Are you sure?')) {
                    if (typeof clearData === 'function') {
                        clearData();
                    }
                }
            });
            
            // ---- Test DB ----
            document.getElementById('testDbBtn').addEventListener('click', function() {
                if (typeof testDatabaseConnection === 'function') {
                    testDatabaseConnection();
                } else {
                    showAlert('info', '🔄', 'DB Status', 'Database is ' + 
                        (<?php echo $db_connected ? 'true' : 'false'; ?> ? '✅ Connected' : '❌ Disconnected'));
                }
            });
            
            // ===== FETCH REAL-TIME DATA =====
            function fetchNoiseData() {
                fetch('api.php?action=get_latest')
                    .then(response => response.json())
                    .then(data => {
                        if (data.noise_level !== undefined) {
                            document.getElementById('soundValue').textContent = data.noise_level;
                            document.getElementById('percentValue').textContent = data.percentage + '%';
                            document.getElementById('soundBar').style.width = data.percentage + '%';
                            
                            const badge = document.getElementById('statusBadge');
                            badge.className = 'status';
                            if (data.percentage < 30) {
                                badge.classList.add('quiet');
                                badge.textContent = '🔇 QUIET';
                            } else if (data.percentage < 60) {
                                badge.classList.add('warning');
                                badge.textContent = '⚠️ MODERATE';
                            } else {
                                badge.classList.add('noise');
                                badge.textContent = '🔊 LOUD';
                            }
                            
                            // Update time
                            if (data.time) {
                                document.getElementById('lastSaveTime').textContent = data.time;
                            }
                        }
                    })
                    .catch(error => console.error('Error fetching data:', error));
            }
            
            // Fetch every 3 seconds
            setInterval(fetchNoiseData, 3000);
            fetchNoiseData();
            
            console.log('✅ Dashboard initialized!');
        });
    </script>
</body>
</html>
