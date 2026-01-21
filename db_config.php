<?php
// Database configuration for XAMPP
$host = 'localhost';
$dbname = 'face_attendance_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Create directory for storing face photos
if (!file_exists('face_photos')) {
    mkdir('face_photos', 0777, true);
}
?>