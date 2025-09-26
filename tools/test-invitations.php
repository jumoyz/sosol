<?php
// Test invitation functionality
require_once '../includes/config.php';
require_once '../includes/functions.php';

try {
    $db = getDbConnection();
    
    // First, let's check if the sol_invitations table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'sol_invitations'");
    if ($tableCheck->rowCount() == 0) {
        echo "Creating sol_invitations table...\n";
        
        // Create the table
        $createTable = "
        CREATE TABLE sol_invitations (
            id CHAR(36) PRIMARY KEY,
            sol_group_id CHAR(36) NOT NULL,
            inviter_id CHAR(36) NOT NULL,
            invitee_id CHAR(36) NOT NULL,
            status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
            message TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (sol_group_id) REFERENCES sol_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE,
            
            UNIQUE KEY unique_invitation (sol_group_id, invitee_id),
            
            INDEX idx_sol_invitations_invitee (invitee_id),
            INDEX idx_sol_invitations_group (sol_group_id),
            INDEX idx_sol_invitations_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($createTable);
        echo "Table created successfully!\n";
    } else {
        echo "sol_invitations table already exists.\n";
    }
    
    // Check existing data
    echo "\nChecking existing users and groups...\n";
    
    $users = $db->query("SELECT id, full_name, email FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "Users:\n";
    foreach ($users as $user) {
        echo "- {$user['id']}: {$user['full_name']} ({$user['email']})\n";
    }
    
    $groups = $db->query("SELECT id, name FROM sol_groups LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nSOL Groups:\n";
    foreach ($groups as $group) {
        echo "- {$group['id']}: {$group['name']}\n";
    }
    
    echo "\nTable structure verified successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
