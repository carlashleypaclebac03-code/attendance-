<?php
require_once 'db_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'register':
        registerFace();
        break;
    case 'recognize':
        recognizeFace();
        break;
    case 'mark_attendance':
        markAttendance();
        break;
    case 'get_today_attendance':
        getTodayAttendance();
        break;
    case 'get_employees':
        getEmployees();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function registerFace() {
    global $pdo;
    
    $name = $_POST['name'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';
    $department = $_POST['department'] ?? '';
    $image_data = $_POST['image_data'] ?? '';
    
    if(empty($name) || empty($employee_id) || empty($image_data)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    try {
        // Save face image
        $image_name = 'face_photos/' . $employee_id . '_' . time() . '.jpg';
        $image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        file_put_contents($image_name, base64_decode($image_data));
        
        // Generate face encoding (simplified - in real app, use Python face_recognition)
        $face_encoding = generateFaceEncoding($image_name);
        
        // Save to database
        $stmt = $pdo->prepare("INSERT INTO employees (name, employee_id, department, face_data, photo_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $employee_id, $department, $face_encoding, $image_name]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Employee registered successfully',
            'photo_url' => $image_name
        ]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function recognizeFace() {
    global $pdo;
    
    $image_data = $_POST['image_data'] ?? '';
    
    if(empty($image_data)) {
        echo json_encode(['success' => false, 'message' => 'No image data']);
        return;
    }
    
    try {
        // Save temporary image
        $temp_image = 'face_photos/temp_' . time() . '.jpg';
        $image_data = str_replace('data:image/jpeg;base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        file_put_contents($temp_image, base64_decode($image_data));
        
        // Get all employees
        $stmt = $pdo->query("SELECT * FROM employees");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Simulate face recognition (in real app, compare face encodings)
        $recognized_employee = null;
        
        // For demo, return the first employee if exists
        if(count($employees) > 0) {
            $recognized_employee = $employees[0];
        }
        
        if($recognized_employee) {
            // Mark attendance
            $today = date('Y-m-d');
            $time = date('H:i:s');
            
            // Check if already marked today
            $check_stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND attendance_date = ?");
            $check_stmt->execute([$recognized_employee['employee_id'], $today]);
            $existing = $check_stmt->fetch();
            
            if($existing) {
                // Update time out
                $update_stmt = $pdo->prepare("UPDATE attendance SET time_out = ? WHERE id = ?");
                $update_stmt->execute([$time, $existing['id']]);
                $message = "Time out marked for " . $recognized_employee['name'];
            } else {
                // Insert new attendance
                $insert_stmt = $pdo->prepare("INSERT INTO attendance (employee_id, name, attendance_date, time_in) VALUES (?, ?, ?, ?)");
                $insert_stmt->execute([
                    $recognized_employee['employee_id'],
                    $recognized_employee['name'],
                    $today,
                    $time
                ]);
                $message = "Attendance marked for " . $recognized_employee['name'];
            }
            
            echo json_encode([
                'success' => true,
                'recognized' => true,
                'employee' => $recognized_employee,
                'message' => $message
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'recognized' => false,
                'message' => 'Face not recognized'
            ]);
        }
        
        // Clean up temp file
        unlink($temp_image);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function markAttendance() {
    global $pdo;
    
    $employee_id = $_POST['employee_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? 'in'; // 'in' or 'out'
    
    if(empty($employee_id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    try {
        $today = date('Y-m-d');
        $time = date('H:i:s');
        
        if($type === 'in') {
            $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, name, attendance_date, time_in) VALUES (?, ?, ?, ?)");
            $stmt->execute([$employee_id, $name, $today, $time]);
            $message = "Time in marked for $name";
        } else {
            // Find today's record and update time out
            $stmt = $pdo->prepare("UPDATE attendance SET time_out = ? WHERE employee_id = ? AND attendance_date = ? AND time_out IS NULL");
            $stmt->execute([$time, $employee_id, $today]);
            $message = "Time out marked for $name";
        }
        
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getTodayAttendance() {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE attendance_date = ? ORDER BY timestamp DESC");
        $stmt->execute([$today]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get summary
        $total_stmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) as total FROM attendance WHERE attendance_date = ?");
        $total_stmt->execute([$today]);
        $total = $total_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'attendance' => $attendance,
            'total' => $total['total'] ?? 0
        ]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getEmployees() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM employees ORDER BY registered_date DESC");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'employees' => $employees,
            'count' => count($employees)
        ]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Simplified face encoding generation
function generateFaceEncoding($image_path) {
    // In a real application, this would use face_recognition library
    // For demo, we'll generate a simple hash
    return md5_file($image_path);
}
?>