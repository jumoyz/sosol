<?php
/**
 * Manage SOL Group
 * Administrative page for SOL group management
 */

// Set page title
$pageTitle = "Manage SOL Groups";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Get group ID from URL
$groupId = $_GET['id'] ?? null;

if (!$groupId) {
    setFlashMessage('error', 'Group ID is missing.');
    redirect('?page=sol-groups');
}

// Initialize variables
$group = null;
$members = [];
$contributions = [];
$payouts = [];
$invitations = [];
$userRole = null;
$error = null;

try {
    $db = getDbConnection();
    
    // Get group data and verify user has permission to manage
    $groupStmt = $db->prepare("
        SELECT sg.*, sp.role,
               COUNT(DISTINCT sp2.user_id) as current_members
        FROM sol_groups sg
        INNER JOIN sol_participants sp ON sg.id = sp.sol_group_id
        LEFT JOIN sol_participants sp2 ON sg.id = sp2.sol_group_id
        WHERE sg.id = ? AND sp.user_id = ? AND (sp.role = 'admin' OR sp.role = 'manager')
        GROUP BY sg.id, sp.role
    ");
    $groupStmt->execute([$groupId, $userId]);
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        setFlashMessage('error', 'SOL group not found or you do not have permission to manage it.');
        redirect('?page=sol-groups');
    }
    
    $userRole = $group['role'];
    
    // Get all members with detailed info
    $membersStmt = $db->prepare("
        SELECT sp.*, u.full_name, u.email, u.profile_photo, u.kyc_verified,
               COUNT(sc.id) as total_contributions,
               COALESCE(SUM(sc.amount), 0) as total_contributed,
               sp.payout_received
        FROM sol_participants sp
        INNER JOIN users u ON sp.user_id = u.id
        LEFT JOIN sol_contributions sc ON sp.id = sc.participant_id
        WHERE sp.sol_group_id = ?
        GROUP BY sp.id, u.id
        ORDER BY sp.payout_position ASC
    ");
    $membersStmt->execute([$groupId]);
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent contributions
    $contributionsStmt = $db->prepare("
        SELECT sc.*, u.full_name, u.profile_photo,
               sp.payout_position
        FROM sol_contributions sc
        INNER JOIN sol_participants sp ON sc.participant_id = sp.id
        INNER JOIN users u ON sp.user_id = u.id
        WHERE sp.sol_group_id = ?
        ORDER BY sc.created_at DESC
        LIMIT 20
    ");
    $contributionsStmt->execute([$groupId]);
    $contributions = $contributionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get payouts schedule/status per participant (with user info)
    $payoutsStmt = $db->prepare("
        SELECT
            sp.id,
            sp.user_id,
            sp.payout_position,
            sp.payout_received,
            u.full_name,
            u.profile_photo
        FROM sol_participants sp
        INNER JOIN users u ON sp.user_id = u.id
        WHERE sp.sol_group_id = ?
        ORDER BY sp.payout_position ASC
    ");
    $payoutsStmt->execute([$groupId]);
    $payouts = $payoutsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get pending invitations
    $invitationsStmt = $db->prepare("
        SELECT si.*, u.full_name as invited_user_name, u.email as invited_email,
               u2.full_name as invited_by_name
        FROM sol_invitations si
        INNER JOIN users u ON si.invited_user_id = u.id
        INNER JOIN users u2 ON si.invited_by = u2.id
        WHERE si.sol_group_id = ? AND si.status = 'pending'
        ORDER BY si.created_at DESC
    ");
    $invitationsStmt->execute([$groupId]);
    $invitations = $invitationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('SOL manage error: ' . $e->getMessage());
    $error = 'An error occurred while loading the SOL group management data.';
}

// Handle member role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_member_role'])) {
    $memberId = $_POST['member_id'] ?? null;
    $newRole = $_POST['new_role'] ?? null;
    
    if ($memberId && $newRole && in_array($newRole, ['member', 'manager', 'admin'])) {
        try {
            $updateRoleStmt = $db->prepare("
                UPDATE sol_participants 
                SET role = ?, updated_at = NOW() 
                WHERE id = ? AND sol_group_id = ?
            ");
            $updateRoleStmt->execute([$newRole, $memberId, $groupId]);
            
            setFlashMessage('success', 'Member role updated successfully.');
        } catch (PDOException $e) {
            error_log('Role update error: ' . $e->getMessage());
            setFlashMessage('error', 'Failed to update member role.');
        }
    } else {
        setFlashMessage('error', 'Invalid role update request.');
    }
    
    redirect('?page=sol-manage&id=' . $groupId);
}

// Handle member removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_member'])) {
    $memberId = $_POST['member_id'] ?? null;
    
    if ($memberId) {
        try {
            $db->beginTransaction();
            
            // Check if member has made contributions
            $contribCheck = $db->prepare("
                SELECT COUNT(*) as contrib_count 
                FROM sol_contributions 
                WHERE participant_id = ?
            ");
            $contribCheck->execute([$memberId]);
            $contribCount = $contribCheck->fetchColumn();
            
            if ($contribCount > 0 && $group['status'] === 'active') {
                setFlashMessage('error', 'Cannot remove members who have made contributions in active groups.');
            } else {
                // Remove member
                $removeStmt = $db->prepare("
                    DELETE FROM sol_participants 
                    WHERE id = ? AND sol_group_id = ?
                ");
                $removeStmt->execute([$memberId, $groupId]);
                
                // Reorder payout positions
                $reorderStmt = $db->prepare("
                    UPDATE sol_participants 
                    SET payout_position = payout_position - 1 
                    WHERE sol_group_id = ? AND payout_position > (
                        SELECT payout_position FROM (
                            SELECT payout_position FROM sol_participants 
                            WHERE id = ?
                        ) as temp
                    )
                ");
                $reorderStmt->execute([$groupId, $memberId]);
                
                $db->commit();
                setFlashMessage('success', 'Member removed successfully.');
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Member removal error: ' . $e->getMessage());
            setFlashMessage('error', 'Failed to remove member.');
        }
    }
    
    redirect('?page=sol-manage&id=' . $groupId);
}

// Handle swap payout position (UUID-safe 3 step swap)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swap_payout_position'])) {
    $memberId = isset($_POST['member_id']) ? trim($_POST['member_id']) : null;
    $targetMemberId = isset($_POST['target_member_id']) ? trim($_POST['target_member_id']) : null;

    if (!$memberId || !$targetMemberId || $memberId === $targetMemberId) {
        setFlashMessage('error', 'Invalid swap request.');
        redirect('?page=sol-manage&id=' . $groupId);
    }

    if (!in_array($userRole, ['admin', 'manager'])) {
        setFlashMessage('error', 'You do not have permission to swap payout positions.');
        redirect('?page=sol-manage&id=' . $groupId);
    }

    try {
        $db->beginTransaction();

    // Fetch and lock participants separately (UUIDs, no casting to int!)
    $stmt = $db->prepare("SELECT id, payout_position, payout_received FROM sol_participants WHERE id = ? AND sol_group_id = ? FOR UPDATE");
    $stmt->execute([$memberId, $groupId]);
    $p1 = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->execute([$targetMemberId, $groupId]);
    $p2 = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p1 || !$p2) {
            throw new Exception('Participants not found for swap.');
        }

        // Enforce unpaid constraint
        if ((int)$p1['payout_received'] === 1 || (int)$p2['payout_received'] === 1) {
            throw new Exception('Cannot swap positions after one has received payout.');
        }

        $pos1 = (int)$p1['payout_position'];
        $pos2 = (int)$p2['payout_position'];

        if ($pos1 === $pos2) {
            $db->rollBack();
            setFlashMessage('info', 'Participants already have identical position.');
            redirect('?page=sol-manage&id=' . $groupId);
        }

        // Choose temp position always as max+1 to avoid collisions (even if 0 exists)
        $maxStmt = $db->prepare("SELECT COALESCE(MAX(payout_position),0) FROM sol_participants WHERE sol_group_id = ?");
        $maxStmt->execute([$groupId]);
        $tempPos = (int)$maxStmt->fetchColumn() + 1;

        // Step 1: move first participant to temp
        $u1 = $db->prepare("UPDATE sol_participants SET payout_position = ?, updated_at = NOW() WHERE id = ? AND sol_group_id = ?");
        $u1->execute([$tempPos, $memberId, $groupId]);
        // Step 2: move second participant into first's original position
        $u2 = $db->prepare("UPDATE sol_participants SET payout_position = ?, updated_at = NOW() WHERE id = ? AND sol_group_id = ?");
        $u2->execute([$pos1, $targetMemberId, $groupId]);
        // Step 3: move first (temp) into second's original position
        $u3 = $db->prepare("UPDATE sol_participants SET payout_position = ?, updated_at = NOW() WHERE id = ? AND sol_group_id = ?");
        $u3->execute([$pos2, $memberId, $groupId]);

        $db->commit();
        setFlashMessage('success', 'Payout positions swapped successfully.');
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Swap payout position error: ' . $e->getMessage());
        setFlashMessage('error', 'Failed to swap payout positions: ' . htmlspecialchars($e->getMessage()));
    }
    redirect('?page=sol-manage&id=' . $groupId);
}

// Handle payout completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_payout'])) {
    $participantId = $_POST['participant_id'] ?? null;
    $payoutMethod = $_POST['payout_method'] ?? 'wallet';
    $payoutReference = trim($_POST['payout_reference'] ?? ''); // Not stored yet – placeholder
    if ($participantId) {
        try {
            $db->beginTransaction();

            // Mark payout as received (boolean flag)
            $upd = $db->prepare("UPDATE sol_participants SET payout_received = 1, updated_at = NOW() WHERE id = ? AND sol_group_id = ?");
            $upd->execute([$participantId, $groupId]);

            // Optionally advance group cycle if this participant matches current cycle
            $posStmt = $db->prepare("SELECT payout_position FROM sol_participants WHERE id = ?");
            $posStmt->execute([$participantId]);
            $pos = (int)$posStmt->fetchColumn();
            if ($pos === (int)$group['current_cycle']) {
                $adv = $db->prepare("UPDATE sol_groups SET current_cycle = current_cycle + 1, updated_at = NOW() WHERE id = ?");
                $adv->execute([$groupId]);
            }

            $db->commit();
            setFlashMessage('success', 'Payout marked as completed.');
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Complete payout error: ' . $e->getMessage());
            setFlashMessage('error', 'Failed to mark payout as completed.');
        }
    }
    redirect('?page=sol-manage&id=' . $groupId);
}

// Handle group status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_group_status'])) {
    $newStatus = $_POST['new_status'] ?? null;
    
    if ($newStatus && in_array($newStatus, ['pending', 'active', 'paused', 'completed'])) {
        try {
            $statusStmt = $db->prepare("
                UPDATE sol_groups 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $statusStmt->execute([$newStatus, $groupId]);
            
            setFlashMessage('success', 'Group status updated successfully.');
        } catch (PDOException $e) {
            error_log('Status update error: ' . $e->getMessage());
            setFlashMessage('error', 'Failed to update group status.');
        }
    }
    
    redirect('?page=sol-manage&id=' . $groupId);
}

// Handle invitation cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_invitation'])) {
    $invitationId = $_POST['cancel_invitation'] ?? null;
    
    if ($invitationId) {
        try {
            $cancelStmt = $db->prepare("
                UPDATE sol_invitations 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE id = ? AND sol_group_id = ?
            ");
            $cancelStmt->execute([$invitationId, $groupId]);
            
            setFlashMessage('success', 'Invitation cancelled successfully.');
        } catch (PDOException $e) {
            error_log('Invitation cancellation error: ' . $e->getMessage());
            setFlashMessage('error', 'Failed to cancel invitation.');
        }
    }
    
    redirect('?page=sol-manage&id=' . $groupId);
}

?>

<!-- Alert Placeholder -->
<div id="alertPlaceholder"></div>

<div class="row">
    <div class="col-12">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="?page=sol-groups">SOL Groups</a></li>
                <li class="breadcrumb-item"><a href="?page=sol-details&id=<?= $groupId ?>">Group Details</a></li>
                <li class="breadcrumb-item active" aria-current="page">Manage Group</li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Manage SOL Group</h1>
                <p class="text-muted mb-0"><?= htmlspecialchars($group['name']) ?></p>
            </div>
            <div>
                <a href="?page=sol-details&id=<?= $groupId ?>" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Group
                </a>
                <a href="?page=sol-edit&id=<?= $groupId ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i>Edit Group
                </a>
                <a href="?page=sol-finance&id=<?= $groupId ?>" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-coins me-2"></i>Finance
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Group Overview Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-2">
                    <i class="fas fa-users fa-2x text-primary"></i>
                </div>
                <h4 class="fw-bold"><?= count($members) ?>/<?= $group['member_limit'] ?></h4>
                <small class="text-muted">Members</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-2">
                    <i class="fas fa-sync fa-2x text-info"></i>
                </div>
                <h4 class="fw-bold"><?= $group['current_cycle'] ?>/<?= $group['total_cycles'] ?></h4>
                <small class="text-muted">Cycles</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-2">
                    <i class="fas fa-money-bill-wave fa-2x text-success"></i>
                </div>
                <h4 class="fw-bold"><?= number_format($group['contribution']) ?> HTG</h4>
                <small class="text-muted">Per Contribution</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="mb-2">
                    <span class="badge bg-<?= $group['status'] === 'active' ? 'success' : ($group['status'] === 'completed' ? 'secondary' : 'warning') ?> fs-6">
                        <?= ucfirst($group['status']) ?>
                    </span>
                </div>
                <h6 class="mb-0">Group Status</h6>
                <small class="text-muted"><?= ucfirst($group['frequency']) ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="manageTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button" role="tab">
            <i class="fas fa-users me-2"></i>Members (<?= count($members) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="contributions-tab" data-bs-toggle="tab" data-bs-target="#contributions" type="button" role="tab">
            <i class="fas fa-money-bill-wave me-2"></i>Contributions (<?= count($contributions) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="payouts-tab" data-bs-toggle="tab" data-bs-target="#payouts" type="button" role="tab">
            <i class="fas fa-money-bill-wave me-2"></i>Payouts (<?= count($payouts) ?>)
        </button>
    </li>    
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="invitations-tab" data-bs-toggle="tab" data-bs-target="#invitations" type="button" role="tab">
            <i class="fas fa-envelope me-2"></i>Invitations (<?= count($invitations) ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
            <i class="fas fa-cog me-2"></i>Settings
        </button>
    </li>
</ul>

<!-- Tab Contents -->
<div class="tab-content" id="manageTabsContent">
    <!-- Members Tab -->
    <div class="tab-pane fade show active" id="members" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Group Members</h5>
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inviteModal">
                    <i class="fas fa-user-plus me-2"></i>Invite Member
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Member</th>
                                <th>Position</th>
                                <th>Role</th>
                                <th>Contributions</th>
                                <th>Payout Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <?php if ($member['profile_photo']): ?>
                                                <img src="<?= htmlspecialchars($member['profile_photo']) ?>" 
                                                     class="rounded-circle" width="40" height="40" alt="Profile">
                                            <?php else: ?>
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <span class="text-white fw-bold">
                                                        <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?= htmlspecialchars($member['full_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($member['email']) ?></small>
                                            <?php if ($member['kyc_verified']): ?>
                                                <span class="badge bg-success ms-1">Verified</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info">#<?= $member['payout_position'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $member['role'] === 'admin' ? 'danger' : ($member['role'] === 'manager' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($member['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?= $member['total_contributions'] ?> contributions</div>
                                    <small class="text-muted"><?= number_format($member['total_contributed']) ?> HTG</small>
                                </td>
                                <td>
                                        <?php if ((int)$member['payout_received'] === 1): ?>
                                        <span class="badge bg-success">Received</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <!-- Swap Payout Position (modal)-->
                                            <li>
                                                <button class="dropdown-item" onclick="swapPayoutPosition('<?= $member['id'] ?>', '<?= htmlspecialchars($member['full_name']) ?>', '<?= (int)$member['payout_position'] ?>')">
                                                    <i class="fas fa-exchange-alt me-2"></i>Swap Payout Position
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" onclick="changeRole('<?= $member['id'] ?>', '<?= $member['full_name'] ?>', '<?= $member['role'] ?>')">
                                                    <i class="fas fa-user-tag me-2"></i>Change Role
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button class="dropdown-item text-danger" 
                                                        onclick="removeMember('<?= $member['id'] ?>', '<?= htmlspecialchars($member['full_name']) ?>')"
                                                        <?= $member['total_contributions'] > 0 && $group['status'] === 'active' ? 'disabled' : '' ?>>
                                                    <i class="fas fa-user-times me-2"></i>Remove Member
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contributions Tab -->
    <div class="tab-pane fade" id="contributions" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Recent Contributions</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Member</th>
                                <th>Amount</th>
                                <th>Cycle</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contributions as $contrib): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <?php if ($contrib['profile_photo']): ?>
                                                <img src="<?= htmlspecialchars($contrib['profile_photo']) ?>" 
                                                     class="rounded-circle" width="32" height="32" alt="Profile">
                                            <?php else: ?>
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 32px; height: 32px;">
                                                    <span class="text-white fw-bold" style="font-size: 0.8rem;">
                                                        <?= strtoupper(substr($contrib['full_name'], 0, 1)) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?= htmlspecialchars($contrib['full_name']) ?></div>
                                            <small class="text-muted">Position #<?= $contrib['payout_position'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-medium"><?= number_format($contrib['amount']) ?> HTG</td>
                                <td><span class="badge bg-info">Cycle <?= $contrib['cycle_number'] ?></span></td>
                                <td><?= date('M j, Y', strtotime($contrib['created_at'])) ?></td>
                                <td>
                                    <?php 
                                        $st = $contrib['status'];
                                        $cls = ($st === 'paid') ? 'success' : (($st === 'pending') ? 'warning' : (($st === 'missed') ? 'danger' : 'secondary'));
                                    ?>
                                    <span class="badge bg-<?= $cls ?>">
                                        <?= htmlspecialchars(ucfirst($st)) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($contributions)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No contributions yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payouts Tab -->
    <div class="tab-pane fade" id="payouts" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">Payouts</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Member</th>
                                <th>Amount</th>
                                <th>Cycle</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payouts as $payout): ?>
                            <?php
                                $memberCount = count($members);
                                $amount = (float)($group['contribution'] * max(1, $memberCount));
                                $cycle = (int)($payout['payout_position'] ?? 0);
                                $createdAt = $group['created_at'] ?? date('Y-m-d');
                                $freq = $group['frequency'] ?? 'monthly';
                                $dt = new DateTime($createdAt);
                                if ($cycle > 1) {
                                    switch ($freq) {
                                        case 'weekly': $dt->modify('+' . (($cycle - 1) * 7) . ' days'); break;
                                        case 'biweekly': $dt->modify('+' . (($cycle - 1) * 14) . ' days'); break;
                                        default: $dt->modify('+' . ($cycle - 1) . ' months'); break;
                                    }
                                }
                                $scheduledDate = $dt->format('Y-m-d');
                                $status = ((int)($payout['payout_received'] ?? 0) === 1) ? 'completed' : ($cycle == (int)$group['current_cycle'] ? 'pending' : ($cycle < (int)$group['current_cycle'] ? 'missed' : 'upcoming'));
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <?php if (!empty($payout['profile_photo'])): ?>
                                                <img src="<?= htmlspecialchars($payout['profile_photo']) ?>" 
                                                     class="rounded-circle" width="32" height="32" alt="Profile">
                                            <?php else: ?>
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 32px; height: 32px;">
                                                    <span class="text-white fw-bold" style="font-size: 0.8rem;">
                                                        <?php $pf = $payout['full_name'] ?? 'U'; echo strtoupper(substr((string)$pf, 0, 1)); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?= htmlspecialchars($payout['full_name'] ?? 'Unknown User') ?></div>
                                            <small class="text-muted">Position #<?= (int)($payout['payout_position'] ?? 0) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-medium"><?= number_format($amount) ?> HTG</td>
                                <td><span class="badge bg-info">Cycle <?= $cycle ?></span></td>
                                <td><?= date('M j, Y', strtotime($scheduledDate)) ?></td>
                                <td>
                                    <span class="badge bg-<?= $status === 'completed' ? 'success' : ($status === 'missed' ? 'secondary' : 'warning') ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($status === 'pending'): ?>
                                        <form method="POST" action="" class="d-flex flex-column flex-lg-row align-items-stretch gap-1">
                                            <input type="hidden" name="complete_payout" value="1">
                                            <input type="hidden" name="participant_id" value="<?= htmlspecialchars($payout['id']) ?>">
                                            <select name="payout_method" class="form-select form-select-sm" style="min-width:120px;">
                                                <option value="wallet">Wallet</option>
                                                <option value="cash">Cash</option>
                                                <option value="bank_transfer">Bank</option>
                                                <option value="mobile_money">Mobile Money</option>
                                            </select>
                                            <input type="text" name="payout_reference" class="form-control form-control-sm" placeholder="Ref (opt)" style="min-width:120px;" maxlength="60">
                                            <button class="btn btn-sm btn-outline-success" type="submit" title="Mark payout as completed">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($payouts)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No payouts yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Invitations Tab -->
    <div class="tab-pane fade" id="invitations" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Pending Invitations</h5>
                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inviteModal">
                    <i class="fas fa-paper-plane me-2"></i>Send Invitation
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Invited User</th>
                                <th>Invited By</th>
                                <th>Date Sent</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invitations as $invitation): ?>
                            <tr>
                                <td>
                                    <div>
                                        <div class="fw-medium"><?= htmlspecialchars($invitation['invited_user_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($invitation['invited_email']) ?></small>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($invitation['invited_by_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($invitation['created_at'])) ?></td>
                                <td>
                                    <span class="badge bg-warning">
                                        <?= ucfirst($invitation['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="cancelInvitation('<?= $invitation['id'] ?>')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($invitations)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No pending invitations</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Settings Tab -->
    <div class="tab-pane fade" id="settings" role="tabpanel">
        <div class="row">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Group Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="groupStatus" class="form-label">Group Status</label>
                                <select class="form-select" id="groupStatus" name="new_status">
                                    <option value="pending" <?= $group['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="active" <?= $group['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="paused" <?= $group['status'] === 'paused' ? 'selected' : '' ?>>Paused</option>
                                    <option value="completed" <?= $group['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                                <small class="form-text text-muted">Change the current status of the group</small>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" name="update_group_status" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Status
                                </button>
                                
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteGroupModal">
                                    <i class="fas fa-trash me-2"></i>Delete Group
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="card-title mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="?page=sol-edit&id=<?= $groupId ?>" class="btn btn-outline-primary">
                                <i class="fas fa-edit me-2"></i>Edit Group Info
                            </a>
                            <button class="btn btn-outline-info" onclick="exportGroupData()">
                                <i class="fas fa-download me-2"></i>Export Data
                            </button>
                            <button class="btn btn-outline-warning" onclick="sendReminders()">
                                <i class="fas fa-bell me-2"></i>Send Reminders
                            </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invite Member Modal -->
<div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inviteModalLabel">Invite Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="?page=sol-details&id=<?= $groupId ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="inviteEmail" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="inviteEmail" name="email" required>
                        <small class="form-text text-muted">Enter the email address of the person you want to invite</small>
                    </div>
                    <div class="mb-3">
                        <label for="inviteRole" class="form-label">Role</label>
                        <select class="form-select" id="inviteRole" name="role">
                            <option value="member">Member</option>
                            <option value="manager">Manager</option>
                            <?php if ($userRole === 'admin'): ?>
                                <option value="admin">Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="invite_member" class="btn btn-primary">Send Invitation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Role Modal -->
<div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeRoleModalLabel">Change Member Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="changeMemberId" name="member_id">
                    <div class="mb-3">
                        <label class="form-label">Member</label>
                        <div id="changeMemberName" class="fw-bold"></div>
                    </div>
                    <div class="mb-3">
                        <label for="changeRole" class="form-label">New Role</label>
                        <select class="form-select" id="changeRole" name="new_role" required>
                            <option value="member">Member</option>
                            <option value="manager">Manager</option>
                            <?php if ($userRole === 'admin'): ?>
                                <option value="admin">Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_member_role" class="btn btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Remove Member Modal -->
<div class="modal fade" id="removeMemberModal" tabindex="-1" aria-labelledby="removeMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="removeMemberModalLabel">Remove Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="removeMemberId" name="member_id">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Are you sure you want to remove <strong id="removeMemberName"></strong> from this group?
                    </div>
                    <p class="text-muted">This action cannot be undone. The member will lose their position in the payout order.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="remove_member" class="btn btn-danger">Remove Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Swap Payout Position Modal -->
<div class="modal fade" id="swapPayoutModal" tabindex="-1" aria-labelledby="swapPayoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="swapPayoutModalLabel">Swap Payout Position</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="swap_payout_position" value="1">
                    <input type="hidden" id="swapMemberId" name="member_id">
                    <div class="mb-3">
                        <label class="form-label">Selected Member</label>
                        <div id="swapMemberName" class="fw-bold"></div>
                        <small class="text-muted" id="swapMemberCurrentPos"></small>
                    </div>
                    <div class="mb-3">
                        <label for="targetMemberId" class="form-label">Swap With</label>
                        <select class="form-select" id="targetMemberId" name="target_member_id" required>
                            <option value="" disabled selected>Choose another member</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $m['payout_received'] ? 'disabled' : '' ?>>#<?= $m['payout_position'] ?> - <?= htmlspecialchars($m['full_name']) ?><?= $m['payout_received'] ? ' (Received)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Only members without a received payout can be swapped.</small>
                    </div>
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Swapping positions changes the payout order schedule. This cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Swap Positions</button>
                </div>
            </form>
        </div>
    </div>
    </div>

<!-- Delete Group Modal -->
<div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-labelledby="deleteGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteGroupModalLabel">Delete Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning!</strong> This action is irreversible.
                </div>
                <p>Are you sure you want to delete the group "<strong><?= htmlspecialchars($group['name']) ?></strong>"?</p>
                <p class="text-muted">All group data, member information, and contribution history will be permanently deleted.</p>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                    <label class="form-check-label" for="confirmDelete">
                        I understand that this action cannot be undone
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="?page=sol-delete&id=<?= $groupId ?>" class="btn btn-danger" id="deleteGroupBtn" disabled>
                    Delete Group
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle flash messages
    <?php if (isset($_SESSION['flash_type']) && isset($_SESSION['flash_message'])): ?>
        showAlert('<?= $_SESSION['flash_type'] ?>', '<?= $_SESSION['flash_message'] ?>');
        <?php 
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_message']);
        ?>
    <?php endif; ?>
    
    // Delete confirmation checkbox
    const confirmDeleteCheckbox = document.getElementById('confirmDelete');
    const deleteBtn = document.getElementById('deleteGroupBtn');
    
    if (confirmDeleteCheckbox && deleteBtn) {
        confirmDeleteCheckbox.addEventListener('change', function() {
            deleteBtn.disabled = !this.checked;
        });
    }
});

// Change member role
function changeRole(memberId, memberName, currentRole) {
    document.getElementById('changeMemberId').value = memberId;
    document.getElementById('changeMemberName').textContent = memberName;
    document.getElementById('changeRole').value = currentRole;
    
    const modal = new bootstrap.Modal(document.getElementById('changeRoleModal'));
    modal.show();
}

// Remove member
function removeMember(memberId, memberName) {
    document.getElementById('removeMemberId').value = memberId;
    document.getElementById('removeMemberName').textContent = memberName;
    
    const modal = new bootstrap.Modal(document.getElementById('removeMemberModal'));
    modal.show();
}

// Swap payout position
function swapPayoutPosition(memberId, memberName, currentPos) {
    const modalEl = document.getElementById('swapPayoutModal');
    if (!modalEl) return;
    document.getElementById('swapMemberId').value = memberId;
    document.getElementById('swapMemberName').textContent = memberName;
    const posEl = document.getElementById('swapMemberCurrentPos');
    if (posEl) posEl.textContent = 'Current Position #' + currentPos;
    // Highlight current member option removal from target select
    const select = document.getElementById('targetMemberId');
    if (select) {
        [...select.options].forEach(opt => {
            opt.disabled = (opt.value === memberId || opt.value === '');
        });
        select.selectedIndex = 0; // reset
        select.onchange = function() {
            const targetOpt = select.options[select.selectedIndex];
            const preview = document.getElementById('swapPreview');
            if (targetOpt && targetOpt.value) {
                const targetText = targetOpt.textContent.trim();
                preview.style.display = 'block';
                preview.textContent = `${memberName} will take ${targetText}'s position and that member will move to position #${currentPos}.`;
            } else {
                if (preview) preview.style.display = 'none';
            }
        };
    }
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

// Cancel invitation
function cancelInvitation(invitationId) {
    if (confirm('Are you sure you want to cancel this invitation?')) {
        // Create a form to submit the cancellation
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'cancel_invitation';
        input.value = invitationId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// Export group data
function exportGroupData() {
    // Implement export functionality
    alert('Export functionality would be implemented here');
}

// Send reminders
function sendReminders() {
    if (confirm('Send payment reminders to all members who haven\'t contributed this cycle?')) {
        // Implement reminder functionality
        alert('Reminder functionality would be implemented here');
    }
}

// Show alert function
function showAlert(type, message) {
    const alertPlaceholder = document.getElementById('alertPlaceholder');
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
    alertPlaceholder.append(wrapper);
    setTimeout(() => {
        wrapper.remove();
    }, 5000);
}
</script>