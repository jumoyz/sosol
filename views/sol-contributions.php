<?php
// Set page title
$pageTitle = "SOL Group Contributions";
$pageDescription = "View and manage contributions for the selected SOL group.";

// Start session if not started
if (session_status() === PHP_SESSION_NONE) session_start();

// Require helpers
require_once __DIR__ . '/../includes/flash-messages.php';

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

// Current user role (if set in session)
$userRole = isset($_SESSION['user_role']) ? strtolower(trim((string) $_SESSION['user_role'])) : null;

if (!$userId) {
    redirect('?page=login');
}

// Retrieve validated group ID
$groupId = requireValidGroupId('?page=sol-groups');

// Initialize variables
$group = null;
$members = [];
$pendingContributions = [];
$totals = ['next_cycle_total' => 0, 'pending_total' => 0];
$error = null;

// Prepare container for all contributions (visible to admins)
$allContributions = [];
// Track whether optional columns exist in sol_contributions (keeps code robust across schemas)
$hasKycNotes = false;

// Handle approve/reject actions (admin/manager)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
    $db = getDbConnection();

    // Detect optional columns in sol_contributions (e.g., kyc_notes)
    $colStmt = $db->prepare("SHOW COLUMNS FROM sol_contributions LIKE 'kyc_notes'");
    $colStmt->execute();
    $hasKycNotes = (bool)$colStmt->fetch();

        // Manual registration by admin/manager
        if (isset($_POST['manual_register_contribution']) && ($userRole === 'admin' || $userRole === 'manager')) {
            $participantId = $_POST['participant_id'] ?? null;
            $amount = $_POST['amount'] ?? null;
            $cycle = $_POST['cycle_number'] ?? null;
            if ($participantId && $amount) {
                // Get the user_id for the participant
                $pstmt = $db->prepare("SELECT user_id, sol_group_id FROM sol_participants WHERE id = ? LIMIT 1");
                $pstmt->execute([$participantId]);
                $pinfo = $pstmt->fetch(PDO::FETCH_ASSOC);
                // $pinfo may be false/null if no row found; guard access to avoid PHP warnings
                if (is_array($pinfo)) {
                    $participantUserId = $pinfo['user_id'] ?? null;
                    $solGroupIdFromParticipant = $pinfo['sol_group_id'] ?? null;
                } else {
                    $participantUserId = null;
                    $solGroupIdFromParticipant = null;
                }

                // Ensure participant belongs to this group
                if (!$participantUserId || ($solGroupIdFromParticipant && $solGroupIdFromParticipant !== $groupId)) {
                    setFlashMessage('error', 'Invalid participant for this group.');
                } else {
                    $contribId = generateUuid();
                    // Ensure we have a current cycle value even if $group wasn't loaded yet (POST ran before fetch)
                    $currentCycle = null;
                    if (is_array($group) && array_key_exists('current_cycle', $group)) {
                        $currentCycle = $group['current_cycle'];
                    } else {
                        try {
                            $gstmt = $db->prepare("SELECT current_cycle FROM sol_groups WHERE id = ? LIMIT 1");
                            $gstmt->execute([$groupId]);
                            $ginfo = $gstmt->fetch(PDO::FETCH_ASSOC);
                            if (is_array($ginfo) && array_key_exists('current_cycle', $ginfo)) {
                                $currentCycle = $ginfo['current_cycle'];
                            }
                        } catch (Exception $eg) {
                            error_log('Could not fetch group current_cycle: ' . $eg->getMessage());
                            $currentCycle = null;
                        }
                    }
                    // Fallback to provided cycle, or group current cycle, or 0
                    $cycleNumber = $cycle ?: ($currentCycle ?? 0);
                    // Insert using schema-complete columns
                    $insertSql = "INSERT INTO sol_contributions (id, sol_group_id, participant_id, user_id, amount, currency, contribution_date, status, cycle_number, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'HTG', NOW(), ?, ?, NOW(), NOW())";
                    $istmt = $db->prepare($insertSql);
                    $istmt->execute([$contribId, $groupId, $participantId, $participantUserId, $amount, 'paid', $cycleNumber]);
                    setFlashMessage('success', 'Manual contribution registered successfully.');
                }
            } else {
                setFlashMessage('error', 'Participant and amount are required.');
            }
        }

        // Delete contribution (admin only)
        if (isset($_POST['delete_contribution']) && $userRole === 'admin') {
            $delId = $_POST['contribution_id'] ?? null;
            if ($delId) {
                $stmt = $db->prepare("DELETE FROM sol_contributions WHERE id = ?");
                $stmt->execute([$delId]);
                setFlashMessage('success', 'Contribution deleted.');
            }
        }

        if (isset($_POST['approve_contribution'])) {
            $contribId = $_POST['contribution_id'] ?? null;
            if ($contribId) {
                $stmt = $db->prepare("UPDATE sol_contributions SET status = 'paid', updated_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$contribId]);
                setFlashMessage('success', 'Contribution approved.');
            }
        } elseif (isset($_POST['reject_contribution'])) {
            $contribId = $_POST['contribution_id'] ?? null;
            $reason = trim($_POST['reject_reason'] ?? '');
            if ($contribId) {
                // Store reason in kyc_notes or a notes column if available
                $stmt = $db->prepare("UPDATE sol_contributions SET status = 'rejected', kyc_notes = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'");
                $stmt->execute([$reason, $contribId]);
                setFlashMessage('success', 'Contribution rejected.');
            }
        }

    // Redirect to avoid form resubmission - use helper which handles headers-sent fallback
    redirect('?page=sol-contributions&id=' . urlencode($groupId));

    } catch (Exception $e) {
        error_log('SOL contributions action error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred processing the action.');
    }
}

