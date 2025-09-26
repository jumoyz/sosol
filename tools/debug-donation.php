<?php
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Campaign Donation Debug Test</h2>";

// Get campaign and user for testing
try {
    $pdo = getDbConnection();
    
    // Get a campaign
    $stmt = $pdo->query("SELECT id, title FROM campaigns WHERE status = 'active' LIMIT 1");
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        die("No active campaigns found");
    }
    
    echo "<p><strong>Test Campaign:</strong> {$campaign['title']} (ID: {$campaign['id']})</p>";
    
    // Get a user with wallet balance
    $stmt = $pdo->query("
        SELECT u.id, u.full_name, w.id as wallet_id, w.balance_htg 
        FROM users u 
        JOIN wallets w ON u.id = w.user_id 
        WHERE w.balance_htg >= 100 
        LIMIT 1
    ");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("No users with sufficient balance found");
    }
    
    echo "<p><strong>Test User:</strong> {$user['full_name']} (Balance: {$user['balance_htg']} HTG)</p>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_donate'])) {
        echo "<h3>Processing Test Donation...</h3>";
        
        $amount = 100.00;
        $message = "Test donation";
        $isAnonymous = 0;
        
        try {
            $pdo->beginTransaction();
            
            // Create donation record
            $donationId = generateUuid();
            echo "<p>Generated donation ID: {$donationId}</p>";
            
            $donationStmt = $pdo->prepare("
                INSERT INTO donations 
                (id, campaign_id, donor_id, amount, message, is_anonymous, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $result = $donationStmt->execute([
                $donationId, $campaign['id'], $user['id'], $amount, $message, $isAnonymous
            ]);
            
            if ($result) {
                echo "<p>✓ Donation record created</p>";
            } else {
                echo "<p>✗ Failed to create donation record</p>";
                print_r($donationStmt->errorInfo());
            }
            
            // Update wallet balance
            $updateWalletStmt = $pdo->prepare("
                UPDATE wallets SET balance_htg = balance_htg - ? WHERE id = ?
            ");
            $result = $updateWalletStmt->execute([$amount, $user['wallet_id']]);
            
            if ($result) {
                echo "<p>✓ Wallet balance updated</p>";
            } else {
                echo "<p>✗ Failed to update wallet balance</p>";
                print_r($updateWalletStmt->errorInfo());
            }
            
            // Create transaction record
            $txnId = generateUuid();
            echo "<p>Generated transaction ID: {$txnId}</p>";
            
            $txnStmt = $pdo->prepare("
                INSERT INTO transactions 
                (id, wallet_id, type, amount, currency, status, reference_id, provider, created_at)
                VALUES (?, ?, 'donation', ?, 'HTG', 'completed', ?, 'campaign_system', NOW())
            ");
            $result = $txnStmt->execute([
                $txnId, $user['wallet_id'], $amount, $donationId
            ]);
            
            if ($result) {
                echo "<p>✓ Transaction record created</p>";
            } else {
                echo "<p>✗ Failed to create transaction record</p>";
                print_r($txnStmt->errorInfo());
            }
            
            // Try to add activity record (this might fail if table structure is different)
            try {
                $activityStmt = $pdo->prepare("
                    INSERT INTO activities 
                    (user_id, activity_type, reference_id, details, created_at)
                    VALUES (?, 'donation', ?, ?, NOW())
                ");
                $result = $activityStmt->execute([
                    $user['id'], 
                    $campaign['id'], 
                    json_encode([
                        'campaign_title' => $campaign['title'],
                        'amount' => $amount,
                        'is_anonymous' => $isAnonymous
                    ])
                ]);
                
                if ($result) {
                    echo "<p>✓ Activity record created</p>";
                } else {
                    echo "<p>⚠ Failed to create activity record (non-critical)</p>";
                    print_r($activityStmt->errorInfo());
                }
            } catch (Exception $e) {
                echo "<p>⚠ Activity logging failed: " . $e->getMessage() . "</p>";
            }
            
            $pdo->commit();
            echo "<p><strong>✓ Donation completed successfully!</strong></p>";
            
            // Check updated balance
            $stmt = $pdo->prepare("SELECT balance_htg FROM wallets WHERE id = ?");
            $stmt->execute([$user['wallet_id']]);
            $newBalance = $stmt->fetchColumn();
            echo "<p>New wallet balance: {$newBalance} HTG</p>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<p><strong>✗ Donation failed:</strong> " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p><strong>Setup Error:</strong> " . $e->getMessage() . "</p>";
}
?>

<form method="POST">
    <button type="submit" name="test_donate" class="btn btn-primary">Test Donate 100 HTG</button>
</form>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.btn { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
.btn:hover { background: #0056b3; }
</style>
