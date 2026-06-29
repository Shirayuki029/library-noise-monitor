<?php
// api.php - Fixed to accept ALL data formats
require_once 'config.php';

session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================================
// PING ARDUINO
// ============================================================
if ($action === 'ping_arduino') {
    $conn = getDB();
    if ($conn) {
        $result = $conn->query("SELECT COUNT(*) as count FROM noise_readings WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row['count'] > 0) {
                echo json_encode(["status" => "ok", "message" => "Arduino is sending data"]);
            } else {
                echo json_encode(["status" => "no_data", "message" => "No recent data from Arduino"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Database query failed"]);
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
        echo json_encode(['error' => 'Database connection failed']);
    }
    exit();
}

// ============================================================
// GET STATS
// ============================================================
if ($action === 'get_stats') {
    $conn = getDB();
    if ($conn) {
        $result = $conn->query("SELECT COUNT(*) as total, AVG(noise_level) as avg_noise, 
                                SUM(CASE WHEN noise_level > 150 THEN 1 ELSE 0 END) as violations 
                                FROM noise_readings");
        if ($result) {
            $stats = $result->fetch_assoc();
            echo json_encode([
                'total' => (int)($stats['total'] ?? 0),
                'avg_noise' => (float)($stats['avg_noise'] ?? 0),
                'violations' => (int)($stats['violations'] ?? 0)
            ]);
        } else {
            echo json_encode(['total' => 0, 'avg_noise' => 0, 'violations' => 0]);
        }
        $conn->close();
    } else {
        echo json_encode(['error' => 'Database connection failed']);
    }
    exit();
}

// ============================================================
// GET TODAY'S STATS
// ============================================================
if ($action === 'get_today_stats') {
    $conn = getDB();
    if ($conn) {
        $result = $conn->query("SELECT COUNT(*) as total, AVG(noise_level) as avg_noise,
                                SUM(CASE WHEN noise_level > 150 THEN 1 ELSE 0 END) as violations 
                                FROM noise_readings WHERE DATE(created_at) = CURDATE()");
        if ($result) {
            $stats = $result->fetch_assoc();
            echo json_encode([
                'total' => (int)($stats['total'] ?? 0),
                'avg_noise' => (float)($stats['avg_noise'] ?? 0),
                'violations' => (int)($stats['violations'] ?? 0)
            ]);
        } else {
            echo json_encode(['total' => 0, 'avg_noise' => 0, 'violations' => 0]);
        }
        $conn->close();
    } else {
        echo json_encode(['error' => 'Database connection failed']);
    }
    exit();
}

// ============================================================
// SAVE READING - Accepts ALL formats
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw input
    $raw_input = file_get_contents('php://input');
    error_log("Raw input: " . $raw_input);
    
    // Try to parse as JSON
    $data = json_decode($raw_input, true);
    
    // If not JSON, try POST data
    if (!$data) {
        $data = $_POST;
    }
    
    // If still no data, try to parse from raw string
    if (empty($data) && !empty($raw_input)) {
        parse_str($raw_input, $data);
    }
    
    error_log("Parsed data: " . print_r($data, true));
    
    // Extract noise level from various formats
    $noise_level = 0;
    
    if (isset($data['noise_level'])) {
        $noise_level = intval($data['noise_level']);
    } elseif (isset($data['sound'])) {
        $noise_level = intval($data['sound']);
    } elseif (isset($data['sound_value'])) {
        $noise_level = intval($data['sound_value']);
    } elseif (isset($data['value'])) {
        $noise_level = intval($data['value']);
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
    
    error_log("Final noise_level: " . $noise_level);
    
    if ($noise_level > 0 && $noise_level <= 1023) {
        $conn = getDB();
        if ($conn) {
            // Check which column exists
            $check = $conn->query("SHOW COLUMNS FROM noise_readings LIKE 'noise_level'");
            $use_noise_level = ($check && $check->num_rows > 0);
            
            if ($use_noise_level) {
                // Table uses noise_level column
                $stmt = $conn->prepare("INSERT INTO noise_readings (user_id, noise_level, created_at) VALUES (?, ?, NOW())");
                $stmt->bind_param("ii", $user_id ?? 1, $noise_level);
            } else {
                // Check for sound_value column
                $check2 = $conn->query("SHOW COLUMNS FROM noise_readings LIKE 'sound_value'");
                if ($check2 && $check2->num_rows > 0) {
                    // Try to get percent and status
                    $percent = isset($data['percent']) ? intval($data['percent']) : round(($noise_level / 1023) * 100);
                    $status = isset($data['status']) ? $data['status'] : 'QUIET';
                    
                    $stmt = $conn->prepare("INSERT INTO noise_readings (user_id, sound_value, percent_value, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("iiis", $user_id ?? 1, $noise_level, $percent, $status);
                } else {
                    // Try the simplest approach
                    $stmt = $conn->prepare("INSERT INTO noise_readings (user_id, noise_level, created_at) VALUES (?, ?, NOW())");
                    $stmt->bind_param("ii", $user_id ?? 1, $noise_level);
                }
            }
            
            if ($stmt->execute()) {
                error_log("✅ Data saved successfully!");
                echo json_encode([
                    "success" => true,
                    "noise_level" => $noise_level,
                    "message" => "Data saved successfully"
                ]);
            } else {
                error_log("❌ Failed to save: " . $stmt->error);
                echo json_encode(["success" => false, "error" => $stmt->error]);
            }
            
            $stmt->close();
            $conn->close();
        } else {
            error_log("❌ Database connection failed!");
            echo json_encode(["success" => false, "error" => "Database connection failed"]);
        }
    } else {
        error_log("⚠️ Invalid noise level: " . $noise_level);
        echo json_encode(["success" => false, "error" => "Invalid noise level: " . $noise_level]);
    }
    exit();
}

// ============================================================
// GET TEST
// ============================================================
if ($action === 'test') {
    $conn = getDB();
    if ($conn) {
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        echo json_encode([
            "status" => "ok",
            "message" => "Database connected",
            "database" => DB_NAME,
            "host" => DB_HOST,
            "tables" => $tables
        ]);
        $conn->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    }
    exit();
}

// ============================================================
// CLEAR DATA
// ============================================================
if ($action === 'clear_data' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $conn = getDB();
    if ($conn) {
        $conn->query("TRUNCATE TABLE noise_readings");
        echo json_encode(["success" => true, "message" => "All data cleared"]);
        $conn->close();
    } else {
        echo json_encode(["success" => false, "error" => "Database connection failed"]);
    }
    exit();
}

// ============================================================
// DEFAULT RESPONSE
// ============================================================
echo json_encode(["status" => "ok", "message" => "API is working"]);
?>
