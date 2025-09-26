<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';
// Set page title
$pageTitle = "Join SOL Group";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
    exit;
}

// Get SOL group ID from URL
$groupId = $_GET['id'] ?? null;

if (!$groupId) {
    setFlashMessage('error', 'SOL Group ID is required.');
    redirect('?page=sol-groups');
    exit;
}

// Initialize variables
$group = null;
$memberCount = 0;
$nextPayoutDate = null;
$contributionDueDate = null;
$walletBalance = 0;
$errors = [];
$success = false;

try {
    $db = getDbConnection();
    
    // Get user wallet balance with better error handling
    try {
        $walletStmt = $db->prepare("SELECT balance_htg FROM wallets WHERE user_id = ?");
        $walletStmt->execute([$userId]);
        $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($wallet) {
            $walletBalance = floatval($wallet['balance_htg']);
        } else {
            // Create wallet if it doesn't exist
            $createWalletStmt = $db->prepare("
                INSERT INTO wallets (user_id, balance_htg, balance_usd, created_at, updated_at) 
                VALUES (?, 0, 0, NOW(), NOW())
            ");
            $createWalletStmt->execute([$userId]);
            $walletBalance = 0;
        }
    } catch (PDOException $walletError) {
        error_log('Wallet fetch error for user ' . $userId . ': ' . $walletError->getMessage());
        $walletBalance = 0;
    }
    
    // Get SOL group details
    $groupStmt = $db->prepare("
        SELECT sg.*, 
               COUNT(sp.user_id) as member_count,
               u.full_name as admin_name
        FROM sol_groups sg
        LEFT JOIN sol_participants sp ON sg.id = sp.sol_group_id
        LEFT JOIN users u ON sg.admin_id = u.id
        WHERE sg.id = ?
        GROUP BY sg.id, sg.name, sg.description, sg.admin_id, sg.member_limit, sg.contribution, 
                 sg.frequency, sg.total_cycles, sg.current_cycle, sg.status, sg.visibility, 
                 sg.start_date, sg.created_at, sg.updated_at, u.full_name
    ");
    $groupStmt->execute([$groupId]);
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        setFlashMessage('error', 'SOL Group not found.');
        redirect('?page=sol-groups');
        exit;
    }
    
    // Check if group is full
    $memberCount = intval($group['member_count']);
    if ($memberCount >= $group['member_limit']) {
        setFlashMessage('error', 'This SOL Group is full and cannot accept new members.');
        redirect('?page=sol-groups');
        exit;
    }
    
    // Check if group is active
    if ($group['status'] !== 'active') {
        setFlashMessage('error', 'This SOL Group is not currently accepting new members.');
        redirect('?page=sol-groups');
        exit;
    }
    
    // Check if the user is already a member
    $memberCheckStmt = $db->prepare("
        SELECT COUNT(*) as is_member
        FROM sol_participants
        WHERE sol_group_id = ? AND user_id = ?
    ");
    $memberCheckStmt->execute([$groupId, $userId]);
    $memberCheck = $memberCheckStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($memberCheck['is_member'] > 0) {
        setFlashMessage('error', 'You are already a member of this SOL Group.');
        redirect('?page=sol-details&id=' . $groupId);
        exit;
    }

    // Calculate next payout position (should be the next available position)
    $positionStmt = $db->prepare("
        SELECT MAX(payout_position) as max_position
        FROM sol_participants
        WHERE sol_group_id = ?
    ");
    $positionStmt->execute([$groupId]);
    $positionData = $positionStmt->fetch(PDO::FETCH_ASSOC);
    $nextPosition = ($positionData['max_position'] ?? 0) + 1;
    
    // Calculate contribution due date based on frequency
    $frequencyDays = [
        'daily' => 1,
        'every3days' => 3,
        'weekly' => 7,
        'biweekly' => 14,
        'monthly' => 30
    ];
    $dayOffset = $frequencyDays[$group['frequency']] ?? 30;
    //$contributionDueDate = date('Y-m-d', strtotime("+{$dayOffset} days"));
    $baseDate = $group['start_date'] ?? date('Y-m-d');
    $contributionDueDate = date('Y-m-d', strtotime($baseDate . " +{$dayOffset} days"));

    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_group'])) {
        // Check if user is verified (KYC approved)
        $userStmt = $db->prepare("SELECT kyc_verified FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['kyc_verified']) {
            $errors[] = 'You must complete KYC verification to join a SOL group.';
        }
        
        // Check if user has enough funds for first contribution
        if ($walletBalance < $group['contribution']) {
            $errors[] = 'Insufficient funds in your wallet. Please add funds before joining this group.';
        }
        
        // Verify group is still available
        $groupCheckStmt = $db->prepare("
            SELECT COUNT(*) as current_members 
            FROM sol_participants 
            WHERE sol_group_id = ?
        ");
        $groupCheckStmt->execute([$groupId]);
        $currentMembers = $groupCheckStmt->fetchColumn();
        
        if ($currentMembers >= $group['member_limit']) {
            $errors[] = 'This group has reached its member limit.';
        }
        
        // Process join request if no errors
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Generate participant ID
                $participantId = generateUuid();
                if (empty($participantId)) {
                    // Fallback UUID generation
                    $participantId = uniqid('sol_', true);
                }
                
                // Validate required data
                if (empty($participantId) || empty($groupId) || empty($userId) || empty($nextPosition)) {
                    throw new Exception('Missing required data for joining group');
                }
                
                // Debug logging
                error_log("Attempting to join SOL group - User: $userId, Group: $groupId, Position: $nextPosition, ParticipantID: $participantId");
                
                // Add user to the SOL group with correct column names
                $joinStmt = $db->prepare("
                    INSERT INTO sol_participants (
                        id, sol_group_id, user_id, payout_position, 
                        contribution_status, total_contributed, total_received,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, 
                        'pending', 0.00, 0.00,
                        NOW(), NOW()
                    )
                ");
                
                $success = $joinStmt->execute([
                    $participantId, 
                    $groupId, 
                    $userId,
                    $nextPosition
                ]);
                
                if (!$success) {
                    $errorInfo = $joinStmt->errorInfo();
                    error_log('SQL Error: ' . print_r($errorInfo, true));
                    throw new PDOException('Failed to insert participant record: ' . $errorInfo[2]);
                }
                
                // Verify the insertion was successful
                $verifyStmt = $db->prepare("SELECT id FROM sol_participants WHERE id = ?");
                $verifyStmt->execute([$participantId]);
                $inserted = $verifyStmt->fetch();
                
                if (!$inserted) {
                    throw new PDOException('Participant record was not properly inserted');
                }
                
                // Log activity if activities table exists
                try {
                    $activityStmt = $db->prepare("
                        INSERT INTO activities (user_id, activity_type, reference_id, details, created_at)
                        VALUES (?, 'sol_join', ?, ?, NOW())
                    ");
                    $activityStmt->execute([
                        $userId, 
                        $groupId, 
                        json_encode([
                            'group_name' => $group['name'], 
                            'position' => $nextPosition,
                            'contribution_amount' => $group['contribution']
                        ])
                    ]);
                } catch (PDOException $activityError) {
                    // Activity logging failed, but don't fail the whole transaction
                    error_log('Activity logging failed: ' . $activityError->getMessage());
                }
                
                $db->commit();
                
                // Set success message
                if (function_exists('setFlashMessage')) {
                    setFlashMessage('success', 'You have successfully joined the SOL group!');
                } else {
                    $_SESSION['flash_type'] = 'success';
                    $_SESSION['flash_message'] = 'You have successfully joined the SOL group!';
                }
                
                // Redirect to group details
                redirect('?page=sol-details&id=' . $groupId);
                exit;
                
            } catch (PDOException $e) {
                $db->rollBack();
                $errorMsg = 'SOL join error - User: ' . $userId . ', Group: ' . $groupId . ', Error: ' . $e->getMessage();
                error_log($errorMsg);
                error_log('SQL State: ' . $e->getCode());
                
                // In development, show more detailed error
                if (defined('APP_ENV') && APP_ENV === 'development') {
                    $errors[] = 'Database error: ' . $e->getMessage();
                } else {
                    $errors[] = 'An error occurred while joining the SOL group. Please try again.';
                }
            } catch (Exception $e) {
                $db->rollBack();
                error_log('General SOL join error - User: ' . $userId . ', Group: ' . $groupId . ', Error: ' . $e->getMessage());
                
                if (defined('APP_ENV') && APP_ENV === 'development') {
                    $errors[] = 'System error: ' . $e->getMessage();
                } else {
                    $errors[] = 'An error occurred while joining the SOL group. Please try again.';
                }
            }
        }
    }
    
} catch (PDOException $e) {
    error_log('SOL join error: ' . $e->getMessage());
    $error = 'An error occurred while loading the SOL group information.';
}
?>

<div class="container">
    <!-- Back Button -->
    <div class="mb-4">
        <a href="?page=sol-groups" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to SOL Groups
        </a>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-4">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>Unable to Join Group</h6>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- SOL Group Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-3">Join SOL Group</h4>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-users fa-lg"></i>
                            </div>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($group['name']) ?></h5>
                            <p class="text-muted mb-0">
                                <i class="fas fa-user me-1"></i> Created by <?= htmlspecialchars($group['admin_name']) ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($group['description'])): ?>
                        <p class="mb-4"><?= nl2br(htmlspecialchars($group['description'])) ?></p>
                    <?php endif; ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="card bg-light">
                                <div class="card-body p-3">
                                    <h6 class="mb-2">Group Details</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li class="d-flex justify-content-between mb-2">
                                            <span>Status:</span>
                                            <span class="badge bg-success"><?= ucfirst($group['status']) ?></span>
                                        </li>
                                        <li class="d-flex justify-content-between mb-2">
                                            <span>Current Cycle:</span>
                                            <span><?= $group['current_cycle'] ?> of <?= $group['total_cycles'] ?></span>
                                        </li>
                                        <li class="d-flex justify-content-between mb-2">
                                            <span>Frequency:</span>
                                            <span><?= ucfirst($group['frequency']) ?></span>
                                        </li>
                                        <li class="d-flex justify-content-between">
                                            <span>Members:</span>
                                            <span><?= $memberCount ?>/<?= $group['member_limit'] ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body p-3">
                                    <h6 class="mb-2">Financial Details</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li class="d-flex justify-content-between mb-2">
                                            <span>Contribution:</span>
                                            <span class="fw-bold"><?= number_format($group['contribution']) ?> HTG</span>
                                        </li>
                                        <li class="d-flex justify-content-between mb-2">
                                            <span>Payout Amount:</span>
                                            <span class="fw-bold">
                                                <?= number_format($group['contribution'] * $group['member_limit']) ?> HTG
                                            </span>
                                        </li>
                                        <li class="d-flex justify-content-between mb-2">
                                            <span>Your Position:</span>
                                            <span><?= $nextPosition ?> of <?= $group['member_limit'] ?></span>
                                        </li>
                                        <li class="d-flex justify-content-between">
                                            <span>First Due Date:</span>
                                            <span><?= date('M j, Y', strtotime($contributionDueDate)) ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> About Your Commitment</h6>
                        <p class="mb-0">By joining this SOL group, you agree to make regular <?= ucfirst($group['frequency']) ?> contributions of <?= number_format($group['contribution']) ?> HTG until the group completes all <?= $group['total_cycles'] ?> cycles. Your payout position will be #<?= $nextPosition ?>.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
                    <!--<form method="POST" action="<?= APP_URL ?>/?page=join-sol&?id=<?= $groupId ?>">  -->
                     <form method="POST" action="">
                        <input type="hidden" name="group_id" value="<?= htmlspecialchars($groupId) ?>">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Join Confirmation</h5>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <span>Your Wallet Balance:</span>
                        <span class="fw-bold"><?= number_format($walletBalance) ?> HTG</span>
                    </div>
                    
                    <div class="alert <?= $walletBalance >= $group['contribution'] ? 'alert-success' : 'alert-danger' ?> mb-4">
                        <?php if ($walletBalance >= $group['contribution']): ?>
                            <i class="fas fa-check-circle me-2"></i> You have enough funds for your first contribution
                        <?php else: ?>
                            <i class="fas fa-exclamation-circle me-2"></i> Insufficient funds for first contribution
                        <?php endif; ?>
                    </div>
                    
                    <!--<form method="POST" action="<?= APP_URL ?>/?page=join-sol&?id=<?= $groupId ?>">  -->
                     <form method="POST" action="?page=sol-join&id=<?= $groupId ?>">
                        <div class="d-grid gap-2">
                            <button type="submit" name="join_group" class="btn btn-primary" <?= $walletBalance < $group['contribution'] ? 'disabled' : '' ?>>
                                <i class="fas fa-handshake me-2"></i> Confirm & Join Group
                            </button>
                            <a href="?page=sol-groups" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Important Information -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Important Information</h5>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-calendar-alt text-primary me-2"></i> Regular Contribution</h6>
                        <p class="text-muted small">You'll need to make <?= ucfirst($group['frequency']) ?> contributions of <?= number_format($group['contribution']) ?> HTG.</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-user-check text-primary me-2"></i> Commitment</h6>
                        <p class="text-muted small">SOL groups rely on all members fulfilling their commitments. Missing contributions may result in penalties.</p>
                    </div>
                    
                    <div>
                        <h6><i class="fas fa-shield-alt text-primary me-2"></i> Security</h6>
                        <p class="text-muted small mb-0">Your funds are protected through our secure platform and escrow system.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>