<?php
// config.php

// ===== SESSION MANAGEMENT =====
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
define('DB_PORT', (int)$port);

// OTP Configuration
define('OTP_EXPIRY', 300); // 5 minutes

// ===== SESSION TIMEOUT =====
define('SESSION_TIMEOUT', 1800); // 30 minutes

// ===== DATABASE CONNECTION =====
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return null;
    }
    
    return $conn;
}

// ===== CHECK DATABASE CONNECTION =====
function checkDBConnection() {
    $conn = getDB();
    if ($conn) {
        $conn->close();
        return true;
    }
    return false;
}

// ===== AUTHENTICATION FUNCTIONS =====
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAuth() {
    if (!isAuthenticated()) {
        header("Location: login.php");
        exit();
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit();
    }
}

function generateOTP() {
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// ===== USER FUNCTIONS =====
function getUser($id) {
    $conn = getDB();
    if (!$conn) return null;
    
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
    if (!$conn) return [];
    
    $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $conn->close();
    return $users;
}

function logActivity($user_id, $action, $details = '') {
    $conn = getDB();
    if (!$conn) return;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $conn->prepare("INSERT INTO user_activity (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// ===== REMOVED: validateSession() and clearUserSession() =====
// These functions are no longer needed for "One Login Only"

function debug_log($message) {
    error_log($message);
}
?>
