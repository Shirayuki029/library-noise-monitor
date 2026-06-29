<?php
// api.php - Fixed for Railway
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
        echo json_encode(['error' => 'Database connection failed']);
    }
    exit();
}

// ============================================================
// SAVE READING
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    if (empty($data)) {
        parse_str($raw_input, $data);
    }
    
    $noise_level = 0;
    if (isset($data['noise_level'])) {
        $noise_level = intval($data['noise_level']);
    } elseif (isset($data['sound'])) {
        $noise_level = intval($data['sound']);
    }
    
    if ($noise_level > 0 && $noise_level <= 1023) {
        $conn = getDB();
        if ($conn) {
            $stmt = $conn->prepare("INSERT INTO noise_readings (user_id, noise_level, created_at) VALUES (?, ?, NOW())");
            $user_id = isset($data['user_id']) ? intval($data['user_id']) : 1;
            $stmt->bind_param("ii", $user_id, $noise_level);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "noise_level" => $noise_level]);
            } else {
                echo json_encode(["success" => false, "error" => $stmt->error]);
            }
            $stmt->close();
            $conn->close();
        } else {
            echo json_encode(["success" => false, "error" => "Database connection failed"]);
        }
    } else {
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
// DEFAULT
// ============================================================
echo json_encode(["status" => "ok", "message" => "API is working", "timestamp" => date('Y-m-d H:i:s')]);
?>
