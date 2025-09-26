<?php
// Simulate the process-invitation.php logic
session_start();

// Set up test session
$_SESSION['user_id'] = '00000000-0000-0000-0000-000000000002'; // Marie Claire
$_SESSION['csrf_token'] = 'test_token';

// Set up test POST data
$_POST['invitation_id'] = 'e3032de1-dbd9-4b2c-849c-520903aa4296'; // The new invitation we created
$_POST['action'] = 'accept';
$_POST['csrf_token'] = 'test_token';

// Include the processing script logic
require_once '../includes/config.php';
require_once '../includes/functions.php';

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

try {
    $invitation_id = $_POST['invitation_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    if (empty($invitation_id) || empty($action)) {
        throw new Exception('Missing required fields');
    }
    
    if (!in_array($action, ['accept', 'reject'])) {
        throw new Exception('Invalid action');
    }
    
    $db = getDbConnection();
    
    // Verify invitation exists and belongs to this user
    $check_stmt = $db->prepare("
        SELECT si.*, sg.name as group_title, sg.member_limit as max_members, u.full_name as inviter_name
        FROM sol_invitations si
        JOIN sol_groups sg ON si.sol_group_id = sg.id
        JOIN users u ON si.invited_by = u.id
        WHERE si.id = ? AND si.invited_user_id = ? AND si.status = 'pending'
    ");
    $check_stmt->execute([$invitation_id, $user_id]);
    $invitation = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invitation) {
        throw new Exception('Invitation not found or already processed');
    }
    
    echo "Found invitation:\n";
    echo "- Group: {$invitation['group_title']}\n";
    echo "- Inviter: {$invitation['inviter_name']}\n";
    echo "- Max members: {$invitation['max_members']}\n\n";
    
    $db->beginTransaction();
    
    $status = ($action === 'accept') ? 'accepted' : 'declined';
    
    // Update invitation status
    $update_stmt = $db->prepare("
        UPDATE sol_invitations 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $result = $update_stmt->execute([$status, $invitation_id]);
    echo "Updated invitation status: " . ($result ? 'Success' : 'Failed') . "\n";
    
    // If accepting, add user to the SOL group
    if ($action === 'accept') {
        // Check if group is not full
        $member_count_stmt = $db->prepare("
            SELECT COUNT(*) as member_count 
            FROM sol_participants 
            WHERE sol_group_id = ?
        ");
        $member_count_stmt->execute([$invitation['sol_group_id']]);
        $member_count = $member_count_stmt->fetchColumn();
        
        echo "Current member count: $member_count\n";
        
        if ($member_count >= $invitation['max_members']) {
            throw new Exception('SOL group is already full');
        }
        
        // Add user as participant
        $participant_id = generateUUID();
        $add_participant_stmt = $db->prepare("
            INSERT INTO sol_participants (id, sol_group_id, user_id, role, join_date, created_at, updated_at)
            VALUES (?, ?, ?, 'member', CURDATE(), NOW(), NOW())
        ");
        $result = $add_participant_stmt->execute([$participant_id, $invitation['sol_group_id'], $user_id]);
        echo "Added participant: " . ($result ? 'Success' : 'Failed') . "\n";
        
        $message = "Successfully joined the SOL group '{$invitation['group_title']}'!";
    } else {
        $message = "Invitation to join '{$invitation['group_title']}' has been declined.";
    }
    
    $db->commit();
    echo "\nTransaction committed successfully!\n";
    echo "Message: $message\n";

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
        echo "Transaction rolled back.\n";
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>
