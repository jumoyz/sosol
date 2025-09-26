<?php
// Simulate admin login for testing
session_start();

// Set admin session variables
$_SESSION['user_id'] = 1; // Assuming first user is admin
$_SESSION['user_name'] = 'Test Admin';
$_SESSION['user_email'] = 'admin@test.com';
$_SESSION['user_role'] = 'admin';
$_SESSION['is_admin'] = true;
$_SESSION['last_activity'] = time();

echo "✓ Admin session created successfully<br>";
echo "Session data:<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<p><a href='admin/users.php'>Go to Admin Users Page</a></p>";
echo "<p><a href='test_admin_auth.php'>Test Admin Auth</a></p>";
echo "<p><a href='test_users.php'>Test Database Connection</a></p>";
?>