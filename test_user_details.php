<?php
// Test get_user_details.php functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate admin session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['is_admin'] = true;

require_once 'includes/config.php';

try {
    $pdo = getDbConnection();
    echo "<h3>Testing User Details Functionality</h3>";
    
    // Get all users to see what IDs are available
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users ORDER BY id ASC LIMIT 10");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Available Users:</h4>";
    if (empty($users)) {
        echo "<p style='color: red;'>No users found in database!</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Action</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td><button onclick=\"testUserDetails(" . $user['id'] . ")\">Test View</button></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Test the first user
        if (!empty($users)) {
            $testUserId = $users[0]['id'];
            echo "<h4>Testing User ID: {$testUserId}</h4>";
            
            // Simulate the AJAX call
            $_GET['id'] = $testUserId;
            
            echo "<iframe src='admin/get_user_details.php?id={$testUserId}' width='100%' height='400'></iframe>";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<script>
function testUserDetails(userId) {
    fetch('admin/get_user_details.php?id=' + userId)
        .then(response => response.json())
        .then(data => {
            console.log('Response:', data);
            if (data.success) {
                alert('Success! User details loaded.');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error: ' + error.message);
        });
}
</script>