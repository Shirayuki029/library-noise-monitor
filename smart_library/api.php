<?php
session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'noise_monitor');

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ALLOW ALL ACTIONS WITHOUT AUTH FOR TESTING
$public_actions = ['save_reading', 'save_incident', 'get_stats', 'get_all_stats', 'get_incidents', 'test', 'clear_data', 'debug'];

if (!in_array($action, $public_actions)) {
    if (!isset($_SESSION['user_id']) || !$_SESSION['authenticated']) {
        echo json_encode(["error" => "Authentication required"]);
        exit();
    }
}

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        return [
            'error' => true,
            'message' => 'Connection failed: ' . $conn->connect_error,
            'code' => $conn->connect_errno
        ];
    }
    
    return $conn;
}

$db = getDBConnection();

if (is_array($db) && isset($db['error']) && $db['error'] === true) {
    echo json_encode([
        "error" => $db['message'],
        "code" => $db['code'] ?? 0,
        "host" => DB_HOST,
        "database" => DB_NAME
    ]);
    exit();
}

$conn = $db;

// ============================================================
// DEBUG ENDPOINT
// ============================================================
if ($action === 'debug') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    echo json_encode([
        "method" => $_SERVER['REQUEST_METHOD'],
        "action" => $action,
        "input" => $input,
        "parsed" => $data,
        "GET" => $_GET,
        "POST" => $_POST,
        "headers" => getallheaders()
    ]);
    exit();
}

