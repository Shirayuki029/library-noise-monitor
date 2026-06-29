<?php
// api.php - Fixed for Railway
require_once 'config.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Turn off error display for clean JSON
ini_set('display_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================================
// TEST CONNECTION
// ============================================================
if ($action === 'test') {
    $conn = getDB();
    if ($conn) {
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        $conn->close();
        
        echo json_encode([
            "status" => "ok",
            "message" => "Database connected successfully",
            "database" => DB_NAME,
            "host" => DB_HOST,
            "tables" => $tables,
            "table_count" => count($tables)
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    }
    exit();
}

// ============================================================
// PING ARDUINO
// ============================================================
if ($action === 'ping_arduino') {
    $conn = getDB();
    if ($conn) {
        $result = $conn->query("SELECT COUNT(*) as count FROM noise_readings WHERE created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)");
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row['count'] > 0) {
                echo json_encode(["status" => "ok", "message" => "Arduino is sending data"]);
            } else {
                echo json_encode(["status" => "no_data", "message" => "No recent data from Arduino"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Query failed"]);
        }
        $conn->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    }
    exit();
}

// ============================================================
// GET LATEST READING
// ============================================================
if ($action === 'get_latest') {
    $conn = getDB();
    if ($conn) {
        $result = $conn->query("SELECT noise_level, created_at FROM noise_readings ORDER BY id DESC LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $noise_level = $row['noise_level'];
            $percentage = round(($noise_level / 1023) * 100);
            
            if ($percentage < 30) {
                $status = 'QUIET';
            } elseif ($percentage < 60) {
                $status = 'MODERATE';
            } else {
                $status = 'LOUD';
            }
            
            echo json_encode([
                'noise_level' => $noise_level,
                'percentage' => $percentage,
                'status' => $status,
                'time' => $row['created_at']
            ]);
        } else {
            echo json_encode(['noise_level' => 0, 'percentage' => 0, 'status' => 'QUIET', 'time' => null]);
        }
        $conn->close();
    } else {
        echo json_encode(['noise_level' => 0, 'percentage' => 0, 'status' => 'ERROR', 'time' => null]);
    }
    exit();
}

// ============================================================
// SAVE READING - FIXED
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_reading') {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    if (empty($data)) {
        parse_str($raw_input, $data);
    }
    
    // Log what we received
    error_log("SAVE READING - Raw: " . $raw_input);
    error_log("SAVE READING - Data: " . print_r($data, true));
    
    // Extract noise level from various formats
    $noise_level = 0;
    if (isset($data['noise_level'])) {
        $noise_level = intval($data['noise_level']);
    } elseif (isset($data['sound'])) {
        $noise_level = intval($data['sound']);
    } elseif (isset($data['sound_value'])) {
        $noise_level = intval($data['sound_value']);
    }
    
    // If still 0, try to find any numeric value
    if ($noise_level === 0) {
        foreach ($data as $key => $value) {
            if (is_numeric($value) && intval($value) > 0 && intval($value) < 1024) {
                $noise_level = intval($value);
                break;
            }
        }
    }
    
    error_log("SAVE READING - Final noise_level: $noise_level");
    
    if ($noise_level > 0 && $noise_level <= 1023) {
        $conn = getDB();
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO noise_readings (user_id, noise_level, created_at) VALUES (?, ?, NOW())");
            $user_id = 1;
            $stmt->bind_param("ii", $user_id, $noise_level);
            
            if ($stmt->execute()) {
                error_log("SAVE READING - ✅ Data saved: $noise_level");
                echo json_encode(["success" => true, "noise_level" => $noise_level]);
            } else {
                error_log("SAVE READING - ❌ Failed to save: " . $stmt->error);
                echo json_encode(["success" => false, "error" => $stmt->error]);
            }
            $stmt->close();
            $conn->close();
        } else {
            error_log("SAVE READING - ❌ Database connection failed");
            echo json_encode(["success" => false, "error" => "Database connection failed"]);
        }
    } else {
        error_log("SAVE READING - ⚠️ Invalid noise level: $noise_level");
        echo json_encode(["success" => false, "error" => "Invalid noise level: $noise_level"]);
    }
    exit();
}

// ============================================================
// GET STATS
// ============================================================
if ($action === 'get_stats') {
    $conn = getDB();
    if ($conn) {
        $result = $conn->query("SELECT COUNT(*) as total FROM noise_readings");
        $row = $result->fetch_assoc();
        echo json_encode(['total' => (int)($row['total'] ?? 0)]);
        $conn->close();
    } else {
        echo json_encode(['error' => 'Database connection failed']);
    }
    exit();
}

// ============================================================
// GET ALL STATS
// ============================================================
if ($action === 'get_all_stats') {
    $conn = getDB();
    if ($conn) {
        $result = $conn->query("SELECT COUNT(*) as total_readings FROM noise_readings");
        $row = $result->fetch_assoc();
        echo json_encode([
            'reading' => [
                'total_readings' => (int)($row['total_readings'] ?? 0),
                'total_violations' => 0
            ],
            'silent' => [
                'total_readings' => 0,
                'total_violations' => 0
            ]
        ]);
        $conn->close();
    } else {
        echo json_encode(['error' => 'Database connection failed']);
    }
    exit();
}

// ============================================================
// GET INCIDENTS
// ============================================================
if ($action === 'get_incidents') {
    $conn = getDB();
    if ($conn) {
        $result = $conn->query("SELECT * FROM noise_readings WHERE noise_level > 150 ORDER BY id DESC LIMIT 10");
        $incidents = [];
        while ($row = $result->fetch_assoc()) {
            $incidents[] = $row;
        }
        echo json_encode($incidents);
        $conn->close();
    } else {
        echo json_encode([]);
    }
    exit();
}

// ============================================================
// CLEAR DATA
// ============================================================
if ($action === 'clear_data') {
    $conn = getDB();
    if ($conn) {
        $conn->query("TRUNCATE TABLE noise_readings");
        $conn->close();
        echo json_encode(["success" => true, "message" => "Data cleared"]);
    } else {
        echo json_encode(["success" => false, "error" => "Database connection failed"]);
    }
    exit();
}

// ============================================================
// DEFAULT
// ============================================================
echo json_encode(["status" => "ok", "message" => "API is working", "timestamp" => date('Y-m-d H:i:s')]);
?>
