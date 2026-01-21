<?php
/**
 * Face Attendance System API
 * Fixed version for GitHub deployment
 */

// ==================== CONFIGURATION ====================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Start output buffering
ob_start();

// ==================== DATABASE CONNECTION ====================
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=face_attendance_db;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    sendJsonError("Database connection failed: " . $e->getMessage());
    exit;
}

// ==================== HEADERS ====================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== FUNCTIONS ====================
function sendJsonError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

function sendJsonSuccess($data = [], $message = 'Success') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

function validateBase64Image($base64) {
    if (empty($base64)) return false;
    
    // Check if it's a valid base64 string
    if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $base64)) return false;
    
    // Decode and check if it's valid image data
    $decoded = base64_decode($base64, true);
    if ($decoded === false) return false;
    
    // Check image size (max 2MB)
    if (strlen($decoded) > 2 * 1024 * 1024) return false;
    
    return $decoded;
}

// ==================== MAIN API ROUTING ====================
try {
    // Get request method
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Get action
    $action = '';
    
    if ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        
        // Also check JSON input
        if (empty($action)) {
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
        }
    } elseif ($method === 'GET') {
        $action = $_GET['action'] ?? '';
    }
    
    // Clear any output before processing
    ob_clean();
    
    // Route actions
    switch ($action) {
        case 'register':
            handleRegister();
            break;
            
        case 'recognize':
            handleRecognize();
            break;
            
        case 'mark_attendance':
            handleMarkAttendance();
            break;
            
        case 'get_today_attendance':
            handleGetTodayAttendance();
            break;
            
        case 'get_employees':
            handleGetEmployees();
            break;
            
        case 'test':
            sendJsonSuccess(['status' => 'API is working']);
            break;
            
        default:
            sendJsonError('Invalid action specified', 400);
    }
} catch (Exception $e) {
    error_log("API Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    sendJsonError('Server error: ' . $e->getMessage(), 500);
}

// ==================== ACTION HANDLERS ====================
function handleRegister() {
    global $pdo;
    
    // Get data
    $name = trim($_POST['name'] ?? '');
    $employee_id = trim($_POST['employee_id'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $image_data = $_POST['image_data'] ?? '';
    
    // Validate
    if (empty($name) || empty($employee_id) || empty($image_data)) {
        sendJsonError('Missing required fields: name, employee_id, or image_data');
    }
    
    if (strlen($name) < 2) sendJsonError('Name must be at least 2 characters');
    if (strlen($employee_id) < 3) sendJsonError('Employee ID must be at least 3 characters');
    
    // Process image
    $image_decoded = validateBase64Image($image_data);
    if (!$image_decoded) sendJsonError('Invalid image data');
    
    // Create upload directory
    $upload_dir = 'face_photos';
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            sendJsonError('Cannot create upload directory');
        }
    }
    
    // Generate unique filename
    $filename = $upload_dir . '/' . preg_replace('/[^a-zA-Z0-9]/', '_', $employee_id) . '_' . time() . '.jpg';
    
    // Save image
    if (file_put_contents($filename, $image_decoded) === false) {
        sendJsonError('Failed to save image. Check directory permissions.');
    }
    
    // Generate face encoding (simplified for demo)
    $face_encoding = md5_file($filename);
    
    // Check for duplicate
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    
    if ($stmt->rowCount() > 0) {
        unlink($filename); // Clean up uploaded file
        sendJsonError('Employee ID already exists');
    }
    
    // Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO employees (name, employee_id, department, face_data, photo_url, registered_date) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    try {
        $stmt->execute([$name, $employee_id, $department, $face_encoding, $filename]);
        $employee_id = $pdo->lastInsertId();
        
        sendJsonSuccess([
            'id' => $employee_id,
            'name' => $name,
            'employee_id' => $employee_id,
            'photo_url' => $filename,
            'registered_date' => date('Y-m-d H:i:s')
        ], 'Employee registered successfully');
        
    } catch (PDOException $e) {
        unlink($filename); // Clean up on error
        sendJsonError('Database error: ' . $e->getMessage());
    }
}