// ============================================================
// POST - Save data
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    if ($action === 'save_reading') {
        error_log("save_reading called: " . print_r($data, true));
        
        if (!isset($data['sound']) || !is_numeric($data['sound'])) {
            echo json_encode(["error" => "Invalid sound value", "received" => $data]);
            exit();
        }
        
        $sound = intval($data['sound']);
        $percent = isset($data['percent']) ? intval($data['percent']) : 0;
        $status = isset($data['status']) ? $data['status'] : 'QUIET';
        $threshold = isset($data['threshold']) ? intval($data['threshold']) : 150;
        $sensitivity = isset($data['sensitivity']) ? floatval($data['sensitivity']) : 1.0;
        $area = isset($data['area']) ? $data['area'] : 'reading';
        
        $stmt = $conn->prepare("INSERT INTO noise_readings 
            (sound_value, percent_value, status, threshold, sensitivity, area, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisidsi", 
            $sound, 
            $percent, 
            $status, 
            $threshold, 
            $sensitivity, 
            $area,
            $user_id
        );
        
        if ($stmt->execute()) {
            $area_escaped = $conn->real_escape_string($area);
            $conn->query("INSERT INTO noise_stats (area, total_readings, total_violations, date) 
                VALUES ('$area_escaped', 1, 0, CURDATE())
                ON DUPLICATE KEY UPDATE total_readings = total_readings + 1");
            
            echo json_encode([
                "success" => true, 
                "id" => $stmt->insert_id,
                "sound" => $sound,
                "percent" => $percent,
                "status" => $status,
                "timestamp" => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode(["error" => $stmt->error]);
        }
        $stmt->close();
        $conn->close();
        exit();
    }
    
    elseif ($action === 'save_incident') {
        error_log("save_incident called: " . print_r($data, true));
        
        $sound = isset($data['sound']) ? intval($data['sound']) : 0;
        $percent = isset($data['percent']) ? intval($data['percent']) : 0;
        $area = isset($data['area']) ? $data['area'] : 'reading';
        
        $stmt = $conn->prepare("INSERT INTO noise_incidents 
            (sound_value, percent_value, area, user_id) 
            VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", 
            $sound, 
            $percent, 
            $area,
            $user_id
        );
        
        if ($stmt->execute()) {
            $area_escaped = $conn->real_escape_string($area);
            $conn->query("INSERT INTO noise_stats (area, total_readings, total_violations, date) 
                VALUES ('$area_escaped', 0, 1, CURDATE())
                ON DUPLICATE KEY UPDATE total_violations = total_violations + 1");
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => $stmt->error]);
        }
        $stmt->close();
        $conn->close();
        exit();
    }
}

// ============================================================
// GET - Load data
// ============================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'get_stats') {
        $area = isset($_GET['area']) ? $conn->real_escape_string($_GET['area']) : 'reading';
        
        $result = $conn->query("SELECT COUNT(*) as total, AVG(sound_value) as avg_sound, 
                                SUM(CASE WHEN status = 'NOISE' THEN 1 ELSE 0 END) as violations 
                                FROM noise_readings WHERE area = '$area'");
        $stats = $result->fetch_assoc();
        echo json_encode([
            'total' => (int)($stats['total'] ?? 0),
            'avg_sound' => (float)($stats['avg_sound'] ?? 0),
            'violations' => (int)($stats['violations'] ?? 0)
        ]);
        $conn->close();
        exit();
    }
    
    elseif ($action === 'get_all_stats') {
        $result = $conn->query("SELECT area, COUNT(*) as total_readings, 
                                SUM(CASE WHEN status = 'NOISE' THEN 1 ELSE 0 END) as total_violations 
                                FROM noise_readings GROUP BY area");
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[$row['area']] = [
                'total_readings' => (int)$row['total_readings'],
                'total_violations' => (int)$row['total_violations']
            ];
        }
        // Always return both areas with defaults
        if (!isset($stats['reading'])) $stats['reading'] = ['total_readings' => 0, 'total_violations' => 0];
        if (!isset($stats['silent'])) $stats['silent'] = ['total_readings' => 0, 'total_violations' => 0];
        echo json_encode($stats);
        $conn->close();
        exit();
    }
    
    elseif ($action === 'get_incidents') {
        $result = $conn->query("SELECT * FROM noise_incidents ORDER BY timestamp DESC LIMIT 50");
        $incidents = [];
        while ($row = $result->fetch_assoc()) {
            $incidents[] = $row;
        }
        echo json_encode($incidents);
        $conn->close();
        exit();
    }
    
    elseif ($action === 'test') {
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        echo json_encode([
            "status" => "ok", 
            "message" => "Database connected successfully",
            "timestamp" => date('Y-m-d H:i:s'),
            "database" => DB_NAME,
            "host" => DB_HOST,
            "tables" => $tables,
            "table_count" => count($tables)
        ]);
        $conn->close();
        exit();
    }
    
    elseif ($action === 'clear_data') {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            $conn->query("TRUNCATE TABLE noise_readings");
            $conn->query("TRUNCATE TABLE noise_incidents");
            $conn->query("INSERT INTO noise_stats (area, total_readings, total_violations, date) VALUES 
                ('reading', 0, 0, CURDATE()),
                ('silent', 0, 0, CURDATE())
                ON DUPLICATE KEY UPDATE total_readings = 0, total_violations = 0");
            echo json_encode(["success" => true, "message" => "All data cleared"]);
        } else {
            echo json_encode(["error" => "Admin access required"]);
        }
        $conn->close();
        exit();
    }
    
    else {
        echo json_encode(["error" => "Unknown action: " . $action]);
        $conn->close();
        exit();
    }
}

// ============================================================
// DELETE - Clear data
// ============================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $action === 'clear_data') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $conn->query("TRUNCATE TABLE noise_readings");
        $conn->query("TRUNCATE TABLE noise_incidents");
        $conn->query("INSERT INTO noise_stats (area, total_readings, total_violations, date) VALUES 
            ('reading', 0, 0, CURDATE()),
            ('silent', 0, 0, CURDATE())
            ON DUPLICATE KEY UPDATE total_readings = 0, total_violations = 0");
        echo json_encode(["success" => true, "message" => "All data cleared"]);
    } else {
        echo json_encode(["error" => "Admin access required"]);
    }
    $conn->close();
    exit();
}

echo json_encode(["error" => "Invalid request"]);
$conn->close();
?>