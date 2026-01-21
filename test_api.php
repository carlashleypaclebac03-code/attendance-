<?php
// Test script for API debugging
header('Content-Type: text/html');

echo "<h2>API Debug Test</h2>";

// Test database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=face_attendance_db", "root", "");
    echo "✓ Database connection successful<br>";
    
    // Check tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Tables found: " . implode(", ", $tables) . "<br>";
    
} catch(PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

// Check directory permissions
echo "face_photos exists: " . (file_exists('face_photos') ? 'Yes' : 'No') . "<br>";
echo "face_photos writable: " . (is_writable('face_photos') ? 'Yes' : 'No') . "<br>";

// Test POST data
echo "<h3>POST Data Test</h3>";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "POST received<br>";
    print_r($_POST);
} else {
    echo "No POST data<br>";
}
?>