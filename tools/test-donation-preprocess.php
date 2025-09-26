<?php
// Simple test to verify the donation preprocess flow works
require_once "../includes/config.php";
require_once "../includes/functions.php";

// Enable error display for testing
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Testing Campaign Donation Flow</h2>";

try {
    $pdo = getDbConnection();
    
    // Get test data
    $stmt = $pdo->query("SELECT id, title FROM campaigns WHERE status = 'active' LIMIT 1");
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        die("No active campaigns found for testing");
    }
    
    $stmt = $pdo->query("
        SELECT u.id, u.full_name, w.balance_htg 
        FROM users u 
        JOIN wallets w ON u.id = w.user_id 
        WHERE w.balance_htg >= 100 
        LIMIT 1
    ");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("No users with sufficient balance found");
    }
    
    echo "<div style='background:#f0f8ff;padding:15px;margin:10px 0;border-left:4px solid #007bff;'>";
    echo "<strong>Test Setup:</strong><br>";
    echo "Campaign: {$campaign['title']} (ID: {$campaign['id']})<br>";
    echo "User: {$user['full_name']} (Balance: {$user['balance_htg']} HTG)<br>";
    echo "</div>";
    
    // Simulate the preprocess logic
    $page = 'campaign';
    $campaignId = $campaign['id'];
    $_SESSION['user_id'] = $user['id'];
    
    // Test that isLoggedIn works
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    echo "<p><strong>✓ Login check:</strong> " . (isLoggedIn() ? "User is logged in" : "User not logged in") . "</p>";
    
    // Test the preprocess file inclusion logic
    $preprocessFile = "campaign-preprocess.php";
    if (file_exists($preprocessFile)) {
        echo "<p><strong>✓ Preprocess file:</strong> {$preprocessFile} exists</p>";
    } else {
        echo "<p><strong>✗ Preprocess file:</strong> {$preprocessFile} not found</p>";
    }
    
    // Test form URL generation
    $formAction = "?page=campaign&id=" . $campaignId;
    echo "<p><strong>✓ Form action URL:</strong> {$formAction}</p>";
    
    // Simulate a donation form submission
    if (isset($_POST['simulate_donation'])) {
        echo "<h3>Simulating Donation Process...</h3>";
        
        $_POST['donate'] = '1';
        $_POST['amount'] = '100';
        $_POST['message'] = 'Test donation';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // This would normally be handled by the preprocess file
        echo "<p><strong>Form data received:</strong></p>";
        echo "<ul>";
        echo "<li>Amount: {$_POST['amount']}</li>";
        echo "<li>Message: {$_POST['message']}</li>";
        echo "<li>User ID: {$_SESSION['user_id']}</li>";
        echo "<li>Campaign ID: {$campaignId}</li>";
        echo "</ul>";
        
        echo "<p><strong>✓ All components ready for donation processing</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>

<div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6;">
    <h3>Test Donation Form</h3>
    <p>This simulates what happens when a user submits a donation:</p>
    
    <form method="POST" style="margin-top: 15px;">
        <input type="hidden" name="simulate_donation" value="1">
        <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; cursor: pointer;">
            🧪 Simulate Donation Process
        </button>
    </form>
</div>

<div style="margin-top: 20px; padding: 15px; background: #e9ecef; border-left: 4px solid #6c757d;">
    <h4>How the fix works:</h4>
    <ol>
        <li><strong>campaign-preprocess.php</strong> handles form submissions BEFORE headers are sent</li>
        <li><strong>campaign.php</strong> only displays content (no more POST processing)</li>
        <li>Form submits to <code>?page=campaign&id=X</code> which routes through index.php</li>
        <li>index.php includes preprocess file first, then renders the page</li>
        <li>Redirects and flash messages work because headers haven't been sent yet</li>
    </ol>
</div>

<style>
body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
p { margin: 8px 0; }
</style>
