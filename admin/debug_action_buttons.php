<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Create admin session if not exists
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['user_id'] = '1';
    $_SESSION['is_admin'] = true;
    $_SESSION['user_role'] = 'admin';
    $_SESSION['admin_username'] = 'test_admin';
    $_SESSION['admin_email'] = 'admin@test.com';
}

echo "<h2>Testing Transaction Action Buttons</h2>";

echo "<h3>1. Test Transaction Details Endpoint</h3>";
$transaction_id = 1;
echo "<p><strong>Testing:</strong> <code>get_transaction_details.php?id=$transaction_id</code></p>";

// Test the endpoint
$url = "http://sosol.local/admin/get_transaction_details.php?id=$transaction_id";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: ' . session_name() . '=' . session_id()
    ]
]);

try {
    $response = file_get_contents($url, false, $context);
    echo "<div class='alert alert-success'>✅ Response received</div>";
    echo "<pre style='background: #f8f9fa; padding: 10px; max-height: 300px; overflow-y: auto;'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
    // Try to decode as JSON
    $data = json_decode($response, true);
    if ($data) {
        echo "<div class='alert alert-info'>📋 JSON Response Structure:</div>";
        echo "<ul>";
        foreach ($data as $key => $value) {
            echo "<li><strong>$key:</strong> " . (is_string($value) ? substr($value, 0, 100) . '...' : json_encode($value)) . "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "<h3>2. Test Session Data</h3>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Admin ID: " . ($_SESSION['admin_id'] ?? 'Not set') . "\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
echo "Is Admin: " . ($_SESSION['is_admin'] ? 'Yes' : 'No') . "\n";
echo "User Role: " . ($_SESSION['user_role'] ?? 'Not set') . "\n";
echo "</pre>";

echo "<h3>3. Test Database Connection</h3>";
try {
    $pdo = getDbConnection();
    echo "<div class='alert alert-success'>✅ Database connection successful</div>";
    
    // Test transaction query
    $stmt = $pdo->prepare("SELECT id, transaction_id, type, amount, currency, status, user_id FROM transactions LIMIT 3");
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Sample Transactions:</h4>";
    echo "<table class='table table-sm'>";
    echo "<tr><th>ID</th><th>Transaction ID</th><th>Type</th><th>Amount</th><th>Status</th><th>User ID</th></tr>";
    foreach ($transactions as $t) {
        echo "<tr>";
        echo "<td>{$t['id']}</td>";
        echo "<td>{$t['transaction_id']}</td>";
        echo "<td>{$t['type']}</td>";
        echo "<td>{$t['amount']} {$t['currency']}</td>";
        echo "<td>{$t['status']}</td>";
        echo "<td>{$t['user_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Database error: " . $e->getMessage() . "</div>";
}

echo "<h3>4. JavaScript Test</h3>";
echo "<p>Test the action buttons below:</p>";

echo "<div class='btn-group' data-transaction-id='1' data-user-id='1' data-amount='100.00' data-currency='HTG' data-type='deposit' data-user-name='Test User' data-user-email='test@example.com'>";
echo "<button class='btn btn-outline-primary' onclick='viewTransaction(1)'>🔍 View</button>";
echo "<button class='btn btn-outline-success' onclick='approveTransaction(1)'>✅ Approve</button>";
echo "<button class='btn btn-outline-danger' onclick='rejectTransaction(1)'>❌ Reject</button>";
echo "</div>";

?>

<script>
// Test JavaScript functions (simplified versions)
function viewTransaction(id) {
    alert('View Transaction called with ID: ' + id);
    console.log('Fetching transaction details for ID:', id);
    
    fetch('get_transaction_details.php?id=' + id, {
        credentials: 'same-origin'
    })
    .then(response => response.text())
    .then(data => {
        console.log('Response:', data);
        alert('Response received - check console for details');
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}

function approveTransaction(id) {
    const buttonGroup = event.target.closest('.btn-group');
    const data = {
        id: id,
        userId: buttonGroup.dataset.userId,
        amount: buttonGroup.dataset.amount,
        currency: buttonGroup.dataset.currency,
        type: buttonGroup.dataset.type,
        userName: buttonGroup.dataset.userName,
        userEmail: buttonGroup.dataset.userEmail
    };
    
    console.log('Approve Transaction Data:', data);
    alert('Approve Transaction called with data: ' + JSON.stringify(data, null, 2));
}

function rejectTransaction(id) {
    const buttonGroup = event.target.closest('.btn-group');
    const data = {
        id: id,
        userId: buttonGroup.dataset.userId,
        amount: buttonGroup.dataset.amount,
        currency: buttonGroup.dataset.currency,
        type: buttonGroup.dataset.type,
        userName: buttonGroup.dataset.userName,
        userEmail: buttonGroup.dataset.userEmail
    };
    
    console.log('Reject Transaction Data:', data);
    alert('Reject Transaction called with data: ' + JSON.stringify(data, null, 2));
}
</script>

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
.alert { padding: 10px; margin: 10px 0; border-radius: 5px; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #b6d4ea; }
.alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.table { border-collapse: collapse; width: 100%; }
.table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
.table th { background: #f8f9fa; }
.btn { padding: 8px 12px; margin: 2px; border: 1px solid #ccc; background: #fff; cursor: pointer; }
.btn:hover { background: #f8f9fa; }
.btn-group { display: inline-block; }
</style>

<p><a href="transactions.php">← Back to Transactions</a></p>