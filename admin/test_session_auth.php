<?php
// Simulate the same session handling as users.php
session_start();

// Include the same files as users.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/functions.php';

echo "<h2>Session Debug Test</h2>";

// Show current session
echo "<h3>Current Session:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Test authentication like get_user_details.php does
echo "<h3>Authentication Test:</h3>";
$userRole = isset($_SESSION['user_role']) ? strtolower(trim((string) $_SESSION['user_role'])) : '';
$isAdminFlag = isset($_SESSION['is_admin']) ? (bool) $_SESSION['is_admin'] : false;
$adminRoleAccepted = in_array($userRole, ['admin', 'super_admin', 'superadmin', 'administrator'], true) || strpos($userRole, 'admin') !== false;

echo "User Role: '$userRole'<br>";
echo "Is Admin Flag: " . ($isAdminFlag ? 'true' : 'false') . "<br>";
echo "Admin Role Accepted: " . ($adminRoleAccepted ? 'true' : 'false') . "<br>";
echo "Authentication Result: " . (($adminRoleAccepted || $isAdminFlag) ? 'PASS' : 'FAIL') . "<br>";

// Get a test user
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT id, full_name FROM users LIMIT 1");
    $test_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_user) {
        echo "<h3>Test API Call:</h3>";
        echo "Test User: {$test_user['full_name']} (ID: {$test_user['id']})<br>";
        
        // Create a test button that calls the API
        echo "<button onclick=\"testAPI('{$test_user['id']}')\">Test Get User Details API</button>";
        echo "<div id='api-result'></div>";
    }
} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage();
}
?>

<script>
function testAPI(userId) {
    const resultDiv = document.getElementById('api-result');
    resultDiv.innerHTML = '<p>Loading...</p>';
    
    fetch('get_user_details.php?id=' + userId)
        .then(response => {
            console.log('Response status:', response.status);
            return response.text(); // Get as text first
        })
        .then(text => {
            console.log('Raw response:', text);
            resultDiv.innerHTML = '<h4>Raw Response:</h4><pre>' + text + '</pre>';
            
            try {
                const data = JSON.parse(text);
                resultDiv.innerHTML += '<h4>Parsed JSON:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
                
                if (data.success) {
                    resultDiv.innerHTML += '<h4>HTML Preview:</h4><div style="border: 1px solid #ccc; padding: 10px;">' + data.html + '</div>';
                }
            } catch (e) {
                resultDiv.innerHTML += '<h4>JSON Parse Error:</h4><p>' + e.message + '</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultDiv.innerHTML = '<h4>Network Error:</h4><p>' + error.message + '</p>';
        });
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
button { background: #007bff; color: white; border: none; padding: 10px 20px; cursor: pointer; }
</style>