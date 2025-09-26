<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

try {
    $db = getDbConnection();
    
    echo "Testing Add Member functionality...\n\n";
    
    // Check if we have a SOL group to test with
    $groups = $db->query("SELECT id, name, admin_id FROM sol_groups LIMIT 1")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($groups)) {
        echo "No SOL groups found for testing.\n";
        exit;
    }
    
    $group = $groups[0];
    echo "Using SOL group: {$group['name']} (ID: {$group['id']})\n";
    
    // Check admin user
    $adminStmt = $db->prepare("SELECT id, full_name, email FROM users WHERE id = ?");
    $adminStmt->execute([$group['admin_id']]);
    $admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "Group admin: {$admin['full_name']} ({$admin['email']})\n\n";
    }
    
    // Check current member count
    $memberCount = $db->prepare("SELECT COUNT(*) FROM sol_participants WHERE sol_group_id = ?");
    $memberCount->execute([$group['id']]);
    $count = $memberCount->fetchColumn();
    
    echo "Current member count: $count\n";
    
    // Test data for new user
    $testName = "Test User " . date('His');
    $testEmail = "testuser" . date('His') . "@example.com";
    $testPhone = "509-" . rand(1000, 9999) . "-" . rand(1000, 9999);
    
    echo "\nTest data for new user:\n";
    echo "- Name: $testName\n";
    echo "- Email: $testEmail\n";
    echo "- Phone: $testPhone\n\n";
    
    // Check if email already exists
    $emailCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
    $emailCheck->execute([$testEmail]);
    
    if ($emailCheck->fetch()) {
        echo "Email already exists (this shouldn't happen with timestamp).\n";
    } else {
        echo "Email is available for new user creation.\n";
    }
    
    echo "\nAdd Member functionality is ready for testing!\n";
    echo "You can now:\n";
    echo "1. Login as the group admin: {$admin['email']}\n";
    echo "2. Go to SOL Group Details page\n";
    echo "3. Click 'Add Member' button\n";
    echo "4. Fill in the form with test data above\n";
    echo "5. Submit to test user creation and group addition\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
