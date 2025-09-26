<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Simulate admin login for testing
$_SESSION['user_id'] = '1';  
$_SESSION['is_admin'] = true;
$_SESSION['user_role'] = 'admin';

echo "<h3>Testing User Details API</h3>";

try {
    $pdo = getDbConnection();
    
    // Get first user from database
    $stmt = $pdo->query("SELECT id, full_name FROM users LIMIT 1");
    $test_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$test_user) {
        echo "<p>No users found in database.</p>";
        exit;
    }
    
    echo "<p>Testing with User ID: " . $test_user['id'] . " (" . $test_user['full_name'] . ")</p>";
    
    // Test the API endpoint
    $api_url = 'http://localhost/SOSOL/webApp/V1/admin/get_user_details.php?id=' . $test_user['id'];
    
    // Use cURL to test the API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    curl_setopt($ch, CURLOPT_HEADER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<h4>API Response (HTTP $http_code):</h4>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Try to decode JSON
    $data = json_decode($response, true);
    if ($data) {
        echo "<h4>Parsed JSON:</h4>";
        echo "<pre>" . print_r($data, true) . "</pre>";
        
        if (isset($data['success']) && $data['success']) {
            echo "<h4>HTML Preview:</h4>";
            echo "<div style='border: 1px solid #ccc; padding: 10px;'>" . $data['html'] . "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
</style>