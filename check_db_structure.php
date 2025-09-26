<?php
// Check actual database structure
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['is_admin'] = true;

require_once 'includes/config.php';

try {
    $pdo = getDbConnection();
    echo "<h3>Database Structure Analysis</h3>";
    
    // Check users table structure
    echo "<h4>Users Table Structure:</h4>";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";
    
    // Check if wallets table exists
    echo "<h4>Wallets Table Check:</h4>";
    try {
        $stmt = $pdo->query("DESCRIBE wallets");
        $wallet_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($wallet_columns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td></tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Wallets table error: " . $e->getMessage() . "</p>";
    }
    
    // Get sample user data
    echo "<h4>Sample Users:</h4>";
    $stmt = $pdo->query("SELECT * FROM users LIMIT 3");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($users) {
        echo "<table border='1'>";
        $first = true;
        foreach ($users as $user) {
            if ($first) {
                echo "<tr>";
                foreach (array_keys($user) as $key) {
                    echo "<th>$key</th>";
                }
                echo "</tr>";
                $first = false;
            }
            echo "<tr>";
            foreach ($user as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found</p>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>