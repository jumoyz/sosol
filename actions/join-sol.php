<?php
/**
 * Join SOL Group Action Handler
 * 
 * Processes user requests to join a SOL Group
 */
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/flash-messages.php';
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../services/MailService.php';
require_once __DIR__.'/../services/NotificationService.php';

// Require user to be logged in
if (!isLoggedIn()) {
    redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Database connection (assuming $db is your PDO instance)
 $db = getDbConnection();

$user_id  = $_SESSION['user_id'] ?? null;
$group_id = intval($_GET['id'] ?? 0);

if (!$user_id || $group_id <= 0) {
    $_SESSION['error'] = "Invalid request.";
    redirect('?page=sol-groups');
    exit;
}

try {
    // 1. Check if group exists
    $stmt = $db->prepare("SELECT id FROM sol_groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    if (!$group) {
        $_SESSION['error'] = "SOL Group not found.";
        redirect('?page=sol-groups');
        exit;
    }

    // 2. Check if already a member
    $stmt = $db->prepare("SELECT id FROM sol_participants WHERE user_id = ? AND sol_group_id = ?");
    $stmt->execute([$user_id, $group_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        $_SESSION['info'] = "You are already a member of this SOL Group.";
        redirect('?page=sol-groups');
        exit;
    }

    // 3. Insert membership
    $participant_id = generateUuid(); // implement generateUuid() in functions.php 
    if (!$participant_id) {
        $_SESSION['error'] = "An error occurred while joining the SOL Group. Please try again.";
        redirect('?page=sol-groups');
        exit;
    }   
    $role = 'member';
    $frequency = getGroupFrequency($group_id); // implement a helper to fetch it
    $payout_position = getNextPayoutPosition($group_id); // count current members + 1
    $contribution_due_date = calculateContributionDueDate($frequency);
    //$stmt = $db->prepare("INSERT INTO sol_participants (id, sol_group_id, user_id, join_date) VALUES (?, ?, NOW())");
    $stmt = $db->prepare("
        INSERT INTO sol_participants 
        (id, sol_group_id, user_id, role, join_date, contribution_due_date, payout_position) 
        VALUES (?, ?, ?, ?, NOW(), ?, ?)
    ");
        $stmt->execute([
        $participant_id,
        $group_id,
        $user_id,
        $role,
        $contribution_due_date,
        $payout_position
    ]);
    if ($stmt->execute([$participant_id, $group_id, $user_id, $role, $contribution_due_date, $payout_position])) {
        $_SESSION['success'] = "You have successfully joined the SOL Group.";
        // Send notification to group admins/managers (best effort)
        try {
            $pdo = $db; // alias
            $admins = $pdo->prepare("SELECT u.email, u.full_name FROM sol_participants sp JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? AND sp.role IN ('admin','manager')");
            $admins->execute([$group_id]);
            $mailService = new App\MailService();
            $notify = new App\NotificationService($pdo);
            while ($a = $admins->fetch(PDO::FETCH_ASSOC)) {
                $subject = 'A member joined your SOL Group';
                $html = '<p>User ID ' . htmlspecialchars($user_id) . ' joined group #' . htmlspecialchars($group_id) . '.</p>';
                $mailService->send($a['email'], $subject, $html);
            }
            $notify->notify($user_id, 'sol_join', 'Joined SOL Group', 'You joined group #' . $group_id);
        } catch (\Throwable $e) {
            error_log('Join SOL notifications error: ' . $e->getMessage());
        }
    } else {
        $_SESSION['error'] = "An error occurred while joining the SOL Group. Please try again.";
    }

} catch (PDOException $e) {
    // Log error (for debugging)
    error_log("Join SOL Error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred. Please try again.";
}

// Redirect back to groups page
redirect('?page=sol-groups');
exit;
