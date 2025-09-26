<?php
// Start the session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once __DIR__ . '/../includes/flash-messages.php';
  
// Set page title
$pageTitle = "SOL Groups";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Generate a CSRF token if not already exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Initialize variables
$myGroups = [];
$availableGroups = [];
$error = null;

try {
    $db = getDbConnection();
    
    // Get groups the user participates in
    $myGroupsStmt = $db->prepare("
        SELECT sg.*, 
            COUNT(sp2.user_id) as member_count,
            sp1.role as user_role
        FROM sol_groups sg
        INNER JOIN sol_participants sp1 ON sg.id = sp1.sol_group_id AND sp1.user_id = ?
        LEFT JOIN sol_participants sp2 ON sg.id = sp2.sol_group_id
        GROUP BY sg.id, sp1.role
        ORDER BY sg.created_at DESC
    ");
    $myGroupsStmt->execute([$userId]);
    $myGroups = $myGroupsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available groups to join
    $availableGroupsStmt = $db->prepare("
        SELECT sg.*, 
               COUNT(sp.user_id) as member_count
        FROM sol_groups sg
        LEFT JOIN sol_participants sp ON sg.id = sp.sol_group_id
        WHERE sg.status = 'active' 
        AND sg.visibility = 'public'
        AND sg.id NOT IN (
            SELECT sol_group_id 
            FROM sol_participants 
            WHERE user_id = ?
        )
        GROUP BY sg.id
        HAVING member_count < sg.member_limit
        ORDER BY sg.created_at DESC
        LIMIT 10
    ");
    $availableGroupsStmt->execute([$userId]);
    $availableGroups = $availableGroupsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending invitations for the user
    $invitationsStmt = $db->prepare("
        SELECT si.*, sg.name as group_name, sg.description as group_description,
               sg.contribution, sg.frequency, sg.total_cycles, sg.member_limit,
               u.full_name as inviter_name,
               COUNT(sp.user_id) as member_count
        FROM sol_invitations si
        JOIN sol_groups sg ON si.sol_group_id = sg.id
        JOIN users u ON si.invited_by = u.id
        LEFT JOIN sol_participants sp ON sg.id = sp.sol_group_id
        WHERE si.invited_user_id = ? AND si.status = 'pending'
        GROUP BY si.id
        ORDER BY si.created_at DESC
    ");
    $invitationsStmt->execute([$userId]);
    $invitations = $invitationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('SOL groups data error: ' . $e->getMessage());
    $error = 'An error occurred while loading SOL groups.';
}

// Handle group creation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    // Validate CSRF token first
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $name = filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW);
        $description = filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW);
        $contribution = filter_input(INPUT_POST, 'contribution', FILTER_VALIDATE_FLOAT);
        $frequency = filter_input(INPUT_POST, 'frequency', FILTER_UNSAFE_RAW);
        $totalCycles = filter_input(INPUT_POST, 'total_cycles', FILTER_VALIDATE_INT);
        $memberLimit = filter_input(INPUT_POST, 'member_limit', FILTER_VALIDATE_INT);
        $visibility = filter_input(INPUT_POST, 'visibility', FILTER_UNSAFE_RAW);
        
        // Sanitize inputs
        $name = htmlspecialchars(trim($name ?? ''), ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(trim($description ?? ''), ENT_QUOTES, 'UTF-8');
        $frequency = htmlspecialchars(trim($frequency ?? ''), ENT_QUOTES, 'UTF-8');
        $visibility = htmlspecialchars(trim($visibility ?? ''), ENT_QUOTES, 'UTF-8');
        
        // Validation
        $errors = [];
        
        if (empty($name) || strlen($name) < 3) {
            $errors[] = 'Group name must be at least 3 characters.';
        }
        
        if (!$contribution || $contribution <= 0) {
            $errors[] = 'Please enter a valid contribution amount.';
        }
        
        if (!in_array($frequency, ['daily', 'every3days', 'weekly', 'biweekly', 'monthly'])) {
            $errors[] = 'Please select a valid contribution frequency.';
        }
        
        if (!$totalCycles || $totalCycles < 3 || $totalCycles > 24) {
            $errors[] = 'Total cycles must be between 3 and 24.';
        }
        
        if (!$memberLimit || $memberLimit < 3 || $memberLimit > 20) {
            $errors[] = 'Member limit must be between 3 and 20.';
        }
        
        // Check if the user is verified (KYC approved)
        $userStmt = $db->prepare("SELECT kyc_verified FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !$user['kyc_verified']) {
            $errors[] = 'You must complete KYC verification to create a SOL group.';
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // 1. Create new SOL group - FIXED: Added admin_id field - Add creator as group admin
                // Generate group ID
                $groupId = generateUuid();
                $createStmt = $db->prepare("
                    INSERT INTO sol_groups (
                        id, admin_id, name, description, contribution, frequency, 
                        total_cycles, current_cycle, member_limit, visibility, 
                        status, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, 
                        ?, 1, ?, ?, 
                        'active', NOW(), NOW()
                    )
                ");
                $createStmt->execute([
                    $groupId,
                    $userId,
                    $name,
                    $description,
                    $contribution,
                    $frequency,
                    $totalCycles,
                    $memberLimit,
                    $visibility
                ]);

                // 2. Add creator as first participant
                $participantId = generateUuid();
                // Add creator as admin
                $participantStmt = $db->prepare("
                    INSERT INTO sol_participants (
                        id, sol_group_id, user_id, role, join_date, 
                        contribution_due_date, payout_position, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, 'admin', NOW(),
                        DATE_ADD(NOW(), INTERVAL CASE ? 
                            WHEN 'daily' THEN 1 
                            WHEN 'every3days' THEN 3
                            WHEN 'weekly' THEN 7 
                            WHEN 'biweekly' THEN 14 
                            ELSE 30 END DAY),
                        1, NOW(), NOW()
                    )
                ");

                $participantStmt->execute([
                    $participantId, 
                    $groupId, 
                    $userId, 
                    $frequency
                ]);

                // 3. Generate payout schedule
                //$payoutManager->regeneratePayoutSchedule($groupId);
                
                $db->commit();
                //return $groupId;

                // Flash message
                setFlashMessage('success', 'SOL group created successfully!');                                                          
                redirect('?page=sol-details&id=' . $groupId);
                
            } catch (PDOException $e) {
                $db->rollBack();
                error_log('SOL group creation error: ' . $e->getMessage());
                error_log('SQL Error Info: ' . print_r($db->errorInfo(), true));
                
                // Provide more specific error message
                if (strpos($e->getMessage(), 'Integrity constraint violation') !== false && 
                    strpos($e->getMessage(), 'admin_id') !== false) {
                    setFlashMessage('error', 'Failed to create SOL group: Invalid user reference. Please contact support.');
                } else {
                    setFlashMessage('error', 'An error occurred while creating the SOL group: ' . $e->getMessage());
                }
            }
        } else {
            // Set error messages
            foreach ($errors as $errorMsg) {
                setFlashMessage('error', $errorMsg);
            }
            // Store form data in session to repopulate form
            $_SESSION['form_data'] = $_POST;
        }
    }
}
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h2 class="fw-bold mb-3">SOL Groups</h2>
                        <p class="text-muted">SOL (Soliarite) groups are community savings clubs where members contribute regularly and take turns receiving the pool.</p>
                    </div>
                    <div class="col-md-5 text-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                            <i class="fas fa-plus-circle me-2"></i> Create New SOL Group
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Display flash messages -->

<!-- My SOL Groups -->
<div class="row">
    <div class="col-12 mb-4">
        <h4 class="fw-bold mb-3">
            <i class="fas fa-users text-primary me-2"></i> My SOL Groups
        </h4>
        
        <?php if (empty($myGroups)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <div class="py-4">
                        <i class="fas fa-user-friends text-muted mb-3" style="font-size: 3rem;"></i>
                        <h5 class="fw-bold mb-3">You haven't joined any SOL groups yet</h5>
                        <p class="text-muted mb-4">Join an existing group or create your own to start saving with your community.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                            Create New SOL Group
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($myGroups as $group): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge bg-<?= $group['status'] === 'active' ? 'success' : 'warning' ?> rounded-pill">
                                        <?= ucfirst($group['status']) ?>
                                    </span>
                                    <span class="badge bg-primary rounded-pill">
                                        Cycle <?= $group['current_cycle'] ?>/<?= $group['total_cycles'] ?>
                                    </span>
                                </div>
                                
                                <h5 class="fw-bold mb-2">
                                    <a href="?page=sol-details&id=<?= $group['id'] ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($group['name']) ?>
                                    </a>
                                </h5>
                                
                                <p class="text-muted small mb-3">
                                    <?php if (!is_null($group['description'])): ?>
                                        <?= htmlspecialchars(substr($group['description'], 0, 100)) ?><?= strlen($group['description']) > 100 ? '...' : '' ?>
                                    <?php else: ?>
                                        <em>No description available</em>
                                    <?php endif; ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3 small">
                                    <span>
                                        <i class="fas fa-users me-1"></i> <?= $group['member_count'] ?>/<?= $group['member_limit'] ?> members
                                    </span>
                                    <span>
                                        <i class="fas fa-money-bill-wave me-1"></i> <?= number_format($group['contribution']) ?> HTG/<?= $group['frequency'] ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?= 
                                        $group['user_role'] === 'admin' ? 'danger' : 
                                        ($group['user_role'] === 'manager' ? 'warning' : 'info') 
                                    ?>">
                                        <?= ucfirst($group['user_role']) ?>
                                    </span>
                                    <a href="?page=sol-details&id=<?= $group['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </a>
                                    <!-- Button visible only for Admin/Manager -->
                                    <?php if ($group['user_role'] === 'admin' || $group['user_role'] === 'manager'): ?>
                                        <a href="?page=sol-manage&id=<?= $group['id'] ?>" class="btn btn-sm btn-outline-success">
                                            Manage Group
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SOL Group Invitations -->
<?php if (!empty($invitations)): ?>
<div class="row">
    <div class="col-12 mb-4">
        <h4 class="fw-bold mb-3">
            <i class="fas fa-envelope text-warning me-2"></i> Group Invitations
            <span class="badge bg-warning text-dark"><?= count($invitations) ?></span>
        </h4>
        
        <div class="row">
            <?php foreach ($invitations as $invitation): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card border-warning shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-warning text-dark rounded-pill">
                                    <i class="fas fa-envelope me-1"></i> Invitation
                                </span>
                                <span class="text-muted small">
                                    <?= date('M j, Y', strtotime($invitation['created_at'])) ?>
                                </span>
                            </div>
                            
                            <h5 class="fw-bold mb-2">
                                <?= htmlspecialchars($invitation['group_name']) ?>
                            </h5>
                            
                            <p class="text-muted small mb-3">
                                <i class="fas fa-user me-1"></i>
                                Invited by: <strong><?= htmlspecialchars($invitation['inviter_name']) ?></strong>
                            </p>
                            
                            <?php if (!empty($invitation['message'])): ?>
                            <div class="alert alert-light border-0 mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-quote-left me-1"></i>
                                    <?= htmlspecialchars($invitation['message']) ?>
                                </small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="text-primary fw-bold">HTG <?= number_format($invitation['contribution'], 2) ?></div>
                                    <small class="text-muted">Contribution</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-success fw-bold"><?= ucfirst($invitation['frequency']) ?></div>
                                    <small class="text-muted">Frequency</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-info fw-bold"><?= $invitation['member_count'] ?>/<?= $invitation['member_limit'] ?></div>
                                    <small class="text-muted">Members</small>
                                </div>
                            </div>
                            
                            <?php if (!empty($invitation['group_description'])): ?>
                            <p class="text-muted small mb-3">
                                <?= htmlspecialchars(substr($invitation['group_description'], 0, 100)) ?><?= strlen($invitation['group_description']) > 100 ? '...' : '' ?>
                            </p>
                            <?php endif; ?>
                            
                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="button" 
                                            class="btn btn-success btn-sm w-100 accept-invitation-btn"
                                            data-invitation-id="<?= $invitation['id'] ?>"
                                            data-group-name="<?= htmlspecialchars($invitation['group_name']) ?>">
                                        <i class="fas fa-check me-1"></i> Accept
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm w-100 reject-invitation-btn"
                                            data-invitation-id="<?= $invitation['id'] ?>"
                                            data-group-name="<?= htmlspecialchars($invitation['group_name']) ?>">
                                        <i class="fas fa-times me-1"></i> Decline
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Available SOL Groups -->
<?php if (!empty($availableGroups)): ?>
<div class="row">
    <div class="col-12 mb-4">
        <h4 class="fw-bold mb-3">
            <i class="fas fa-search text-primary me-2"></i> Available SOL Groups
        </h4>
        
        <div class="row">
            <?php foreach ($availableGroups as $group): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-success rounded-pill">
                                    <?= ucfirst($group['status']) ?>
                                </span>
                                <span class="badge bg-primary rounded-pill">
                                    Cycle <?= $group['current_cycle'] ?>/<?= $group['total_cycles'] ?>
                                </span>
                            </div>
                            
                            <h5 class="fw-bold mb-2">
                                <?= htmlspecialchars($group['name']) ?>
                            </h5>
                            
                            <p class="text-muted small mb-3">
                                <?php if (!is_null($group['description'])): ?>
                                    <?= htmlspecialchars(substr($group['description'], 0, 100)) ?><?= strlen($group['description']) > 100 ? '...' : '' ?>
                                <?php else: ?>
                                    <em>No description available</em>
                                <?php endif; ?>
                            </p>
                                                        
                            <div class="d-flex justify-content-between align-items-center mb-3 small">
                                <span>
                                    <i class="fas fa-users me-1"></i> <?= $group['member_count'] ?>/<?= $group['member_limit'] ?> members
                                </span>
                                <span>
                                    <i class="fas fa-money-bill-wave me-1"></i> <?= number_format($group['contribution']) ?> HTG/<?= $group['frequency'] ?>
                                </span>
                            </div>
                            
                            <?php if ($group['member_count'] < $group['member_limit']): ?>
                                <div class="d-grid">
                                    <a href="?page=sol-join&id=<?= $group['id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-handshake me-2"></i> Join Group
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="d-grid">
                                    <button class="btn btn-secondary" disabled>
                                        <i class="fas fa-users-slash me-2"></i> Group Full
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- How SOL Groups Work -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3">
                    <i class="fas fa-info-circle text-primary me-2"></i> How SOL Groups Work
                </h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="d-flex mb-4">
                            <div class="me-3">
                                <div class="step-circle bg-primary text-white">1</div>
                            </div>
                            <div>
                                <h5>Join or Create a Group</h5>
                                <p class="text-muted">Find an existing group to join or create your own with friends, family, or community members.</p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-4">
                            <div class="me-3">
                                <div class="step-circle bg-primary text-white">2</div>
                            </div>
                            <div>
                                <h5>Contribute Regularly</h5>
                                <p class="text-muted">Make your agreed contribution on schedule (weekly, biweekly, or monthly).</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="d-flex mb-4">
                            <div class="me-3">
                                <div class="step-circle bg-primary text-white">3</div>
                            </div>
                            <div>
                                <h5>Take Turns Receiving Funds</h5>
                                <p class="text-muted">Each cycle, a different member receives the full pool of contributions based on the predetermined order.</p>
                            </div>
                        </div>
                        
                        <div class="d-flex mb-4">
                            <div class="me-3">
                                <div class="step-circle bg-primary text-white">4</div>
                            </div>
                            <div>
                                <h5>Complete the Cycle</h5>
                                <p class="text-muted">When all members have received their payout, the group completes its cycle or can restart.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="?page=faq" class="btn btn-outline-primary">
                        <i class="fas fa-question-circle me-2"></i> Learn More About SOL Groups
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create SOL Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1" aria-labelledby="createGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createGroupModalLabel">Create New SOL Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="?page=sol-groups" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Group Name</label>
                        <input type="text" class="form-control" id="name" name="name" minlength="3" maxlength="50" 
                               value="<?= isset($_SESSION['form_data']['name']) ? htmlspecialchars($_SESSION['form_data']['name']) : '' ?>" required>
                        <?php unset($_SESSION['form_data']['name']); ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" maxlength="500"><?= isset($_SESSION['form_data']['description']) ? htmlspecialchars($_SESSION['form_data']['description']) : '' ?></textarea>
                        <?php unset($_SESSION['form_data']['description']); ?>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="contribution" class="form-label">Contribution Amount (HTG)</label>
                            <input type="number" class="form-control" id="contribution" name="contribution" min="250" step="500" 
                                   value="<?= isset($_SESSION['form_data']['contribution']) ? htmlspecialchars($_SESSION['form_data']['contribution']) : '' ?>" required>
                            <?php unset($_SESSION['form_data']['contribution']); ?>
                        </div>
                        <div class="col-md-6">
                            <label for="frequency" class="form-label">Contribution Frequency</label>
                            <select class="form-select" id="frequency" name="frequency" required>
                                <option value="daily" <?= (isset($_SESSION['form_data']['frequency']) && $_SESSION['form_data']['frequency'] === 'daily') ? 'selected' : '' ?>>Daily</option>
                                <option value="every3days" <?= (isset($_SESSION['form_data']['frequency']) && $_SESSION['form_data']['frequency'] === 'every3days') ? 'selected' : '' ?>>Every 3 Days</option>
                                <option value="weekly" <?= (isset($_SESSION['form_data']['frequency']) && $_SESSION['form_data']['frequency'] === 'weekly') ? 'selected' : '' ?>>Weekly</option>
                                <option value="biweekly" <?= (isset($_SESSION['form_data']['frequency']) && $_SESSION['form_data']['frequency'] === 'biweekly') ? 'selected' : '' ?>>Biweekly</option>
                                <option value="monthly" <?= (!isset($_SESSION['form_data']['frequency']) || (isset($_SESSION['form_data']['frequency']) && $_SESSION['form_data']['frequency'] === 'monthly')) ? 'selected' : '' ?>>Monthly</option>
                            </select>
                            <?php unset($_SESSION['form_data']['frequency']); ?>
                            <div class="form-text">Choose how often members will contribute</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="total_cycles" class="form-label">Total Cycles</label>
                            <input type="number" class="form-control" id="total_cycles" name="total_cycles" min="3" max="24" 
                                   value="<?= isset($_SESSION['form_data']['total_cycles']) ? htmlspecialchars($_SESSION['form_data']['total_cycles']) : '12' ?>" required>
                            <?php unset($_SESSION['form_data']['total_cycles']); ?>
                            <div class="form-text">Number of payouts before group completion</div>
                        </div>
                        <div class="col-md-6">
                            <label for="member_limit" class="form-label">Member Limit</label>
                            <input type="number" class="form-control" id="member_limit" name="member_limit" min="3" max="20" 
                                   value="<?= isset($_SESSION['form_data']['member_limit']) ? htmlspecialchars($_SESSION['form_data']['member_limit']) : '10' ?>" required>
                            <?php unset($_SESSION['form_data']['member_limit']); ?>
                            <div class="form-text">Maximum 20 members per group</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?= isset($_SESSION['form_data']['start_date']) ? htmlspecialchars($_SESSION['form_data']['start_date']) : '' ?>" required>
                            <?php unset($_SESSION['form_data']['start_date']); ?>
                            <div class="form-text">Select the start date for the group</div>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?= isset($_SESSION['form_data']['end_date']) ? htmlspecialchars($_SESSION['form_data']['end_date']) : '' ?>" required>
                            <?php unset($_SESSION['form_data']['end_date']); ?>
                            <div class="form-text">Select the end date for the group</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="visibility" class="form-label">Group Visibility</label>
                        <select class="form-select" id="visibility" name="visibility" required>
                            <option value="public" <?= (!isset($_SESSION['form_data']['visibility']) || (isset($_SESSION['form_data']['visibility']) && $_SESSION['form_data']['visibility'] === 'public')) ? 'selected' : '' ?>>Public - Anyone can find and join</option>
                            <option value="private" <?= (isset($_SESSION['form_data']['visibility']) && $_SESSION['form_data']['visibility'] === 'private') ? 'selected' : '' ?>>Private - Invitation only</option>
                        </select>
                        <?php unset($_SESSION['form_data']['visibility']); ?>
                    </div>
                    
                    <div class="alert alert-info">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-info-circle fa-lg"></i>
                            </div>
                            <div>
                                <p class="mb-0">By creating a SOL group, you agree to:</p>
                                <ul class="mb-0">
                                    <li>Make your own contributions on time</li>
                                    <li>Manage the group responsibly</li>
                                    <li>Follow SoSol's community guidelines</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="create_group" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i> Create SOL Group
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.querySelector('form[method="POST"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const contribution = document.getElementById('contribution');
            const totalCycles = document.getElementById('total_cycles');
            const memberLimit = document.getElementById('member_limit');
            
            if (contribution && contribution.value < 250) {
                e.preventDefault();
                alert('Contribution amount must be at least 250 HTG.');
                contribution.focus();
                return false;
            }
            
            if (totalCycles && (totalCycles.value < 3 || totalCycles.value > 24)) {
                e.preventDefault();
                alert('Total cycles must be between 3 and 24.');
                totalCycles.focus();
                return false;
            }
            
            if (memberLimit && (memberLimit.value < 3 || memberLimit.value > 20)) {
                e.preventDefault();
                alert('Member limit must be between 3 and 20.');
                memberLimit.focus();
                return false;
            }
        });
    }
    
    // Handle invitation acceptance and rejection
    document.querySelectorAll('.accept-invitation-btn, .reject-invitation-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const invitationId = this.getAttribute('data-invitation-id');
            const groupName = this.getAttribute('data-group-name');
            const action = this.classList.contains('accept-invitation-btn') ? 'accept' : 'reject';
            const actionText = action === 'accept' ? 'accept' : 'decline';
            
            if (confirm(`Are you sure you want to ${actionText} the invitation to join "${groupName}"?`)) {
                processInvitation(invitationId, action);
            }
        });
    });
    
    function processInvitation(invitationId, action) {
        const formData = new FormData();
        formData.append('invitation_id', invitationId);
        formData.append('action', action);
        formData.append('csrf_token', '<?= $csrfToken ?>');
        
        // Show loading state
        const buttons = document.querySelectorAll(`[data-invitation-id="${invitationId}"]`);
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
        
        fetch('../actions/process-invitation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Show success message
                alert(data.message);
                // Reload page to update invitations
                window.location.reload();
            } else {
                // Show error message
                alert('Error: ' + data.message);
                // Re-enable buttons
                buttons.forEach(btn => {
                    btn.disabled = false;
                    if (btn.classList.contains('accept-invitation-btn')) {
                        btn.innerHTML = '<i class="fas fa-check me-1"></i> Accept';
                    } else {
                        btn.innerHTML = '<i class="fas fa-times me-1"></i> Decline';
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing the invitation. Please try again.');
            // Re-enable buttons
            buttons.forEach(btn => {
                btn.disabled = false;
                if (btn.classList.contains('accept-invitation-btn')) {
                    btn.innerHTML = '<i class="fas fa-check me-1"></i> Accept';
                } else {
                    btn.innerHTML = '<i class="fas fa-times me-1"></i> Decline';
                }
            });
        });
    }
});
</script>