// Fetch data
try {
    $db = getDbConnection();

    // Detect optional columns in sol_contributions (e.g., kyc_notes) - run here so it's defined for later SELECTs
    try {
        $colStmt = $db->prepare("SHOW COLUMNS FROM sol_contributions LIKE 'kyc_notes'");
        $colStmt->execute();
        $hasKycNotes = (bool)$colStmt->fetch();
    } catch (Exception $inner) {
        // If the DB doesn't support SHOW COLUMNS or table missing, keep default false and log
        error_log('Could not detect kyc_notes column: ' . $inner->getMessage());
        $hasKycNotes = false;
    }

    // Get group data
    $groupStmt = $db->prepare("SELECT sg.*, COUNT(sp.user_id) as member_count FROM sol_groups sg LEFT JOIN sol_participants sp ON sg.id = sp.sol_group_id WHERE sg.id = ? GROUP BY sg.id");
    $groupStmt->execute([$groupId]);
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        setFlashMessage('error', 'SOL group not found.');
        redirect('?page=sol-groups');
    }

    // Members and their next contribution status
    $membersStmt = $db->prepare("SELECT sp.id AS participant_id, sp.user_id, sp.payout_position, u.full_name, u.profile_photo, u.kyc_verified FROM sol_participants sp INNER JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? ORDER BY sp.payout_position ASC");
    $membersStmt->execute([$groupId]);
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Pending contributions (awaiting approval)
    $pendingStmt = $db->prepare("SELECT sc.id, sc.participant_id, sc.amount, sc.cycle_number, sc.created_at, u.full_name, u.profile_photo FROM sol_contributions sc INNER JOIN sol_participants sp ON sc.participant_id = sp.id INNER JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? AND sc.status = 'pending' ORDER BY sc.created_at ASC");
    $pendingStmt->execute([$groupId]);
    $pendingContributions = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

    // All contributions for the group (admins can view)
    if ($hasKycNotes) {
        $allStmt = $db->prepare("SELECT sc.id, sc.participant_id, sc.amount, sc.cycle_number, sc.created_at, sc.status, sc.kyc_notes, u.full_name, sp.payout_position FROM sol_contributions sc INNER JOIN sol_participants sp ON sc.participant_id = sp.id INNER JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? ORDER BY sc.created_at DESC");
    } else {
        // kyc_notes doesn't exist; select empty notes instead to avoid SQL errors
        $allStmt = $db->prepare("SELECT sc.id, sc.participant_id, sc.amount, sc.cycle_number, sc.created_at, sc.status, '' AS kyc_notes, u.full_name, sp.payout_position FROM sol_contributions sc INNER JOIN sol_participants sp ON sc.participant_id = sp.id INNER JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? ORDER BY sc.created_at DESC");
    }
    $allStmt->execute([$groupId]);
    $allContributions = $allStmt->fetchAll(PDO::FETCH_ASSOC);

    // Totals: next cycle expected total and pending total
    $nextCycleTotal = 0;
    foreach ($members as $m) {
        $nextCycleTotal += floatval($group['contribution']);
    }
    $pendingTotal = 0;
    foreach ($pendingContributions as $p) {
        $pendingTotal += floatval($p['amount']);
    }
    $totals['next_cycle_total'] = $nextCycleTotal;
    $totals['pending_total'] = $pendingTotal;

} catch (PDOException $e) {
    error_log('SOL contributions error: ' . $e->getMessage());
    $error = 'An error occurred while loading contributions.';
}
?>

