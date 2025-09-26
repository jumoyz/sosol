<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';
require_once '../vendor/autoload.php';
require_once '../services/MailService.php';
require_once '../services/NotificationService.php';

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
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}

try {
    $sol_group_id = $_POST['sol_group_id'] ?? '';
    $invitee_email = $_POST['invitee_email'] ?? '';
    $message = $_POST['message'] ?? '';
    $inviter_id = $_SESSION['user_id'];
    
    if (empty($sol_group_id) || empty($invitee_email)) {
        throw new Exception('Missing required fields');
    }
    
    $db = getDbConnection();
    
    // Check if the user is admin/manager of the group
    $group_check = $db->prepare("
        SELECT sg.*, sp.role 
        FROM sol_groups sg
        JOIN sol_participants sp ON sg.id = sp.sol_group_id
        WHERE sg.id = ? AND sp.user_id = ? AND sp.role IN ('admin', 'manager')
    ");
    $group_check->execute([$sol_group_id, $inviter_id]);
    $group = $group_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        throw new Exception('You do not have permission to invite users to this group');
    }
    
    // Find the user by email
    $user_stmt = $db->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $user_stmt->execute([$invitee_email]);
    $invitee = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invitee) {
        throw new Exception('User not found with the provided email address');
    }
    
    // Check if user is already in the group
    $member_check = $db->prepare("
        SELECT id FROM sol_participants 
        WHERE sol_group_id = ? AND user_id = ?
    ");
    $member_check->execute([$sol_group_id, $invitee['id']]);
    
    if ($member_check->fetch()) {
        throw new Exception('User is already a member of this group');
    }
    
    // Check if invitation already exists (new column names)
    $invitation_check = $db->prepare("
        SELECT id FROM sol_invitations 
        WHERE sol_group_id = ? AND invited_user_id = ? AND status = 'pending'
    ");
    $invitation_check->execute([$sol_group_id, $invitee['id']]);
    
    if ($invitation_check->fetch()) {
        throw new Exception('An invitation has already been sent to this user');
    }
    
    // Create the invitation
    $invitation_id = generateUUID();
    $invite_stmt = $db->prepare("
        INSERT INTO sol_invitations (id, sol_group_id, invited_by, invited_user_id, message, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
    ");
    $invite_stmt->execute([$invitation_id, $sol_group_id, $inviter_id, $invitee['id'], $message]);

    // Send email + notification (best effort)
    try {
        $mailService = new App\MailService();
        $subject = 'You have been invited to join a SOL Group';
        $groupName = $group['name'] ?? 'SOL Group';
        $html = '<p>Hello ' . htmlspecialchars($invitee['full_name']) . ',</p>' .
                '<p>You have been invited to join the SOL group <strong>' . htmlspecialchars($groupName) . '</strong>.</p>' .
                ($message ? '<blockquote>' . nl2br(htmlspecialchars($message)) . '</blockquote>' : '') .
                '<p>Please log in to review and accept the invitation.</p>';
        $mailService->send($invitee_email, $subject, $html);
        $notify = new App\NotificationService($db);
        $notify->notify($invitee['id'], 'sol_invitation', 'SOL Invitation', 'Invitation to join group ' . $groupName);
    } catch (\Throwable $e) {
        error_log('Invitation notification error: ' . $e->getMessage());
    }
    
    // Log the action
    logActivity($inviter_id, 'sol_invitation_sent', "Sent invitation to {$invitee['full_name']} for group {$group['name']}", json_encode([
        'invitation_id' => $invitation_id,
        'sol_group_id' => $sol_group_id,
        'invited_user_id' => $invitee['id']
    ]));
    
    echo json_encode([
        'success' => true,
        'message' => "Invitation sent successfully to {$invitee['full_name']}!"
    ]);

} catch (Exception $e) {
    // Log the error
    if (function_exists('logActivity')) {
        logActivity($_SESSION['user_id'] ?? 'unknown', 'sol_invitation_error', $e->getMessage(), json_encode([
            'sol_group_id' => $sol_group_id ?? '',
            'invitee_email' => $invitee_email ?? ''
        ]));
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
