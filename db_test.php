<?php
// Simple test script for database connectivity
require_once __DIR__ . '/includes/config.php';

try {
    $pdo = getDbConnection();
    echo "Database connection: SUCCESS<br>";
    
    // Test users table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetchColumn();
    echo "Users in database: $count<br>";
    
    // Test users query with columns
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Users table columns:<br>";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})<br>";
    }
    
    // Test basic users query
    $stmt = $pdo->query("SELECT id, full_name, email, role FROM users LIMIT 3");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<br>Sample users:<br>";
    foreach ($users as $user) {
        echo "- {$user['full_name']} ({$user['email']}) - Role: {$user['role']}<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>