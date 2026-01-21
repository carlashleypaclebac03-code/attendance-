<?php
// Database check script
$host = 'localhost';
$dbname = 'face_attendance_db';
$username = 'root';
$password = '';

echo "<h2>Database Check</h2>";

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    
    // Check if database exists
    $result = $pdo->query("SHOW DATABASES LIKE '$dbname'");
    if ($result->rowCount() > 0) {
        echo "✓ Database '$dbname' exists<br>";
        
        // Use the database
        $pdo->exec("USE $dbname");
        
        // Check tables
        $tables = ['employees', 'attendance'];
        foreach ($tables as $table) {
            $result = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($result->rowCount() > 0) {
                echo "✓ Table '$table' exists<br>";
                
                // Show table structure
                $columns = $pdo->query("DESCRIBE $table")->fetchAll();
                echo "<pre>Table structure for $table:\n";
                print_r($columns);
                echo "</pre>";
            } else {
                echo "✗ Table '$table' does NOT exist<br>";
            }
        }
    } else {
        echo "✗ Database '$dbname' does NOT exist<br>";
        
        // Offer to create it
        if (isset($_GET['create'])) {
            $sql = file_get_contents('database.sql');
            $pdo->exec($sql);
            echo "Database created! Refresh page.<br>";
        } else {
            echo "<a href='?create=1'>Create Database</a>";
        }
    }
    
} catch (PDOException $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "<br>";
}
?>