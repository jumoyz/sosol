<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

try {
    $db = getDbConnection();
    
    // Get two different users for testing
    $users = $db->query("SELECT id, full_name, email FROM users LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) < 2) {
        echo "Need at least 2 users for testing\n";
        exit;
    }
    
    // Get a SOL group
    $groups = $db->query("SELECT id, name FROM sol_groups LIMIT 1")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($groups) < 1) {
        echo "Need at least 1 SOL group for testing\n";
        exit;
    }
    
    $inviter = $users[0];
    $invitee = $users[1]; 
    $group = $groups[0];
    
    echo "Creating test invitation:\n";
    echo "- From: {$inviter['full_name']} ({$inviter['email']})\n";
    echo "- To: {$invitee['full_name']} ({$invitee['email']})\n";
    echo "- Group: {$group['name']}\n\n";
    
    // Create test invitation
    $invitation_id = generateUUID();
    $stmt = $db->prepare("
        INSERT INTO sol_invitations (id, sol_group_id, invited_by, invited_user_id, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
    ");
    
    $result = $stmt->execute([$invitation_id, $group['id'], $inviter['id'], $invitee['id']]);
    
    if ($result) {
        echo "Test invitation created successfully!\n";
        echo "Invitation ID: $invitation_id\n";
        
        // Verify it was created
        $verify = $db->prepare("
            SELECT si.*, sg.name as group_name, u.full_name as inviter_name
            FROM sol_invitations si
            JOIN sol_groups sg ON si.sol_group_id = sg.id
            JOIN users u ON si.invited_by = u.id
            WHERE si.id = ?
        ");
        $verify->execute([$invitation_id]);
        $invitation = $verify->fetch(PDO::FETCH_ASSOC);
        
        if ($invitation) {
            echo "\nVerification successful:\n";
            echo "- Group: {$invitation['group_name']}\n";
            echo "- Inviter: {$invitation['inviter_name']}\n";
            echo "- Status: {$invitation['status']}\n";
        } else {
            echo "Verification failed - invitation not found\n";
        }
    } else {
        echo "Failed to create test invitation\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