function handleRecognize() {
    global $pdo;
    
    $image_data = $_POST['image_data'] ?? '';
    
    if (empty($image_data)) {
        sendJsonError('No image data provided');
    }
    
    // Process image
    $image_decoded = validateBase64Image($image_data);
    if (!$image_decoded) sendJsonError('Invalid image data');
    
    // Save temp image
    $temp_file = 'face_photos/temp_' . time() . '.jpg';
    file_put_contents($temp_file, $image_decoded);
    
    // Get all employees
    $stmt = $pdo->query("SELECT * FROM employees ORDER BY id DESC LIMIT 10");
    $employees = $stmt->fetchAll();
    
    // Simplified recognition (always match first employee for demo)
    $recognized = count($employees) > 0 ? $employees[0] : null;
    
    if ($recognized) {
        // Mark attendance
        $today = date('Y-m-d');
        $time = date('H:i:s');
        
        // Check existing attendance
        $check = $pdo->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ? AND attendance_date = ?
            ORDER BY id DESC LIMIT 1
        ");
        $check->execute([$recognized['employee_id'], $today]);
        $existing = $check->fetch();
        
        if ($existing) {
            // Update time out
            $update = $pdo->prepare("
                UPDATE attendance SET time_out = ? 
                WHERE id = ? AND time_out IS NULL
            ");
            $update->execute([$time, $existing['id']]);
            $message = "Time out marked for " . $recognized['name'];
        } else {
            // Insert time in
            $insert = $pdo->prepare("
                INSERT INTO attendance (employee_id, name, attendance_date, time_in) 
                VALUES (?, ?, ?, ?)
            ");
            $insert->execute([
                $recognized['employee_id'],
                $recognized['name'],
                $today,
                $time
            ]);
            $message = "Time in marked for " . $recognized['name'];
        }
        
        // Clean up temp file
        unlink($temp_file);
        
        sendJsonSuccess([
            'recognized' => true,
            'employee' => $recognized,
            'attendance_date' => $today,
            'time' => $time,
            'message' => $message
        ]);
    } else {
        // Clean up temp file
        unlink($temp_file);
        
        sendJsonSuccess([
            'recognized' => false,
            'message' => 'Face not recognized'
        ]);
    }
}

function handleMarkAttendance() {
    global $pdo;
    
    $employee_id = $_POST['employee_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? 'in';
    
    if (empty($employee_id) || empty($name)) {
        sendJsonError('Missing employee ID or name');
    }
    
    $today = date('Y-m-d');
    $time = date('H:i:s');
    
    if ($type === 'in') {
        $stmt = $pdo->prepare("
            INSERT INTO attendance (employee_id, name, attendance_date, time_in) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$employee_id, $name, $today, $time]);
        $message = "Time in marked for $name";
    } else {
        $stmt = $pdo->prepare("
            UPDATE attendance SET time_out = ? 
            WHERE employee_id = ? AND attendance_date = ? AND time_out IS NULL
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$time, $employee_id, $today]);
        $message = "Time out marked for $name";
    }
    
    sendJsonSuccess(['message' => $message]);
}

function handleGetTodayAttendance() {
    global $pdo;
    
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE attendance_date = ? 
        ORDER BY time_in DESC
    ");
    $stmt->execute([$today]);
    $attendance = $stmt->fetchAll();
    
    // Get counts
    $total = $pdo->prepare("
        SELECT COUNT(DISTINCT employee_id) as total 
        FROM attendance WHERE attendance_date = ?
    ");
    $total->execute([$today]);
    $total_count = $total->fetch()['total'] ?? 0;
    
    sendJsonSuccess([
        'attendance' => $attendance,
        'total' => $total_count,
        'date' => $today
    ]);
}

function handleGetEmployees() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT * FROM employees 
        ORDER BY registered_date DESC
    ");
    $employees = $stmt->fetchAll();
    
    sendJsonSuccess([
        'employees' => $employees,
        'count' => count($employees)
    ]);
}

// End output buffering
ob_end_flush();
?>