<div class="main-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold">SOL Group Contributions</h1>
                <p class="text-muted mb-0"><?= htmlspecialchars((string)($pageDescription ?? '')) ?></p>
            </div>
            <div>
                <a href="?page=sol-details&id=<?= htmlspecialchars((string)$groupId) ?>" class="btn btn-outline-secondary">Back to Group</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars((string)$error) ?></div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="mb-2">Group: <?= htmlspecialchars((string)($group['name'] ?? '')) ?></h5>
                        <p class="text-muted mb-1">Contribution per member: <?= number_format($group['contribution'] ?? 0, 2) ?> HTG</p>
                        <p class="text-muted mb-0">Members: <?= intval($group['member_count'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-end">
                        <div class="h6">Expected next cycle total</div>
                        <div class="fw-bold fs-4"><?= number_format($totals['next_cycle_total'], 2) ?> HTG</div>
                        <small class="text-muted">Pending contributions: <?= number_format($totals['pending_total'], 2) ?> HTG</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending contributions table -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Pending Contributions</h5>
                <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                    <div>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#manualContributionModal">
                            <i class="fas fa-plus-circle me-1"></i> Register Contribution
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pendingContributions)): ?>
                    <div class="p-4 text-center text-muted">No pending contributions.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Member</th>
                                    <th>Amount</th>
                                    <th>Cycle</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingContributions as $p): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center">
                                                <?= htmlspecialchars((string)substr($p['full_name'],0,2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars((string)($p['full_name'] ?? '')) ?></div>
                                                <small class="text-muted">Participant ID: <?= htmlspecialchars((string)$p['participant_id']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= number_format($p['amount'],2) ?> HTG</td>
                                    <td><?= intval($p['cycle_number']) ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($p['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" style="display:inline-block">
                                                <input type="hidden" name="contribution_id" value="<?= htmlspecialchars((string)$p['id']) ?>">
                                                <button type="submit" name="approve_contribution" class="btn btn-outline-success">Approve</button>
                                            </form>
                                            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= htmlspecialchars((string)$p['id']) ?>">Reject</button>
                                        </div>

                                        <!-- Reject modal -->
                                        <div class="modal fade" id="rejectModal<?= htmlspecialchars((string)$p['id']) ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Reject Contribution</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Provide a reason for rejection (optional):</p>
                                                            <textarea name="reject_reason" class="form-control" rows="3"></textarea>
                                                            <input type="hidden" name="contribution_id" value="<?= htmlspecialchars((string)$p['id']) ?>">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="reject_contribution" class="btn btn-danger">Reject Contribution</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Manual Contribution Modal (admin/manager) -->
        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
        <div class="modal fade" id="manualContributionModal" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Register Contribution</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Participant</label>
                                <select name="participant_id" class="form-select" required>
                                    <option value="">Select participant</option>
                                    <?php foreach ($members as $mem): ?>
                                        <option value="<?= htmlspecialchars((string)$mem['participant_id']) ?>"><?= htmlspecialchars((string)$mem['full_name']) ?> (Pos #<?= intval($mem['payout_position']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Amount (HTG)</label>
                                <input type="number" step="0.01" name="amount" class="form-control" required value="<?= htmlspecialchars((string)($group['contribution'] ?? '')) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cycle Number (optional)</label>
                                <input type="number" name="cycle_number" class="form-control" placeholder="Leave blank for current cycle">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="manual_register_contribution" class="btn btn-primary">Register Contribution</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contributions list (admins see all) / Members list for regular users -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent">
                <h5 class="mb-0"><?= ($userRole === 'admin' || $userRole === 'manager') ? 'Member Contributions' : 'Members' ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>When</th>
                                <th>Member</th>
                                <th>Pos</th>
                                <th>Amount</th>
                                <th>Cycle</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allContributions as $c): ?>
                            <tr>
                                <td><?= date('M j, Y g:i A', strtotime($c['created_at'])) ?></td>
                                <td><?= htmlspecialchars((string)($c['full_name'] ?? '')) ?></td>
                                <td><?= intval($c['payout_position']) ?></td>
                                <td><?= number_format($c['amount'],2) ?> HTG</td>
                                <td><?= intval($c['cycle_number']) ?></td>
                                <td>
                                    <?php
                                    $status = $c['status'] ?? 'unknown';
                                    $badge = 'secondary';
                                    if ($status === 'pending') $badge = 'warning text-dark';
                                    if ($status === 'paid') $badge = 'success';
                                    if ($status === 'rejected') $badge = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars((string)ucfirst($status)) ?></span>
                                </td>
                                <td><?= htmlspecialchars((string)($c['kyc_notes'] ?? '')) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($c['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline-block">
                                                <input type="hidden" name="contribution_id" value="<?= htmlspecialchars((string)$c['id']) ?>">
                                                <button type="submit" name="approve_contribution" class="btn btn-outline-success">Approve</button>
                                            </form>
                                            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= htmlspecialchars((string)$c['id']) ?>">Reject</button>
                                        <?php endif; ?>
                                        <?php if ($userRole === 'admin'): ?>
                                            <form method="POST" style="display:inline-block" onsubmit="return confirm('Delete this contribution?');">
                                                <input type="hidden" name="contribution_id" value="<?= htmlspecialchars((string)$c['id']) ?>">
                                                <button type="submit" name="delete_contribution" class="btn btn-outline-danger">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Reject modal duplicate for each contribution -->
                                    <div class="modal fade" id="rejectModal<?= htmlspecialchars((string)$c['id']) ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Contribution</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Provide a reason for rejection (optional):</p>
                                                        <textarea name="reject_reason" class="form-control" rows="3"></textarea>
                                                        <input type="hidden" name="contribution_id" value="<?= htmlspecialchars((string)$c['id']) ?>">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="reject_contribution" class="btn btn-danger">Reject Contribution</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <!-- Regular members view (unchanged) -->
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Pos</th>
                                    <th>Member</th>
                                    <th>KYC</th>
                                    <th>Next Due</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $m): ?>
                                <tr>
                                    <td><?= intval($m['payout_position']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-secondary text-white rounded-circle me-3 d-flex align-items-center justify-content-center">
                                                <?= htmlspecialchars((string)substr($m['full_name'],0,2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars((string)($m['full_name'] ?? '')) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($m['kyc_verified']): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $due = null; // Could query per participant if needed
                                        echo $due ? date('M j, Y', strtotime($due)) : '<span class="text-muted">—</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <a href="?page=sol-details&id=<?= htmlspecialchars((string)$groupId) ?>&participant=<?= htmlspecialchars((string)$m['participant_id']) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>