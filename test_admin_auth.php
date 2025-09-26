<?php
// Test admin authentication
session_start();

echo "<h3>Session Debug:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Required for Admin:</h3>";
$userRole = isset($_SESSION['user_role']) ? strtolower(trim((string) $_SESSION['user_role'])) : '';
$isAdminFlag = isset($_SESSION['is_admin']) ? (bool) $_SESSION['is_admin'] : false;

echo "User Role: " . ($userRole ?: '(none)') . "<br>";
echo "Is Admin Flag: " . ($isAdminFlag ? 'true' : 'false') . "<br>";

$adminRoleAccepted = in_array($userRole, ['admin', 'super_admin', 'superadmin', 'administrator'], true) || strpos($userRole, 'admin') !== false;

echo "Admin Role Accepted: " . ($adminRoleAccepted ? 'true' : 'false') . "<br>";
echo "Overall Admin Access: " . (($adminRoleAccepted || $isAdminFlag) ? 'YES' : 'NO') . "<br>";

if (!($adminRoleAccepted || $isAdminFlag)) {
    echo "<p style='color: red;'><strong>⚠ Admin access denied - this is why the admin panel isn't working!</strong></p>";
    echo "<p>To fix this, you need to either:</p>";
    echo "<ol>";
    echo "<li>Login as a user with admin role</li>";
    echo "<li>Set \$_SESSION['user_role'] = 'admin'</li>";
    echo "<li>Set \$_SESSION['is_admin'] = true</li>";
    echo "</ol>";
} else {
    echo "<p style='color: green;'><strong>✓ Admin access granted</strong></p>";
}
?>