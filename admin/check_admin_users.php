<?php
require_once '../includes/config.php';

try {
    $pdo = getDbConnection();
    
    echo "=== ADMIN USERS CHECK ===\n\n";
    
    // Check for admin users
    $stmt = $pdo->query("SELECT id, full_name, email, role FROM users WHERE role LIKE '%admin%'");
    $admin_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($admin_users)) {
        echo "No admin users found!\n\n";
        
        // Create a temporary admin user
        echo "Creating temporary admin user...\n";
        
        $stmt = $pdo->query("SELECT id, full_name, email FROM users LIMIT 1");
        $first_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($first_user) {
            $update_stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $update_stmt->execute([$first_user['id']]);
            
            echo "Updated user '{$first_user['full_name']}' to admin role.\n";
            echo "Email: {$first_user['email']}\n";
            echo "You can now login with this user to access admin panel.\n\n";
        }
    } else {
        echo "Found admin users:\n";
        foreach ($admin_users as $admin) {
            echo "  - {$admin['full_name']} ({$admin['email']}) - Role: {$admin['role']}\n";
        }
    }
    
    // Also check session requirements for get_user_details.php
    echo "\n=== SESSION REQUIREMENTS ===\n";
    echo "For get_user_details.php to work, you need either:\n";
    echo "1. \$_SESSION['is_admin'] = true, OR\n";
    echo "2. \$_SESSION['user_role'] = 'admin' (or similar admin role)\n\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>