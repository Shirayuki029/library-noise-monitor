<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debugging Your Website</h1>";

// Test 1: Check if config.php loads
echo "<h3>Test 1: Loading config.php</h3>";
try {
    require_once 'config.php';
    echo "✅ config.php loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ Error loading config.php: " . $e->getMessage() . "<br>";
}

// Test 2: Check database connection
echo "<h3>Test 2: Database Connection</h3>";
try {
    $conn = getDB();
    if ($conn) {
        echo "✅ Database connected!<br>";
        echo "Database: " . DB_NAME . "<br>";
        echo "Host: " . DB_HOST . "<br>";
        echo "User: " . DB_USER . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 3: List tables
echo "<h3>Test 3: Tables in Database</h3>";
if (isset($conn) && $conn) {
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        while ($row = $result->fetch_array()) {
            echo "✅ Table: " . $row[0] . "<br>";
        }
    } else {
        echo "❌ Error getting tables: " . $conn->error . "<br>";
    }
}

// Test 4: Check if users table exists
echo "<h3>Test 4: Users Table</h3>";
if (isset($conn) && $conn) {
    $result = $conn->query("SELECT * FROM users LIMIT 1");
    if ($result) {
        echo "✅ Users table exists and has data<br>";
    } else {
        echo "❌ Users table error: " . $conn->error . "<br>";
    }
}

echo "<h3>✅ Debug Complete!</h3>";
?>