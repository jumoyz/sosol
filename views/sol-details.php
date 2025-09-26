<?php
// -----------------------------------------------------------------------------
// SOL Group Details (Clean Rebuild)
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../includes/flash-messages.php';
require_once __DIR__ . '/../services/SolFinanceManager.php';

$pageTitle = 'SOL Group Details';
$pageDescription = 'View and manage details of your SOSOL group.';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { redirect('?page=login'); }
$groupId = requireValidGroupId('?page=sol-groups');

// Allow clearing modal session data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['clear_session'])) {
    unset($_SESSION['new_user_info']);
    exit('OK');
}

// Data holders
$group = null; $members = []; $schedule = []; $userMember = null; $userRole = null; $nextPayment = null;
$recentContributions = []; $pendingContributions = []; $recentMessages = []; $unreadCount = 0; $error = null;

try {
    $db = getDbConnection();

    // Group
    $stmt = $db->prepare('SELECT sg.*, COUNT(sp.user_id) as member_count FROM sol_groups sg LEFT JOIN sol_participants sp ON sg.id = sp.sol_group_id WHERE sg.id = ? GROUP BY sg.id');
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) { setFlashMessage('error','SOL group not found.'); redirect('?page=sol-groups'); }

    // Membership
    $stmt = $db->prepare('SELECT sp.*, u.full_name, u.profile_photo,(SELECT COUNT(*) FROM sol_contributions sc WHERE sc.participant_id = sp.id) as contributions_made FROM sol_participants sp INNER JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? AND sp.user_id = ?');
    $stmt->execute([$groupId,$userId]);
    $userMember = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userMember) { $userRole = $userMember['role']; } elseif ($group['visibility'] === 'private') { setFlashMessage('error','Private group – invitation required.'); redirect('?page=sol-groups'); }

    // Members list
    $stmt = $db->prepare('SELECT sp.*, u.full_name, u.profile_photo, u.kyc_verified,(SELECT COUNT(*) FROM sol_contributions sc WHERE sc.participant_id = sp.id) as contributions_made FROM sol_participants sp INNER JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? ORDER BY sp.payout_position ASC');
    $stmt->execute([$groupId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Schedule anchored on start_date
    $anchorDate = $group['start_date'] ?: ($group['created_at'] ?? date('Y-m-d'));
    $cycleDates = computeSolCycleDates($anchorDate, $group['frequency'], (int)$group['total_cycles']);
    $stmt = $db->prepare('SELECT sp.payout_position, sp.payout_received, u.full_name, u.id as user_id FROM sol_participants sp INNER JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? ORDER BY sp.payout_position ASC');
    $stmt->execute([$groupId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pos = (int)$row['payout_position'];
        $row['scheduled_date'] = $cycleDates[$pos] ?? $anchorDate;
        $schedule[] = $row;
    }

    // Next contribution date
    if ($userMember) {
        $check = $db->prepare("SELECT COUNT(*) FROM sol_contributions WHERE participant_id=? AND cycle_number=? AND status IN ('paid','pending')");
        $check->execute([$userMember['id'], $group['current_cycle']]);
        $has = $check->fetchColumn() > 0;
        $nd = computeNextContributionDate($anchorDate, $group['frequency'], (int)$group['current_cycle'], (int)$group['total_cycles'], $has);
        $nextPayment = $nd ? $nd->format('Y-m-d') : null;
    }

    // Contributions
    $stmt = $db->prepare('SELECT sc.id, sc.amount, sc.cycle_number, sc.created_at, sc.status, u.full_name, u.profile_photo FROM sol_contributions sc INNER JOIN sol_participants sp ON sc.participant_id = sp.id INNER JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? ORDER BY sc.created_at DESC LIMIT 10');
    $stmt->execute([$groupId]);
    $recentContributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT sc.id, sc.amount, sc.cycle_number, sc.created_at, u.full_name, u.profile_photo FROM sol_contributions sc INNER JOIN sol_participants sp ON sc.participant_id = sp.id INNER JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? AND sc.status='pending' ORDER BY sc.created_at ASC");
    $stmt->execute([$groupId]);
    $pendingContributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Messages
    $stmt = $db->prepare('SELECT gm.*, u.full_name, u.profile_photo FROM group_messages gm INNER JOIN users u ON gm.user_id = u.id WHERE gm.sol_group_id = ? ORDER BY gm.created_at DESC LIMIT 5');
    $stmt->execute([$groupId]);
    $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $db->prepare('SELECT COUNT(*) FROM group_messages WHERE sol_group_id=? AND user_id != ? AND is_read=FALSE');
    $stmt->execute([$groupId,$userId]);
    $unreadCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('sol-details load error: '.$e->getMessage());
    $error = 'Unable to load SOL group details.';
}

// --------------------------- Actions ----------------------------------------
// Contribution submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_contribution'])) {
    requireValidCsrfOrFail();
    if (!$userMember) { setFlashMessage('error','You must be a member to contribute.'); }
    else {
        $method = $_POST['payment_method'] ?? 'wallet';
        $method = in_array($method,['wallet','cash']) ? $method : 'cash';
        try {
            $db = getDbConnection();
            $db->beginTransaction();
            $status = $method === 'wallet' ? 'paid' : 'pending';
            if ($method === 'wallet') {
                $w = $db->prepare('SELECT balance_htg FROM wallets WHERE user_id=? FOR UPDATE');
                $w->execute([$userId]);
                $wallet = $w->fetch(PDO::FETCH_ASSOC);
                if (!$wallet || $wallet['balance_htg'] < $group['contribution']) {
                    $db->rollBack();
                    setFlashMessage('error','Insufficient wallet balance.');
                    redirect('?page=sol-details&id='.$groupId);
                }
            }
            $cid = generateUuid();
            $ins = $db->prepare("INSERT INTO sol_contributions (id, sol_group_id, participant_id, user_id, amount, currency, status, cycle_number, created_at, updated_at) VALUES (?,?,?,?,?,'HTG',?,?,NOW(),NOW())");
            $ins->execute([$cid,$groupId,$userMember['id'],$userId,$group['contribution'],$status,$group['current_cycle']]);
            $db->commit();
            setFlashMessage('success',$method==='wallet'?'Contribution paid from wallet.':'Contribution recorded; awaiting approval.');
        } catch(Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('contribution error: '.$e->getMessage());
            setFlashMessage('error','Could not process contribution.');
        }
    }
    redirect('?page=sol-details&id='.$groupId);
}

// Add Member (direct add by admin/manager)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    requireValidCsrfOrFail();
    if (!$userRole || !in_array($userRole,['admin','manager'])) {
        setFlashMessage('error','Unauthorized.');
        redirect('?page=sol-details&id='.$groupId);
    }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'member';
    $role = in_array($role,['member','manager','admin']) ? $role : 'member';

    if ($role === 'admin' && $userRole !== 'admin') { $role = 'manager'; }

    if ($name === '' || $email === '' || $phone === '') {
        setFlashMessage('error','All fields are required.');
        redirect('?page=sol-details&id='.$groupId);
    }

    try {
        $db = getDbConnection();
        $db->beginTransaction();
        // Find existing user
        $usr = $db->prepare('SELECT id FROM users WHERE email = ?');
        $usr->execute([$email]);
        $userRow = $usr->fetch(PDO::FETCH_ASSOC);
        $newPasswordPlain = null;
        if (!$userRow) {
            $newUserId = generateUuid();
            $newPasswordPlain = generateRandomString(10);
            $phash = password_hash($newPasswordPlain, PASSWORD_BCRYPT);
            $insUser = $db->prepare('INSERT INTO users (id, full_name, email, phone_number, password_hash, created_at, updated_at) VALUES (?,?,?,?,?,NOW(),NOW())');
            $insUser->execute([$newUserId,$name,$email,$phone,$phash]);
            // Create wallet
            $wId = generateUuid();
            $insW = $db->prepare('INSERT INTO wallets (id, user_id, balance_htg, balance_usd, created_at, updated_at) VALUES (?,?,0,0,NOW(),NOW())');
            $insW->execute([$wId,$newUserId]);
            $userIdToAdd = $newUserId;
            $_SESSION['new_user_info'] = [
                'name'=>$name,
                'email'=>$email,
                'phone'=>$phone,
                'password'=>$newPasswordPlain,
                'role'=>$role
            ];
        } else {
            $userIdToAdd = $userRow['id'];
        }
        // Check already participant
        $chk = $db->prepare('SELECT id FROM sol_participants WHERE sol_group_id=? AND user_id=?');
        $chk->execute([$groupId,$userIdToAdd]);
        if ($chk->fetch()) {
            $db->rollBack();
            setFlashMessage('error','User already in group.');
            redirect('?page=sol-details&id='.$groupId);
        }
        // Determine payout_position = current count + 1
        $cnt = $db->prepare('SELECT COUNT(*) FROM sol_participants WHERE sol_group_id=?');
        $cnt->execute([$groupId]);
        $position = (int)$cnt->fetchColumn() + 1;
        $participantId = generateUuid();
        $insPart = $db->prepare('INSERT INTO sol_participants (id, sol_group_id, user_id, role, payout_position, join_date, created_at, updated_at) VALUES (?,?,?,?,?,NOW(),NOW(),NOW())');
        $insPart->execute([$participantId,$groupId,$userIdToAdd,$role,$position]);
        $db->commit();
        setFlashMessage('success','Member added successfully.'.($newPasswordPlain? ' New user credentials displayed.' : ''));
    } catch(Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('add_member error: '.$e->getMessage());
        setFlashMessage('error','Could not add member.');
    }
    redirect('?page=sol-details&id='.$groupId);
}

