<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Create admin session if not exists
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'test_admin';
    $_SESSION['admin_email'] = 'admin@test.com';
    $_SESSION['admin_role'] = 'admin';
}

// Test the get_transaction_details.php endpoint
$transaction_id = 1; // Test with first transaction

echo "<h2>Testing Transaction Details Endpoint</h2>";
echo "<p>Testing get_transaction_details.php with transaction ID: $transaction_id</p>";

// Make a cURL request to the endpoint
$url = 'http://sosol.local/admin/get_transaction_details.php?id=' . $transaction_id;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>Response (HTTP $http_code):</h3>";
echo "<pre style='background: #f8f9fa; padding: 15px; border: 1px solid #ddd;'>";
echo htmlspecialchars($response);
echo "</pre>";

// Also test direct inclusion
echo "<h3>Direct PHP Include Test:</h3>";
echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #ddd;'>";
$_GET['id'] = $transaction_id;
ob_start();
try {
    include 'get_transaction_details.php';
    $direct_output = ob_get_contents();
} catch (Exception $e) {
    $direct_output = "Error: " . $e->getMessage();
}
ob_end_clean();
echo htmlspecialchars($direct_output);
echo "</div>";

echo "<br><a href='transactions.php' class='btn btn-primary'>Back to Transactions</a>";
?>