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
// PING ARDUINO - Check if Arduino is sending data
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
// GET LATEST READING - For dashboard
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
// SAVE READING - Accepts ALL data formats
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
    
    // If still no data, try to parse from raw string (for Arduino format: noise_level=123)
    if (empty($data) && !empty($raw_input)) {
        parse_str($raw_input, $data);
    }
    
    error_log("Parsed data: " . print_r($data, true));
    
    // ============================================================
    // EXTRACT DATA FROM VARIOUS FORMATS
    // ============================================================
    $noise_level = 0;
    $percent = 0;
    $status = 'QUIET';
    $threshold = 150;
    $sensitivity = 1.0;
    $area = 'reading';
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
    
    // Try different possible keys for noise level
    if (isset($data['noise_level'])) {
        $noise_level = intval($data['noise_level']);
    } elseif (isset($data['sound'])) {
        $noise_level = intval($data['sound']);
    } elseif (isset($data['sound_value'])) {
        $noise_level = intval($data['sound_value']);
    } elseif (isset($data['value'])) {
        $noise_level = intval($data['value']);
    }
    
    // If noise_level is 0, check if there's any numeric value
    if ($noise_level === 0) {
        foreach ($data as $key => $value) {
            if (is_numeric($value) && intval($value) > 0 && intval($value) < 1024) {
                $noise_level = intval($value);
                break;
            }
        }
    }
    
    // Extract percent if available
    if (isset($data['percent'])) {
        $percent = intval($data['percent']);
    } elseif (isset($data['percent_value'])) {
        $percent = intval($data['percent_value']);
    } else {
        $percent = round(($noise_level / 1023) * 100);
    }
    
    // Extract status if available
    if (isset($data['status'])) {
        $status = strtoupper($data['status']);
    } else {
        if ($percent > 70) {
            $status = 'NOISE';
        } elseif ($percent > 40) {
            $status = 'WARNING';
        } else {
            $status = 'QUIET';
        }
    }
    
    // Extract other values
    if (isset($data['threshold'])) {
        $threshold = intval($data['threshold']);
    }
    if (isset($data['sensitivity'])) {
        $sensitivity = floatval($data['sensitivity']);
    }
    if (isset($data['area'])) {
        $area = $data['area'];
    }
    if (isset($data['user_id'])) {
        $user_id = intval($data['user_id']);
    }
    
    error_log("Final values: noise_level=$noise_level, percent=$percent, status=$status, threshold=$threshold, sensitivity=$sensitivity, area=$area, user_id=$user_id");
    
    // ============================================================
    // SAVE TO DATABASE
    // ============================================================
    if ($noise_level > 0 && $noise_level <= 1023) {
        $conn = getDB();
        if ($conn) {
            // Check if table has the right columns
            $check = $conn->query("SHOW COLUMNS FROM noise_readings LIKE 'noise_level'");
            if ($check && $check->num_rows > 0) {
                // Table uses noise_level column
                $stmt = $conn->prepare("INSERT INTO noise_readings (user_id, noise_level, created_at) VALUES (?, ?, NOW())");
                $stmt->bind_param("ii", $user_id, $noise_level);
            } else {
                // Table uses sound_value column
                $stmt = $conn->prepare("INSERT INTO noise_readings (user_id, sound_value, percent_value, status, threshold, sensitivity, area, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("iiisids", $user_id, $noise_level, $percent, $status, $threshold, $sensitivity, $area);
            }
            
            if ($stmt->execute()) {
                error_log("✅ Data saved: noise_level=$noise_level, percent=$percent, status=$status");
                
                // Update stats
                $area_escaped = $conn->real_escape_string($area);
                $conn->query("INSERT INTO noise_stats (area, total_readings, total_violations, date) 
                    VALUES ('$area_escaped', 1, " . ($status === 'NOISE' ? 1 : 0) . ", CURDATE())
                    ON DUPLICATE KEY UPDATE 
                        total_readings = total_readings + 1,
                        total_violations = total_violations + " . ($status === 'NOISE' ? 1 : 0));
                
                echo json_encode([
                    "success" => true,
                    "noise_level" => $noise_level,
                    "percent" => $percent,
                    "status" => $status,
                    "timestamp" => date('Y-m-d H:i:s')
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
        error_log("⚠️ Invalid noise level: $noise_level");
        echo json_encode(["success" => false, "error" => "Invalid noise level: $noise_level"]);
    }
    exit();
}

// ============================================================
// GET - Load data (existing functions)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // ... keep your existing GET handlers here ...
    // (get_stats, get_all_stats, get_incidents, test, clear_data)
}

// ============================================================
// DEFAULT RESPONSE
// ============================================================
echo json_encode(["status" => "ok", "message" => "API is working", "timestamp" => date('Y-m-d H:i:s')]);
?>