// Invite Member (creates invitation record)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_member'])) {
    requireValidCsrfOrFail();
    if (!$userRole || !in_array($userRole,['admin','manager'])) {
        setFlashMessage('error','Unauthorized.');
        redirect('?page=sol-details&id='.$groupId);
    }
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'member';
    $role = in_array($role,['member','manager','admin']) ? $role : 'member';
    if ($role === 'admin' && $userRole !== 'admin') { $role = 'manager'; }

    if ($email === '') { setFlashMessage('error','Email required.'); redirect('?page=sol-details&id='.$groupId); }
    try {
        $db = getDbConnection();
        $db->beginTransaction();
        // locate user
        $usr = $db->prepare('SELECT id FROM users WHERE email=?');
        $usr->execute([$email]);
        $userRow = $usr->fetch(PDO::FETCH_ASSOC);
        if (!$userRow) {
            // create placeholder user with random password
            $tempId = generateUuid();
            $pwd = generateRandomString(10);
            $phash = password_hash($pwd,PASSWORD_BCRYPT);
            $insUser = $db->prepare('INSERT INTO users (id, full_name, email, phone_number, password_hash, created_at, updated_at) VALUES (?,?,?,?,?,NOW(),NOW())');
            $insUser->execute([$tempId,$email,$email,'+0000000000',$phash]);
            $wId = generateUuid();
            $db->prepare('INSERT INTO wallets (id,user_id,balance_htg,balance_usd,created_at,updated_at) VALUES (?,?,0,0,NOW(),NOW())')->execute([$wId,$tempId]);
            $userRow = ['id'=>$tempId];
            $_SESSION['new_user_info'] = [
                'name'=>$email,
                'email'=>$email,
                'phone'=>'+0000000000',
                'password'=>$pwd,
                'role'=>$role
            ];
        }
        // Ensure not already participant
        $chk = $db->prepare('SELECT id FROM sol_participants WHERE sol_group_id=? AND user_id=?');
        $chk->execute([$groupId,$userRow['id']]);
        if ($chk->fetch()) { $db->rollBack(); setFlashMessage('error','User already in group.'); redirect('?page=sol-details&id='.$groupId); }
        // Insert invitation (re-using schema if present, fallback to flash only if table missing)
        $invId = generateUuid();
        $token = generateRandomString(32);
        $hasTable = true;
        try { $db->query('SELECT 1 FROM sol_invitations LIMIT 1'); } catch(Exception $ti) { $hasTable = false; }
        if ($hasTable) {
            $ins = $db->prepare('INSERT INTO sol_invitations (id, sol_group_id, invited_by, invited_user_id, status, message, created_at, updated_at) VALUES (?,?,?,?,"pending",NULL,NOW(),NOW())');
            $ins->execute([$invId,$groupId,$userId,$userRow['id']]);
        }
        $db->commit();
        setFlashMessage('success','Invitation created.'.(!$hasTable?' (invitation table missing, user placeholder created)':''));
    } catch(Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('invite_member error: '.$e->getMessage());
        setFlashMessage('error','Could not create invitation.');
    }
    redirect('?page=sol-details&id='.$groupId);
}

