<?php
session_start();
require_once '../includes/config.php';

// Get the admin user
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin_user) {
        // Set up admin session
        $_SESSION['user_id'] = $admin_user['id'];
        $_SESSION['user_name'] = $admin_user['full_name'];
        $_SESSION['user_email'] = $admin_user['email'];
        $_SESSION['user_role'] = $admin_user['role'];
        $_SESSION['is_verified'] = $admin_user['kyc_verified'];
        $_SESSION['logged_in'] = true;
        $_SESSION['is_admin'] = true; // Explicitly set this
        
        echo "<h2>✅ Admin Session Created!</h2>";
        echo "<p><strong>Logged in as:</strong> {$admin_user['full_name']} ({$admin_user['email']})</p>";
        echo "<p><strong>Role:</strong> {$admin_user['role']}</p>";
        
        echo "<h3>Session Variables:</h3>";
        echo "<pre>";
        print_r($_SESSION);
        echo "</pre>";
        
        echo "<h3>Test Links:</h3>";
        echo "<a href='users.php' style='display: inline-block; background: #007bff; color: white; padding: 10px 20px; text-decoration: none; margin-right: 10px;'>Go to Users Admin Panel</a>";
        echo "<a href='test_session_auth.php' style='display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; margin-right: 10px;'>Test Session Auth</a>";
        
        // Also test the get_user_details API
        $test_stmt = $pdo->query("SELECT id FROM users LIMIT 1");
        $test_user = $test_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test_user) {
            echo "<br><br>";
            echo "<button onclick=\"testAPI('{$test_user['id']}')\" style='background: #dc3545; color: white; border: none; padding: 10px 20px; cursor: pointer;'>Test Get User Details API</button>";
            echo "<div id='api-result'></div>";
        }
        
    } else {
        echo "<h2>❌ No Admin User Found</h2>";
        echo "<p>Please make sure there's a user with role='admin' in the database.</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Database Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>

<script>
function testAPI(userId) {
    const resultDiv = document.getElementById('api-result');
    resultDiv.innerHTML = '<h4>Testing API...</h4>';
    
    fetch('get_user_details.php?id=' + userId)
        .then(response => response.text())
        .then(text => {
            console.log('Raw response:', text);
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    resultDiv.innerHTML = '<h4>✅ API Success!</h4><div style="border: 1px solid #ccc; padding: 10px; margin-top: 10px;">' + data.html + '</div>';
                } else {
                    resultDiv.innerHTML = '<h4>❌ API Error:</h4><p>' + data.message + '</p>';
                }
            } catch (e) {
                resultDiv.innerHTML = '<h4>❌ JSON Parse Error:</h4><p>' + e.message + '</p><h5>Raw Response:</h5><pre>' + text + '</pre>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<h4>❌ Network Error:</h4><p>' + error.message + '</p>';
        });
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
a { margin: 5px; }
</style>