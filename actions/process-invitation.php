<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

/**
 * Validates the CSRF token.
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
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
    
    $db->beginTransaction();
    
    $status = ($action === 'accept') ? 'accepted' : 'declined';
    
    // Update invitation status
    $update_stmt = $db->prepare("
        UPDATE sol_invitations 
        SET status = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $update_stmt->execute([$status, $invitation_id]);
    
    // If accepting, add user to the SOL group
    if ($action === 'accept') {
        // Check if user is already a member (shouldn't happen with invitation system, but safety check)
        $existing_member_check = $db->prepare("
            SELECT id FROM sol_participants 
            WHERE sol_group_id = ? AND user_id = ?
        ");
        $existing_member_check->execute([$invitation['sol_group_id'], $user_id]);
        
        if ($existing_member_check->fetch()) {
            throw new Exception('You are already a member of this group');
        }
        
        // Check if group is not full
        $member_count_stmt = $db->prepare("
            SELECT COUNT(*) as member_count 
            FROM sol_participants 
            WHERE sol_group_id = ?
        ");
        $member_count_stmt->execute([$invitation['sol_group_id']]);
        $member_count = $member_count_stmt->fetchColumn();
        
        if ($member_count >= $invitation['max_members']) {
            throw new Exception('SOL group is already full');
        }
        
        // Add user as participant
        $participant_id = generateUUID();
        $add_participant_stmt = $db->prepare("
            INSERT INTO sol_participants (id, sol_group_id, user_id, role, join_date, payout_position, created_at, updated_at)
            VALUES (?, ?, ?, 'member', CURDATE(), ?, NOW(), NOW())
        ");
        // Set payout_order as the next available number
        $add_participant_stmt->execute([$participant_id, $invitation['sol_group_id'], $user_id, $member_count + 1]);
        
        // Log the action
        logActivity($db, $user_id, 'sol_invitation_accepted', $invitation_id, [
            'sol_group_id' => $invitation['sol_group_id'],
            'group_name' => $invitation['group_title']
        ]);
        
        $message = "Successfully joined the SOL group '{$invitation['group_title']}'!";
    } else {
        // Log the rejection
        logActivity($db, $user_id, 'sol_invitation_rejected', $invitation_id, [
            'sol_group_id' => $invitation['sol_group_id'],
            'group_name' => $invitation['group_title']
        ]);
        
        $message = "Invitation to join '{$invitation['group_title']}' has been declined.";
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    
    // Log the error
    if (function_exists('logActivity') && isset($db)) {
        logActivity($db, $_SESSION['user_id'] ?? 'unknown', 'sol_invitation_error', $invitation_id ?? '', [
            'action' => $action ?? '',
            'error_message' => $e->getMessage()
        ]);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
