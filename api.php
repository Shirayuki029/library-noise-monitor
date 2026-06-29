<?php
// api.php - Fixed to match your database columns
require_once 'config.php';

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
// GET LATEST READING
// ============================================================
if ($action === 'get_latest') {
    $conn = getDB();
    if ($conn) {
        // Try to get from sound_value (your column name)
        $result = $conn->query("SELECT sound_value as noise_level, created_at FROM noise_readings ORDER BY id DESC LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $noise_level = $row['noise_level'];
            $percentage = round(($noise_level / 1023) * 100);
            
            echo json_encode([
                'noise_level' => $noise_level,
                'percentage' => $percentage,
                'status' => $percentage < 30 ? 'QUIET' : ($percentage < 60 ? 'MODERATE' : 'LOUD'),
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
// PING ARDUINO
// ============================================================
if ($action === 'ping_arduino') {
    $conn = getDB();
    if ($conn) {
        $result = $conn->query("SELECT COUNT(*) as count FROM noise_readings WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        if ($result) {
            $row = $result->fetch_assoc();
            echo json_encode(["status" => ($row['count'] > 0) ? 'ok' : 'no_data']);
        } else {
            echo json_encode(["status" => "error"]);
        }
        $conn->close();
    } else {
        echo json_encode(["status" => "error"]);
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
        if ($result) {
            $stats = $result->fetch_assoc();
            echo json_encode([
                'total' => (int)($stats['total'] ?? 0),
                'violations' => 0
            ]);
        } else {
            echo json_encode(['total' => 0, 'violations' => 0]);
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
        $result = $conn->query("SELECT COUNT(*) as total FROM noise_readings WHERE DATE(created_at) = CURDATE()");
        if ($result) {
            $stats = $result->fetch_assoc();
            echo json_encode([
                'total' => (int)($stats['total'] ?? 0),
                'violations' => 0
            ]);
        } else {
            echo json_encode(['total' => 0, 'violations' => 0]);
        }
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
        $result = $conn->query("SELECT COUNT(*) as total FROM noise_readings");
        if ($result) {
            $stats = $result->fetch_assoc();
            echo json_encode([
                'reading' => [
                    'total_readings' => (int)($stats['total'] ?? 0),
                    'total_violations' => 0
                ]
            ]);
        } else {
            echo json_encode(['reading' => ['total_readings' => 0, 'total_violations' => 0]]);
        }
        $conn->close();
    } else {
        echo json_encode(['reading' => ['total_readings' => 0, 'total_violations' => 0]]);
    }
    exit();
}

// ============================================================
// GET INCIDENTS
// ============================================================
if ($action === 'get_incidents') {
    $conn = getDB();
    if ($conn) {
        $result = $conn->query("SELECT * FROM noise_readings WHERE sound_value > 150 ORDER BY id DESC LIMIT 10");
        $incidents = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $incidents[] = $row;
            }
        }
        echo json_encode($incidents);
        $conn->close();
    } else {
        echo json_encode([]);
    }
    exit();
}

// ============================================================
// TEST
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
// SAVE READING - FIXED FOR YOUR DATABASE
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
    
    // Extract values
    $sound_value = 0;
    $percent_value = 0;
    $status = 'QUIET';
    $threshold = 150;
    $sensitivity = 1.0;
    $area = 'reading';
    $user_id = 1;
    
    // Get sound value from various possible keys
    if (isset($data['noise_level'])) {
        $sound_value = intval($data['noise_level']);
    } elseif (isset($data['sound'])) {
        $sound_value = intval($data['sound']);
    } elseif (isset($data['sound_value'])) {
        $sound_value = intval($data['sound_value']);
    } elseif (isset($data['value'])) {
        $sound_value = intval($data['value']);
    }
    
    // If still 0, try to find any numeric value
    if ($sound_value === 0) {
        foreach ($data as $key => $value) {
            if (is_numeric($value) && intval($value) > 0 && intval($value) < 1024) {
                $sound_value = intval($value);
                break;
            }
        }
    }
    
    // Get percent if provided, otherwise calculate
    if (isset($data['percent'])) {
        $percent_value = intval($data['percent']);
    } elseif (isset($data['percent_value'])) {
        $percent_value = intval($data['percent_value']);
    } else {
        $percent_value = round(($sound_value / 1023) * 100);
    }
    
    // Get status
    if (isset($data['status'])) {
        $status = $data['status'];
    } else {
        $status = $percent_value > 70 ? 'NOISE' : ($percent_value > 40 ? 'WARNING' : 'QUIET');
    }
    
    // Get other values if provided
    if (isset($data['threshold'])) $threshold = intval($data['threshold']);
    if (isset($data['sensitivity'])) $sensitivity = floatval($data['sensitivity']);
    if (isset($data['area'])) $area = $data['area'];
    if (isset($data['user_id'])) $user_id = intval($data['user_id']);
    
    error_log("Final: sound=$sound_value, percent=$percent_value, status=$status, threshold=$threshold, sensitivity=$sensitivity, area=$area, user_id=$user_id");
    
    // SAVE TO DATABASE - USING YOUR COLUMN NAMES
    if ($sound_value > 0 && $sound_value <= 1023) {
        $conn = getDB();
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO noise_readings (user_id, sound_value, percent_value, status, threshold, sensitivity, area, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisidss", $user_id, $sound_value, $percent_value, $status, $threshold, $sensitivity, $area);
            
            if ($stmt->execute()) {
                error_log("✅ Data saved! sound=$sound_value, percent=$percent_value, status=$status");
                echo json_encode([
                    "success" => true,
                    "sound" => $sound_value,
                    "percent" => $percent_value,
                    "status" => $status,
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
        error_log("⚠️ Invalid sound value: " . $sound_value);
        echo json_encode(["success" => false, "error" => "Invalid sound value: " . $sound_value]);
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
        echo json_encode(["success" => true, "message" => "Data cleared"]);
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
