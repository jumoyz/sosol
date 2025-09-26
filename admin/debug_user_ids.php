<?php
require_once '../includes/config.php';

try {
    $pdo = getDbConnection();
    
    echo "=== USER IDS DEBUG ===\n\n";
    
    // Get all user IDs and basic info
    $stmt = $pdo->query("SELECT id, full_name, email, role FROM users ORDER BY created_at DESC LIMIT 10");
    echo "Available User IDs:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - ID: {$row['id']}\n";
        echo "    Name: {$row['full_name']}\n";
        echo "    Email: {$row['email']}\n";
        echo "    Role: {$row['role']}\n\n";
    }
    
    // Test the get_user_details.php logic with the first user
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $test_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_user) {
        $user_id = $test_user['id'];
        echo "Testing get_user_details logic with ID: $user_id\n\n";
        
        // Simulate the exact query from get_user_details.php
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $check_stmt->execute([$user_id]);
        $exists = $check_stmt->fetch();
        
        echo "User existence check: " . ($exists ? "FOUND" : "NOT FOUND") . "\n";
        
        if ($exists) {
            // Test the full query
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "Full user query: " . ($user_data ? "SUCCESS" : "FAILED") . "\n";
            if ($user_data) {
                echo "User data keys: " . implode(', ', array_keys($user_data)) . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>