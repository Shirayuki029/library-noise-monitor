<?php
// config.php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'noise_monitor');

// OTP Configuration
define('OTP_EXPIRY', 120); // 5 minutes

// ===== ADDED FOR C1: SESSION TIMEOUT =====
define('SESSION_TIMEOUT', 1800); // 30 minutes (1800 seconds)

// ===== ADDED FOR C1: CHECK SESSION TIMEOUT =====
function checkSessionTimeout() {
    // Check if session exists
    if (isset($_SESSION['last_activity'])) {
        // Calculate idle time
        $inactive_time = time() - $_SESSION['last_activity'];
        
        // If idle time exceeds timeout
        if ($inactive_time > SESSION_TIMEOUT) {
            // Clear session
            session_unset();
            session_destroy();
            
            // Redirect to login with timeout message
            header("Location: login.php?timeout=1");
            exit();
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

// Database connection
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Check if user is authenticated
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// ===== MODIFIED FOR C1: Added checkSessionTimeout() =====
// Require authentication
function requireAuth() {
    checkSessionTimeout(); // <-- ADDED FOR C1
    if (!isAuthenticated()) {
        header("Location: login.php");
        exit();
    }
}

// ===== MODIFIED FOR C1: Added checkSessionTimeout() =====
// Require admin
function requireAdmin() {
    checkSessionTimeout(); // <-- ADDED FOR C1
    requireAuth();
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}

// Generate OTP
function generateOTP() {
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// Log user activity
function logActivity($user_id, $action, $details = '') {
    $conn = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $conn->prepare("INSERT INTO user_activity (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Get user by ID
function getUser($id) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $user;
}

// Get all users (admin only)
function getAllUsers() {
    $conn = getDB();
    $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $conn->close();
    return $users;
}
?>