<?php
require_once "../includes/config.php";
require_once "../includes/functions.php";

echo "=== Testing Campaign Donation Functionality ===\n";

try {
    $pdo = getDbConnection();
    
    // Check if required tables exist
    $tables = ['campaigns', 'donations', 'wallets', 'transactions', 'activities'];
    
    echo "\n1. Checking Database Tables:\n";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            echo "✓ {$table} table exists\n";
        } catch (Exception $e) {
            echo "✗ {$table} table missing or inaccessible\n";
        }
    }
    
    // Check if there are any active campaigns
    echo "\n2. Checking Active Campaigns:\n";
    $stmt = $pdo->query("SELECT id, title, status FROM campaigns WHERE status = 'active' LIMIT 3");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($campaigns) > 0) {
        echo "✓ Found " . count($campaigns) . " active campaigns:\n";
        foreach ($campaigns as $campaign) {
            echo "  - {$campaign['id']}: {$campaign['title']}\n";
        }
    } else {
        echo "✗ No active campaigns found\n";
    }
    
    // Check if there are users with wallets
    echo "\n3. Checking Users with Wallets:\n";
    $stmt = $pdo->query("
        SELECT u.id, u.full_name, w.balance_htg 
        FROM users u 
        JOIN wallets w ON u.id = w.user_id 
        WHERE w.balance_htg > 0 
        LIMIT 3
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "✓ Found " . count($users) . " users with wallet balance:\n";
        foreach ($users as $user) {
            echo "  - {$user['full_name']}: {$user['balance_htg']} HTG\n";
        }
    } else {
        echo "✗ No users with wallet balance found\n";
    }
    
    // Test generateUuid function
    echo "\n4. Testing generateUuid Function:\n";
    if (function_exists('generateUuid')) {
        $uuid = generateUuid();
        echo "✓ generateUuid function works: {$uuid}\n";
    } else {
        echo "✗ generateUuid function not found\n";
    }
    
    // Check donations table structure
    echo "\n5. Checking Donations Table Structure:\n";
    $stmt = $pdo->query("DESCRIBE donations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['id', 'campaign_id', 'donor_id', 'amount', 'message', 'is_anonymous', 'status'];
    foreach ($requiredColumns as $col) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $col) {
                echo "✓ {$col} column exists\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "✗ {$col} column missing\n";
        }
    }
    
    echo "\n=== Test Completed ===\n";
    
} catch (Exception $e) {
    echo "Test failed: " . $e->getMessage() . "\n";
}
?>
