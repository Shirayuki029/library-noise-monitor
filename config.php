<?php
// config.php

// ===== SESSION MANAGEMENT =====
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== RAILWAY DATABASE CONFIGURATION =====
$host = getenv('MYSQLHOST') ?: 'reseau.proxy.rlwy.net';
$port = getenv('MYSQLPORT') ?: 46901;
$dbname = getenv('MYSQLDATABASE') ?: 'noise_monitor';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'zVsqVputbGKVtSvUkDJJfnZRpcYqkBFl';

// Database configuration
define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASS', $pass);
define('DB_NAME', $dbname);
define('DB_PORT', (int)$port); // Cast to integer

// OTP Configuration
define('OTP_EXPIRY', 300); // 5 minutes (not 2 minutes)

// ===== SESSION TIMEOUT =====
define('SESSION_TIMEOUT', 1800); // 30 minutes

// ===== CHECK SESSION TIMEOUT =====
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        if ($inactive_time > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header("Location: login.php?timeout=1");
            exit();
        }
    }
    $_SESSION['last_activity'] = time();
}

// ===== DATABASE CONNECTION =====
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Database connection failed. Please check logs.");
    }
    
    return $conn;
}

// ===== AUTHENTICATION FUNCTIONS =====
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAuth() {
    checkSessionTimeout();
    if (!isAuthenticated()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdmin() {
    checkSessionTimeout();
    requireAuth();
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}

// ===== VALIDATE SESSION AGAINST DATABASE (One Login Only) =====
function validateSession() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $session_id = $_SESSION['session_id'];
    
    $conn = getDB();
    $stmt = $conn->prepare("SELECT session_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    if (!$user) {
        return false;
    }
    
    // If session_id doesn't match, user logged in elsewhere
    if ($user['session_id'] !== $session_id) {
        return false;
    }
    
    return true;
}

// ===== OTP FUNCTIONS =====
function generateOTP() {
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// ===== USER FUNCTIONS =====
function logActivity($user_id, $action, $details = '') {
    $conn = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $conn->prepare("INSERT INTO user_activity (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

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

// ===== HELPER FUNCTIONS =====
function debug_log($message) {
    error_log($message);
}

// ===== REMOVE SESSION_ID FROM DATABASE ON LOGOUT =====
function clearUserSession($user_id) {
    $conn = getDB();
    $stmt = $conn->prepare("UPDATE users SET session_id = NULL WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
?>
