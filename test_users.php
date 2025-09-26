<?php
// Simple test to check users data
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

try {
    $pdo = getDbConnection();
    echo "✓ Database connection successful<br>";
    
    // Test basic users query
    $query = "SELECT COUNT(*) as total FROM users";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $total = $stmt->fetchColumn();
    echo "✓ Total users in database: " . $total . "<br>";
    
    if ($total > 0) {
        // Get first few users
        $query = "SELECT id, full_name, email, role, created_at FROM users ORDER BY id ASC LIMIT 3";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Sample users:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Test the complex query used in admin/users.php
        echo "<h3>Testing complex query:</h3>";
        $complex_query = "
            SELECT u.*,
                   COALESCE(w.balance_htg, 0) as balance_htg, 
                   COALESCE(w.balance_usd, 0) as balance_usd,
                   COALESCE(t.transaction_count, 0) as transaction_count,
                   COALESCE(s.sol_count, 0) as sol_count
            FROM users u
            LEFT JOIN wallets w ON u.id = w.user_id
            LEFT JOIN (SELECT user_id, COUNT(*) as transaction_count FROM transactions GROUP BY user_id) t ON u.id = t.user_id
            LEFT JOIN (SELECT user_id, COUNT(*) as sol_count FROM sol_participants GROUP BY user_id) s ON u.id = s.user_id
            ORDER BY u.created_at DESC
            LIMIT 3
        ";
        
        try {
            $stmt = $pdo->prepare($complex_query);
            $stmt->execute();
            $complex_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "✓ Complex query returned " . count($complex_users) . " users<br>";
            
            if (!empty($complex_users)) {
                echo "<pre>";
                print_r($complex_users[0]);
                echo "</pre>";
            }
        } catch (Exception $e) {
            echo "✗ Complex query failed: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "⚠ No users found in database<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
    echo "✗ Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>