?>

<!-- Alert Placeholder -->
<div id="alertPlaceholder"></div>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div class="group-icon bg-primary-subtle rounded-circle p-3 d-flex align-items-center justify-content-center">
                                <i class="fas fa-users text-primary" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div>
                            <h2 class="fw-bold mb-0"><?= htmlspecialchars($group['name']) ?></h2>
                            <p class="text-muted mb-0">
                                <span class="badge bg-<?= $group['status'] === 'active' ? 'success' : ($group['status'] === 'completed' ? 'secondary' : 'warning') ?> me-2">
                                    <?= ucfirst($group['status']) ?>
                                </span>
                                Cycle <?= $group['current_cycle'] ?> of <?= $group['total_cycles'] ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($userRole): ?>
                        <div class="mt-3 mt-md-0">
                            <?php if ($group['status'] === 'active' && $nextPayment): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contributeModal">
                                    <i class="fas fa-money-bill-wave me-2"></i> Make Contribution
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                                <button class="btn btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                                    <i class="fas fa-user-plus me-2"></i> Add Member
                                </button>
                            <?php endif; ?>

                            <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                                <button class="btn btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#inviteModal">
                                    <i class="fas fa-user-plus me-2"></i> Invite Member
                                </button>
                                <a href="?page=sol-finance&id=<?= $groupId ?>" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-coins me-2"></i> Finance
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($group['status'] === 'active' && $group['member_count'] < $group['member_limit']): ?>
                        <div>
                            <a href="?page=sol-join&id=<?= $groupId ?>" class="btn btn-primary">
                                <i class="fas fa-handshake me-2"></i> Join Group
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Group Details -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-0">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                            <i class="fas fa-info-circle me-2"></i> Details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button" role="tab">
                            <i class="fas fa-users me-2"></i> Members
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">
                            <i class="fas fa-calendar-alt me-2"></i> Schedule
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="contributions-tab" data-bs-toggle="tab" data-bs-target="#contributions" type="button" role="tab">
                            <i class="fas fa-history me-2"></i> Activity
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-4">
                <div class="tab-content">
                    <!-- Details Tab -->
                    <div class="tab-pane fade show active" id="details" role="tabpanel">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="fw-bold mb-3">Group Information</h5>
                                <div class="mb-3">
                                    <div class="text-muted small">Description</div>
                                    <p><?= nl2br(htmlspecialchars($group['description'] ?? 'No description provided.')) ?></p>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Created</span>
                                    <span><?php 
                                        $createdRaw = $group['created_at'] ?? null; 
                                        echo $createdRaw ? date('F j, Y', strtotime($createdRaw)) : '<span class="text-muted">—</span>'; 
                                    ?></span>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Start Date</span>
                                    <span><?php 
                                        $startRaw = $group['start_date'] ?? null; 
                                        echo $startRaw ? date('F j, Y', strtotime($startRaw)) : '<span class="text-muted">—</span>'; 
                                    ?></span>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">End Date</span>
                                    <span><?php 
                                        $endRaw = $group['end_date'] ?? null; 
                                        echo $endRaw ? date('F j, Y', strtotime($endRaw)) : '<span class="text-muted">—</span>'; 
                                    ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Visibility</span>
                                    <span><?= ucfirst($group['visibility']) ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Members</span>
                                    <span><?= $group['member_count'] ?> / <?= $group['member_limit'] ?></span>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="fw-bold mb-3">Financial Details</h5>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Contribution</span>
                                    <span class="fw-medium"><?= number_format($group['contribution']) ?> HTG</span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Frequency</span>
                                    <span><?= ucfirst($group['frequency']) ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Payout Amount</span>
                                    <span class="fw-medium"><?= number_format($group['contribution'] * $group['member_count']) ?> HTG</span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Cycles</span>
                                    <span><?= $group['total_cycles'] ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($userMember): ?>
                            <div class="card bg-light border-0 mt-3">
                                <div class="card-body">
                                    <h5 class="card-title">Your Participation</h5>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted">Role</span>
                                                <span class="badge bg-<?= 
                                                    $userMember['role'] === 'admin' ? 'danger' : 
                                                    ($userMember['role'] === 'manager' ? 'warning' : 'info') 
                                                ?>">
                                                    <?= ucfirst($userMember['role']) ?>
                                                </span>
                                            </div>
                                            
                                            <?php
                                            $joinDateTs = !empty($userMember['join_date']) ? strtotime($userMember['join_date']) : null;
                                            ?>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted">Joined</span>
                                                <span><?= $joinDateTs ? date('F j, Y', $joinDateTs) : '<span class="text-muted">—</span>' ?></span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted">Payout Position</span>
                                                <span>#<?= $userMember['payout_position'] ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <?php $nextPaymentTs = !empty($nextPayment) ? strtotime($nextPayment) : null; ?>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted">Next Contribution</span>
                                                <span class="<?= ($nextPaymentTs !== null && $nextPaymentTs <= time()) ? 'text-danger fw-bold' : '' ?>">
                                                    <?= $nextPaymentTs !== null ? date('F j, Y', $nextPaymentTs) : '<span class="text-muted">—</span>' ?>
                                                </span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted">Contributions Made</span>
                                                <span><?= $userMember['contributions_made'] ?></span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted">Payout Received</span>
                                                <span>
                                                    <?php
                                                    $payoutReceivedTs = !empty($userMember['payout_received']) ? strtotime($userMember['payout_received']) : null;
                                                    if ($payoutReceivedTs):
                                                        echo date('F j, Y', $payoutReceivedTs);
                                                    else:
                                                        echo $userMember['payout_position'] < $group['current_cycle'] ? 'Missed' : 
                                                            ($userMember['payout_position'] == $group['current_cycle'] ? 'Current Cycle' : 'Upcoming');
                                                    endif;
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($group['status'] === 'active' && $nextPaymentTs !== null && $nextPaymentTs <= time()): ?>
                                        <div class="alert alert-warning mt-3 mb-0">
                                            <i class="fas fa-exclamation-circle me-2"></i> Your contribution is due! Please make your payment to stay in good standing.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Members Tab -->
                    <div class="tab-pane fade" id="members" role="tabpanel">
                        <h5 class="fw-bold mb-3">Group Members</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Member</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td><?= $member['payout_position'] ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="member-avatar me-2">
                                                        <?php if (!empty($member['profile_photo'])): ?>
                                                            <img src="<?= htmlspecialchars($member['profile_photo']) ?>" alt="" class="avatar-sm">
                                                        <?php else: ?>
                                                            <div class="avatar-placeholder avatar-sm bg-light d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-user text-primary"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?= htmlspecialchars($member['full_name']) ?>
                                                        <?php if ($member['kyc_verified']): ?>
                                                            <i class="fas fa-check-circle text-success ms-1" data-bs-toggle="tooltip" title="Verified"></i>
                                                        <?php endif; ?>
                                                        <?php if ($member['user_id'] === $userId): ?>
                                                            <span class="badge bg-primary ms-1">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $member['role'] === 'admin' ? 'danger' : 
                                                    ($member['role'] === 'manager' ? 'warning' : 'info') 
                                                ?>">
                                                    <?= ucfirst($member['role']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($member['join_date'])) ?></td>
                                            <td>
                                                <?php if ($member['payout_received']): ?>
                                                    <span class="badge bg-success">Received Payout</span>
                                                <?php elseif ($member['payout_position'] == $group['current_cycle']): ?>
                                                    <span class="badge bg-primary">Current Recipient</span>
                                                <?php elseif ($member['payout_position'] < $group['current_cycle']): ?>
                                                    <span class="badge bg-secondary">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark">Waiting</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (($userRole === 'admin' || $userRole === 'manager') && $group['member_count'] < $group['member_limit'] && $group['status'] === 'active'): ?>
                            <div class="mt-3 text-center">
                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#inviteModal">
                                    <i class="fas fa-user-plus me-2"></i> Invite Member
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Schedule Tab -->
                    <div class="tab-pane fade" id="schedule" role="tabpanel">
                        <h5 class="fw-bold mb-3">Payout Schedule</h5>
                        
                        <div class="timeline mb-4">
                            <?php foreach ($schedule as $item): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker <?= 
                                        $item['payout_position'] < $group['current_cycle'] ? 'completed' : 
                                        ($item['payout_position'] == $group['current_cycle'] ? 'current' : '')
                                    ?>"></div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="mb-0">Cycle <?= $item['payout_position'] ?></h6>
                                            <span class="text-muted small"><?= date('F j, Y', strtotime($item['scheduled_date'])) ?></span>
                                        </div>
                                        <p class="mb-0">
                                            Payout to: <strong><?= htmlspecialchars($item['full_name']) ?></strong>
                                            
                                            <?php if ($item['payout_received']): ?>
                                                <span class="badge bg-success ms-2">Paid</span>
                                            <?php elseif ($item['payout_position'] == $group['current_cycle']): ?>
                                                <span class="badge bg-primary ms-2">Current</span>
                                            <?php elseif (strtotime($item['scheduled_date']) < time() && !$item['payout_received']): ?>
                                                <span class="badge bg-danger ms-2">Missed</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($group['status'] === 'completed'): ?>
                            <div class="alert alert-success text-center">
                                <i class="fas fa-check-circle me-2"></i> This SOL group has completed all cycles!
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Contributions Tab -->
                    <div class="tab-pane fade" id="contributions" role="tabpanel">
                        <h5 class="fw-bold mb-3">Recent Activity</h5>
                        
                        <?php if (!empty($recentContributions)): ?>
                            <div class="activity-feed">
                                <?php foreach ($recentContributions as $contrib): ?>
                                    <div class="activity-item">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <?php if (!empty($contrib['profile_photo'])): ?>
                                                    <img src="<?= htmlspecialchars($contrib['profile_photo']) ?>" alt="" class="avatar-sm">
                                                <?php else: ?>
                                                    <div class="avatar-sm bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-user text-primary"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="mb-1">
                                                    <span class="fw-medium"><?= htmlspecialchars($contrib['full_name']) ?></span>
                                                    <span> contributed </span>
                                                    <span class="fw-medium"><?= number_format($contrib['amount']) ?> HTG</span>
                                                    <span class="text-muted ms-2 small">(Cycle <?= $contrib['cycle_number'] ?>)</span>
                                                    <?php if ($contrib['status'] ?? null): ?>
                                                        <?php
                                                            $st = $contrib['status'];
                                                            $badgeClass = $st === 'paid' ? 'success' : ($st === 'pending' ? 'warning' : ($st === 'missed' ? 'danger' : 'secondary'));
                                                        ?>
                                                        <span class="badge bg-<?= $badgeClass ?> ms-2 text-uppercase" style="font-size:10px;"><?= htmlspecialchars($st) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="far fa-clock me-1"></i> <?= date('F j, Y \a\t g:i a', strtotime($contrib['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="mb-3">
                                    <i class="fas fa-file-alt text-muted" style="font-size: 2.5rem;"></i>
                                </div>
                                <p class="text-muted mb-0">No contribution activity yet</p>
                            </div>
                        <?php endif; ?>

                        <?php if (($userRole === 'admin' || $userRole === 'manager') && !empty($pendingContributions)): ?>
                            <hr>
                            <h6 class="fw-bold mb-3">Pending Approvals</h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Member</th>
                                            <th>Amount</th>
                                            <th>Cycle</th>
                                            <th>Requested</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($pendingContributions as $pc): ?>
                                            <tr>
                                                <td class="d-flex align-items-center">
                                                    <?php if (!empty($pc['profile_photo'])): ?>
                                                        <img src="<?= htmlspecialchars($pc['profile_photo']) ?>" alt="" class="rounded-circle me-2" width="30" height="30">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-light me-2 d-flex align-items-center justify-content-center" style="width:30px;height:30px;">
                                                            <i class="fas fa-user text-primary"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span><?= htmlspecialchars($pc['full_name']) ?></span>
                                                </td>
                                                <td><?= number_format($pc['amount']) ?> HTG</td>
                                                <td><?= (int)$pc['cycle_number'] ?></td>
                                                <td><span class="text-muted small"><?= timeAgo($pc['created_at']) ?></span></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="contribution_id" value="<?= htmlspecialchars($pc['id']) ?>">
                                                        <button name="approve_contribution" class="btn btn-sm btn-success" onclick="return confirm('Approve this contribution?')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Group Summary and Actions -->
    <div class="col-lg-4">
        <!-- Group Progress Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Group Progress</h5>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted">Cycle Progress</span>
                        <span><?= $group['current_cycle'] ?> of <?= $group['total_cycles'] ?> cycles</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-primary" role="progressbar" 
                             style="width: <?= ($group['current_cycle'] / $group['total_cycles']) * 100 ?>%"></div>
                    </div>
                </div>
                
                <!-- Current Cycle Info -->
                <?php if ($group['status'] === 'active'): ?>
                    <div class="card bg-light border-0 mt-4 mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Current Cycle Information</h6>
                            
                            <?php 
                            // Find current recipient
                            $currentRecipient = null;
                            foreach ($members as $member) {
                                if ($member['payout_position'] == $group['current_cycle']) {
                                    $currentRecipient = $member;
                                    break;
                                }
                            }
                            ?>
                            
                            <?php if ($currentRecipient): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Current Recipient</span>
                                    <span class="fw-medium">
                                        <?= htmlspecialchars($currentRecipient['full_name']) ?>
                                        <?php if ($currentRecipient['user_id'] === $userId): ?>
                                            <span class="badge bg-primary ms-1">You</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Cycle Amount</span>
                                    <span class="fw-medium"><?= number_format($group['contribution'] * $group['member_count']) ?> HTG</span>
                                </div>
                                
                                <?php
                                // Count contributions for current cycle
                                $currentCycleContribCount = 0;
                                foreach ($recentContributions as $contrib) {
                                    if ($contrib['cycle_number'] == $group['current_cycle']) {
                                        $currentCycleContribCount++;
                                    }
                                }
                                ?>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Contributions</span>
                                    <span><?= $currentCycleContribCount ?> of <?= count($members) ?> members</span>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-2">
                                    <p class="text-muted mb-0">No data available for the current cycle.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Group Rules -->
                <div class="mt-4">
                    <h6 class="fw-bold mb-3">Group Rules</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i> Contribute on time to maintain good standing
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i> Stay in the group until all cycles are complete
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i> Respect payout order and schedule
                        </li>
                        <li>
                            <i class="fas fa-check-circle text-success me-2"></i> Communicate with group if issues arise
                        </li>
                    </ul>
                </div>
                
                <!-- Group Actions -->
                <?php if ($userRole && $group['status'] === 'active'): ?>
                    <div class="d-grid gap-2 mt-4">
                        <?php $nextPaymentTs_for_action = isset($nextPaymentTs) ? $nextPaymentTs : (!empty($nextPayment) ? strtotime($nextPayment) : null); ?>
                        <?php if ($nextPaymentTs_for_action !== null && $nextPaymentTs_for_action <= time()): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contributeModal">
                                <i class="fas fa-money-bill-wave me-2"></i> Make Contribution
                            </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#messageModal">
                            <i class="fas fa-comment-alt me-2"></i> Message Group
                        </button>
                        
                        <?php if ($userRole === 'admin'): ?>
                            <a href="?page=sol-manage&id=<?= $groupId ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-cog me-2"></i> Manage Group
                            </a>
                        <?php endif; ?>
                    </div>
                <?php elseif (!$userRole && $group['status'] === 'active' && $group['member_count'] < $group['member_limit']): ?>
                    <div class="d-grid mt-4">
                        <a href="?page=sol-join&id=<?= urlencode($group['id']) ?>" class="btn btn-primary">
                            <i class="fas fa-handshake me-2"></i> Join Group
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Group Chat Preview -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-0">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-comments text-primary me-2"></i> Group Chat
                    </h5>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($recentMessages)): ?>
                    <div class="chat-preview-container">
                        <?php foreach (array_reverse($recentMessages) as $message): ?>
                            <div class="message-item mb-3 <?= $message['user_id'] === $userId ? 'own-message' : '' ?>">
                                <div class="d-flex align-items-start">
                                    <div class="message-avatar me-2">
                                        <?php if (!empty($message['profile_photo'])): ?>
                                            <img src="<?= htmlspecialchars($message['profile_photo']) ?>" 
                                                 alt="<?= htmlspecialchars($message['full_name']) ?>" 
                                                 class="rounded-circle" 
                                                 width="32" height="32">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                                                 style="width: 32px; height: 32px; font-size: 14px;">
                                                <?= strtoupper(substr($message['full_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="message-content flex-grow-1">
                                        <div class="message-header d-flex justify-content-between align-items-center">
                                            <small class="text-muted fw-medium">
                                                <?= htmlspecialchars($message['full_name']) ?>
                                            </small>
                                            <small class="text-muted">
                                                <?= timeAgo($message['created_at']) ?>
                                            </small>
                                        </div>
                                        <div class="message-text <?= $message['message_type'] === 'system' ? 'text-info fst-italic' : '' ?>">
                                            <?= htmlspecialchars($message['message']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Quick message form for members -->
                    <?php if ($userMember): ?>
                        <hr class="my-3">
                        <form method="POST" action="" class="quick-message-form">
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       name="quick_message" 
                                       placeholder="Type a quick message..." 
                                       maxlength="500"
                                       required>
                                <button type="submit" 
                                        name="send_quick_message" 
                                        class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                            <input type="hidden" name="group_id" value="<?= $groupId ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="?page=group-chat&id=<?= $groupId ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-comments me-1"></i> View Full Chat
                        </a>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-comments fa-2x mb-3 text-light"></i>
                        <p class="mb-0">No messages yet</p>
                        <small class="text-muted">Start a conversation with your group!</small>
                        
                        <?php if ($userMember): ?>
                            <form method="POST" action="" class="mt-3">
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control" 
                                           name="quick_message" 
                                           placeholder="Send the first message..." 
                                           maxlength="500"
                                           required>
                                    <button type="submit" 
                                            name="send_quick_message" 
                                            class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="group_id" value="<?= $groupId ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Group Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Group Info</h5>
                    <?php if ($userRole === 'admin'): ?>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editGroupModal">
                        <i class="fas fa-edit me-2"></i> Edit Group
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="text-muted small">Group Name</div>
                            <p><?= htmlspecialchars($group['name']) ?></p>  
                        </div>
                        
                        <div class="mb-3">
                            <div class="text-muted small">Description</div>
                            <p><?= nl2br(htmlspecialchars($group['description'] ?? 'No description provided.')) ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <div class="text-muted small">Visibility</div>
                            <p><?= ucfirst($group['visibility']) ?></p>
                        </div>
                    </div>
                
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="text-muted small">Contribution</div>
                            <p><?= number_format($group['contribution']) ?> HTG</p>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Frequency</div>
                            <p><?= ucfirst($group['frequency']) ?></p>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Total Cycles</div>
                            <p><?php echo $group['total_cycles']; ?></p>
                        </div>
                    </div>
                </div>
                <!--
                <div class="row">
                    <div class="col-md-12">
                        <h5 class="fw-bold mb-3">Group Members</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Member</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td><?= $member['payout_position'] ?></td>
                                            <td><?= htmlspecialchars($member['full_name']) ?></td>
                                            <td><?= ucfirst($member['role']) ?></td>
                                            <td><?= $member['status'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div> -->
                
                <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                    <div class="d-grid mt-4">
                        <a href="?page=sol-edit&id=<?= $groupId ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i> Edit Group
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                    <div class="d-grid mt-4">
                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteGroupModal">
                            <i class="fas fa-trash-alt me-2"></i> Delete Group
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>        
    </div> 
    
    
    <div class="col-lg-4">

    </div>
    
</div>


<!-- Contribution Modal -->
<div class="modal fade" id="contributeModal" tabindex="-1" aria-labelledby="contributeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contributeModalLabel">Make Contribution</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form id="contributionForm" method="POST" action="">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <div class="text-muted small">Contribution Amount</div>
                                <p class="fw-bold"><?= number_format($group['contribution']) ?> HTG</p>
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small">Frequency</div>
                                <p><?= ucfirst($group['frequency']) ?></p>
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small">Total Cycles</div>
                                <p><?= $group['total_cycles'] ?></p>    
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small">Next Contribution Due</div>
                                <p class="fw-bold">
                                    <?php if ($nextPayment): ?>
                                        <?= date('F j, Y', strtotime($nextPayment)) ?>
                                    <?php else: ?>
                                        No payment due yet.
                                    <?php endif; ?>  
                                </p>
                            </div>
                                        
                            <div class="mb-3">
                                <div class="text-muted small">Payment Method</div>
                                <select class="form-select" id="paymentMethod" name="payment_method">
                                    <option value="wallet">Wallet</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small">Payment Reference</div>
                                <input type="text" class="form-control" id="paymentReference" name="payment_reference" placeholder="Enter payment reference">
                            </div>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="make_contribution" class="btn btn-primary" form="contributionForm">Submit</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Group Modal -->
<div class="modal fade" id="deleteGroupModal" tabindex  ="-1" aria-labelledby="deleteGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteGroupModalLabel">Delete Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this group? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="?page=sol-delete&id=<?= $groupId ?>" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<!-- Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGroupModalLabel">Edit Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="?page=sol-edit&id=<?= $groupId ?>" method="POST" id="editGroupForm">
                    <div class="mb-3">
                        <label for="groupName" class="form-label">Group Name</label>
                        <input type="text" class="form-control" id="groupName" name="group_name" value="<?= htmlspecialchars($group['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="groupDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="groupDescription" name="description"><?= htmlspecialchars($group['description']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="memberLimit" class="form-label">Member Limit</label>
                        <input type="number" class="form-control" id="memberLimit" name="member_limit" value="<?= $group['member_limit'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="contributionAmount" class="form-label">Contribution Amount</label>
                        <input type="number" class="form-control" id="contributionAmount" name="contribution" value="<?= $group['contribution'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="frequency" class="form-label">Frequency</label>
                        <select class="form-select" id="frequency" name="frequency">
                            <option value="weekly" <?= $group['frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="biweekly" <?= $group['frequency'] === 'biweekly' ? 'selected' : '' ?>>Biweekly</option>
                            <option value="monthly" <?= $group['frequency'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="totalCycles" class="form-label">Total Cycles</label>
                        <input type="number" class="form-control" id="totalCycles" name="total_cycles" value="<?= $group['total_cycles'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="visibility" class="form-label">Visibility</label>
                        <select class="form-select" id="visibility" name="visibility">
                            <option value="public" <?= $group['visibility'] === 'public' ? 'selected' : '' ?>>Public</option>
                            <option value="private" <?= $group['visibility'] === 'private' ? 'selected' : '' ?>>Private</option>
                        </select>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" form="editGroupForm">Save changes</button>
            </div>
        </div>
    </div>
</div> 

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-labelledby="addMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMemberModalLabel">Add Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST" id="addMemberForm">
                    <input type="hidden" name="add_member" value="1">
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                    <div class="mb-3">
                        <label for="addMemberName" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="addMemberName" name="name" required
                               placeholder="Enter full name">
                    </div>
                    <div class="mb-3">
                        <label for="addMemberEmail" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="addMemberEmail" name="email" required
                               placeholder="Enter email address">
                    </div>
                    <div class="mb-3">
                        <label for="addMemberPhone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="addMemberPhone" name="phone" required
                               placeholder="Enter phone number">
                    </div>
                    <div class="mb-3">
                        <label for="addMemberRole" class="form-label">Role</label>
                        <select class="form-select" id="addMemberRole" name="role">
                            <option value="member">Member</option>
                            <?php if ($userRole === 'admin'): ?>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i>
                        <small>If the user doesn't exist, a new account will be created with a generated password.</small>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" form="addMemberForm">Add Member</button>
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
            <div class="modal-body">
                <form action="" method="POST" id="inviteForm">
                    <div class="mb-3">
                        <label for="inviteEmail" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="inviteEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="inviteRole" class="form-label">Role</label>
                        <select class="form-select" id="inviteRole" name="role">
                            <option value="member">Member</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                </form> 
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="invite_member" class="btn btn-primary" form="inviteForm">Invite</button>
            </div>
        </div>
    </div>
</div>

<!-- Message Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalLabel">Send Message to Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST" id="messageForm">
                    <div class="mb-3">
                        <label for="messageText" class="form-label">Message</label>
                        <textarea class="form-control" id="messageText" name="message" rows="3" required></textarea>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" form="messageForm">Send</button>
            </div>
        </div>
    </div>
</div>

<!-- New User Information Modal -->
<?php if (isset($_SESSION['new_user_info'])): ?>
<div class="modal fade" id="newUserInfoModal" tabindex="-1" aria-labelledby="newUserInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="newUserInfoModalLabel">
                    <i class="fas fa-user-plus me-2"></i>New User Created Successfully!
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>User account created and added to the SOL group!</strong>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Share these login credentials with the new member:</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Name:</strong></div>
                            <div class="col-sm-9"><?= htmlspecialchars($_SESSION['new_user_info']['name']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Email:</strong></div>
                            <div class="col-sm-9">
                                <code><?= htmlspecialchars($_SESSION['new_user_info']['email']) ?></code>
                                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?= $_SESSION['new_user_info']['email'] ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Phone:</strong></div>
                            <div class="col-sm-9"><?= htmlspecialchars($_SESSION['new_user_info']['phone']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Password:</strong></div>
                            <div class="col-sm-9">
                                <code><?= htmlspecialchars($_SESSION['new_user_info']['password']) ?></code>
                                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?= $_SESSION['new_user_info']['password'] ?>')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Role:</strong></div>
                            <div class="col-sm-9">
                                <span class="badge bg-primary"><?= ucfirst($_SESSION['new_user_info']['role']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <small><strong>Important:</strong> Please save these credentials securely and share them with the new member. The password will not be shown again.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it!</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Share</button>
                <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-show the modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('newUserInfoModal'));
    modal.show();
    
    // Clear session data after modal is closed
    document.getElementById('newUserInfoModal').addEventListener('hidden.bs.modal', function() {
        // You could make an AJAX call here to clear the session data
        fetch('<?= $_SERVER['PHP_SELF'] ?>?clear_session=1', {method: 'POST'});
    });
});

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show temporary success message
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(() => {
            btn.innerHTML = originalHTML;
        }, 2000);
    });
}
</script>

<?php 
// Clear the session data
unset($_SESSION['new_user_info']); 
?>
<?php endif; ?>

</div>
</div>
</div>
</div>
</div>


<style>
    .chat-preview-container {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .message-item {
        border-left: 3px solid transparent;
        padding-left: 8px;
        transition: all 0.2s ease;
    }
    
    .message-item:hover {
        background-color: rgba(0, 123, 255, 0.05);
        border-left-color: #007bff;
    }
    
    .message-item.own-message {
        border-left-color: #28a745;
        background-color: rgba(40, 167, 69, 0.05);
    }
    
    .message-text {
        font-size: 14px;
        line-height: 1.4;
        margin-top: 2px;
        word-wrap: break-word;
    }
    
    .message-header {
        margin-bottom: 2px;
    }
    
    .quick-message-form .form-control {
        border-radius: 20px;
    }
    
    .quick-message-form .btn {
        border-radius: 20px;
        padding: 6px 12px;
    }
    
    .avatar-sm {
        width: 36px !important;
        height: 36px !important;
        object-fit: cover;
        border-radius: 50% !important;
        display: inline-block;
    }
    .avatar-placeholder.avatar-sm {
        width: 36px !important;
        height: 36px !important;
        border-radius: 50% !important;
        font-size: 1.1rem;
    }
    .message-avatar {
        flex-shrink: 0;
    }
</style>

<script>
    function showAlert(type, message) {
        const alertPlaceholder = document.getElementById('alertPlaceholder');
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `<div class="alert alert-${type} alert-dismissible" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        alertPlaceholder.append(wrapper);
        setTimeout(() => {
            wrapper.remove();
        }, 5000);   
    }
    document.addEventListener('DOMContentLoaded', function () {
        const contributeModal = document.getElementById('contributeModal');
        const deleteGroupModal = document.getElementById('deleteGroupModal');
        const editGroupModal = document.getElementById('editGroupModal');
        const inviteModal = document.getElementById('inviteModal');
        const alertPlaceholder = document.getElementById('alertPlaceholder');   
        
        // Handle flash messages if they exist
        <?php if (isset($_SESSION['flash_type']) && isset($_SESSION['flash_message'])): ?>
            showAlert('<?= $_SESSION['flash_type'] ?>', '<?= $_SESSION['flash_message'] ?>');
            <?php 
            unset($_SESSION['flash_type']);
            unset($_SESSION['flash_message']);
            ?>
        <?php endif; ?>
        
        // Contribution Modal
        if (contributeModal) {
            contributeModal.addEventListener('show.bs.modal', function () {
                // Reset form fields
                document.getElementById('paymentMethod').value = 'bank_transfer';
                document.getElementById('paymentReference').value = '';
            });
            
            // Handle contribution form submission
            const contributionForm = document.getElementById('contributionForm');
            if (contributionForm) {
                contributionForm.addEventListener('submit', function(e) {
                    const paymentReference = document.getElementById('paymentReference').value;
                    if (!paymentReference.trim()) {
                        e.preventDefault();
                        alert('Please enter a payment reference.');
                        return false;
                    }
                });
            }
        }
        
        // Invite Modal
        if (inviteModal) {
            inviteModal.addEventListener('show.bs.modal', function () {
                // Reset form fields
                document.getElementById('inviteEmail').value = '';
                document.getElementById('inviteRole').value = 'member';
            });
            
            // Handle invite form submission
            const inviteForm = document.getElementById('inviteForm');
            if (inviteForm) {
                inviteForm.addEventListener('submit', function(e) {
                    const email = document.getElementById('inviteEmail').value;
                    if (!email.trim()) {
                        e.preventDefault();
                        alert('Please enter an email address.');
                        return false;
                    }
                    // Basic email validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Please enter a valid email address.');
                        return false;
                    }
                });
            }
        }
        
        // Edit Group Modal  
        if (editGroupModal) {
            editGroupModal.addEventListener('show.bs.modal', function () {
                // Fields are already populated with current values
                // No need to reset
            });
        }
    });
</script>