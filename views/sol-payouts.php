<?php
// Set page title
$pageTitle = "SOL Group Payouts";
$pageDescription = "View and manage payouts for the selected SOL group.";

// Start session if not started
if (session_status() === PHP_SESSION_NONE) session_start();

// Get current user data
$userId = $_SESSION['user_id'] ?? null;
$userRole = isset($_SESSION['user_role']) ? strtolower(trim((string) $_SESSION['user_role'])) : null;

if (!$userId) {
    redirect('?page=login');
}

// Retrieve validated group ID
$groupId = requireValidGroupId('?page=sol-groups');

// Initialize variables
$group = null;
$payouts = [];
$members = [];
$totals = ['pending' => 0, 'paid' => 0];
$error = null;

// Handle POST actions (mark paid/missed/delete/manual payout)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDbConnection();

        // Mark payout as paid (simple marker)
        if (isset($_POST['mark_paid']) && ($userRole === 'admin' || $userRole === 'manager')) {
            $pid = $_POST['payout_id'] ?? null;
            if ($pid) {
                $stmt = $db->prepare("UPDATE sol_payouts SET status = 'paid', paid_date = NOW(), updated_at = NOW() WHERE id = ? AND status != 'paid'");
                $stmt->execute([$pid]);
                setFlashMessage('success', 'Payout marked as paid.');
            }
        }

    // Process (Pay) payout: create a transaction record and mark payout paid
        if (isset($_POST['pay_payout']) && ($userRole === 'admin' || $userRole === 'manager')) {
            $pid = $_POST['payout_id'] ?? null;
            if ($pid) {
                // Fetch payout and participant user
                $q = $db->prepare("SELECT sp.id, sp.participant_id, par.user_id, sp.amount FROM sol_payouts sp LEFT JOIN sol_participants par ON sp.participant_id = par.id WHERE sp.id = ? LIMIT 1");
                $q->execute([$pid]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $recipientUserId = $row['user_id'] ?? null;
                    $amount = floatval($row['amount'] ?? 0);

                    // Find recipient wallet if available (HTG preferred)
                    $walletId = null;
                    if ($recipientUserId) {
                        $w = $db->prepare("SELECT id FROM wallets WHERE user_id = ? AND (currency = 'HTG' OR currency = '' OR currency IS NULL) LIMIT 1");
                        $w->execute([$recipientUserId]);
                        $winfo = $w->fetch(PDO::FETCH_ASSOC);
                        if (is_array($winfo) && !empty($winfo['id'])) {
                            $walletId = $winfo['id'];
                        }
                    }

                    // Fallback to system wallet if recipient wallet not found
                    if (empty($walletId)) {
                        $walletId = '10000000-0000-0000-0000-000000000001';
                    }

                    // Transaction type id for sol_payout (fallback to known code)
                    $txTypeId = null;
                    $tstmt = $db->prepare("SELECT id FROM transaction_types WHERE code = 'sol_payout' LIMIT 1");
                    $tstmt->execute();
                    $tt = $tstmt->fetch(PDO::FETCH_ASSOC);
                    if (is_array($tt) && !empty($tt['id'])) $txTypeId = $tt['id'];

                    // Insert transaction record
                    $txId = generateUuid();
                    $txRef = $pid;
                    $txStatus = 'completed';
                    $txType = 'sol_payout';
                    $ins = $db->prepare("INSERT INTO transactions (id, transaction_id, user_id, wallet_id, type, transaction_type_id, amount, currency, provider, reference_id, reference_type, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'HTG', 'system', ?, 'sol_payout', ?, NOW(), NOW())");
                    $transactionId = 'PAYOUT_' . time() . '_' . substr($txId,0,8);
                    $ins->execute([$txId, $transactionId, $recipientUserId, $walletId, $txType, $txTypeId, $amount, $txRef, $txStatus]);

                    // Mark payout paid
                    $stmt = $db->prepare("UPDATE sol_payouts SET status = 'paid', paid_date = NOW(), updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$pid]);

                    setFlashMessage('success', 'Payout processed and recorded.');
                } else {
                    setFlashMessage('error', 'Payout not found.');
                }
            }
        }

        // Edit payout (admin/manager)
        if (isset($_POST['edit_payout']) && ($userRole === 'admin' || $userRole === 'manager')) {
            $pid = $_POST['payout_id'] ?? null;
            $amount = isset($_POST['amount']) ? trim($_POST['amount']) : null;
            $scheduled = isset($_POST['scheduled_date']) ? trim($_POST['scheduled_date']) : null;
            if ($pid && $amount !== null) {
                $amount = floatval($amount);
                $stmt = $db->prepare("UPDATE sol_payouts SET amount = ?, scheduled_date = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$amount, $scheduled ?: null, $pid]);
                setFlashMessage('success', 'Payout updated.');
            } else {
                setFlashMessage('error', 'Amount is required to update payout.');
            }
        }

        // Mark payout as missed
        if (isset($_POST['mark_missed']) && ($userRole === 'admin' || $userRole === 'manager')) {
            $pid = $_POST['payout_id'] ?? null;
            if ($pid) {
                $stmt = $db->prepare("UPDATE sol_payouts SET status = 'missed', updated_at = NOW() WHERE id = ? AND status != 'missed'");
                $stmt->execute([$pid]);
                setFlashMessage('success', 'Payout marked as missed.');
            }
        }

        // Delete payout (admin only)
        if (isset($_POST['delete_payout']) && $userRole === 'admin') {
            $pid = $_POST['payout_id'] ?? null;
            if ($pid) {
                $stmt = $db->prepare("DELETE FROM sol_payouts WHERE id = ?");
                $stmt->execute([$pid]);
                setFlashMessage('success', 'Payout deleted.');
            }
        }

    // Manual payout registration (admin/manager)
        if (isset($_POST['manual_payout']) && ($userRole === 'admin' || $userRole === 'manager')) {
            $participantId = $_POST['participant_id'] ?? null;
            $amount = $_POST['amount'] ?? null;
            $date = $_POST['paid_date'] ?? null;
            if ($participantId && $amount) {
                $pid = generateUuid();
                    $stmt = $db->prepare("INSERT INTO sol_payouts (id, sol_group_id, participant_id, amount, currency, scheduled_date, paid_date, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'HTG', NOW(), ?, 'paid', NOW(), NOW())");
                    $stmt->execute([$pid, $groupId, $participantId, $amount, $date ?: date('Y-m-d')]);
                setFlashMessage('success', 'Manual payout recorded.');
            } else {
                setFlashMessage('error', 'Participant and amount are required for manual payout.');
            }
        }

        // redirect back to avoid resubmission
        redirect('?page=sol-payouts&id=' . urlencode($groupId));
    } catch (Exception $e) {
        error_log('SOL payouts action error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred processing the payout action.');
    }
}

// Fetch payout data
try {
    $db = getDbConnection();

    // Group details
    $gstmt = $db->prepare("SELECT * FROM sol_groups WHERE id = ? LIMIT 1");
    $gstmt->execute([$groupId]);
    $group = $gstmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) {
        setFlashMessage('error', 'SOL group not found.');
        redirect('?page=sol-groups');
    }

    // Payouts for the group (include participant payout position as cycle when available)
    $pstmt = $db->prepare("SELECT sp.*, par.payout_position AS cycle, u.full_name, u.profile_photo FROM sol_payouts sp LEFT JOIN sol_participants par ON sp.participant_id = par.id LEFT JOIN users u ON par.user_id = u.id WHERE sp.sol_group_id = ? ORDER BY sp.scheduled_date ASC, sp.created_at ASC");
    $pstmt->execute([$groupId]);
    $payouts = $pstmt->fetchAll(PDO::FETCH_ASSOC);

    // Members list
    $mstmt = $db->prepare("SELECT sp.id AS participant_id, sp.user_id, sp.payout_position, u.full_name, u.profile_photo FROM sol_participants sp INNER JOIN users u ON sp.user_id = u.id WHERE sp.sol_group_id = ? ORDER BY sp.payout_position ASC");
    $mstmt->execute([$groupId]);
    $members = $mstmt->fetchAll(PDO::FETCH_ASSOC);

    // Totals
    $pending = 0; $paid = 0;
    foreach ($payouts as $p) {
        if (($p['status'] ?? 'pending') === 'paid') $paid += floatval($p['amount'] ?? 0);
        else $pending += floatval($p['amount'] ?? 0);
    }
    $totals['pending'] = $pending;
    $totals['paid'] = $paid;

} catch (Exception $e) {
    error_log('SOL payouts fetch error: ' . $e->getMessage());
    $error = 'An error occurred while loading payouts.';
}

?>

<div class="main-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold">SOL Group Payouts</h1>
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
                        <div class="h6">Pending payouts total</div>
                        <div class="fw-bold fs-4"><?= number_format($totals['pending'], 2) ?> HTG</div>
                        <small class="text-muted">Paid total: <?= number_format($totals['paid'], 2) ?> HTG</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Scheduled Payouts</h5>
                <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#manualPayoutModal">
                        <i class="fas fa-plus-circle me-1"></i> Manual Payout
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($payouts)): ?>
                    <div class="p-4 text-center text-muted">No scheduled payouts.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Cycle</th>
                                    <th>Payout To (Member)</th>
                                    <th>Amount</th>
                                    <th>Scheduled Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payouts as $p): ?>
                                <tr>
                                    <td><?= intval($p['cycle'] ?? 0) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-secondary text-white rounded-circle me-3 d-flex align-items-center justify-content-center">
                                                <?= htmlspecialchars((string)substr(($p['full_name'] ?? ''), 0, 2)) ?>
                                            </div>
                                            <div>
                                                <?= htmlspecialchars((string)($p['full_name'] ?? '')) ?><br>
                                                <small class="text-muted">Participant: <?= htmlspecialchars((string)($p['participant_id'] ?? '')) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= number_format($p['amount'] ?? 0, 2) ?> HTG</td>
                                    <td><?= htmlspecialchars((string)($p['scheduled_date'] ?? '')) ?></td>
                                    <td>
                                        <?php $status = $p['status'] ?? 'pending';
                                            $cls = 'secondary';
                                            if ($status === 'pending') $cls = 'warning text-dark';
                                            if ($status === 'paid') $cls = 'success';
                                            if ($status === 'missed') $cls = 'danger';
                                        ?>
                                        <span class="badge bg-<?= $cls ?>"><?= htmlspecialchars((string)ucfirst($status)) ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($status !== 'paid'): ?>
                                                <form method="POST" style="display:inline-block">
                                                    <input type="hidden" name="payout_id" value="<?= htmlspecialchars((string)$p['id']) ?>">
                                                    <button type="submit" name="pay_payout" class="btn btn-outline-primary">Pay</button>
                                                </form>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editPayoutModal<?= htmlspecialchars((string)$p['id']) ?>">Edit</button>
                                            <?php if ($userRole === 'admin'): ?>
                                                <form method="POST" style="display:inline-block" onsubmit="return confirm('Delete this payout?');">
                                                    <input type="hidden" name="payout_id" value="<?= htmlspecialchars((string)$p['id']) ?>">
                                                    <button type="submit" name="delete_payout" class="btn btn-outline-danger">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Edit modal for payout -->
                                        <div class="modal fade" id="editPayoutModal<?= htmlspecialchars((string)$p['id']) ?>" tabindex="-1">
                                            <div class="modal-dialog modal-sm">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit Payout</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Amount (HTG)</label>
                                                                <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars((string)($p['amount'] ?? '')) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Scheduled Date</label>
                                                                <input type="date" name="scheduled_date" class="form-control" value="<?= htmlspecialchars((string)($p['scheduled_date'] ?? '')) ?>">
                                                            </div>
                                                            <input type="hidden" name="payout_id" value="<?= htmlspecialchars((string)$p['id']) ?>">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="edit_payout" class="btn btn-primary">Save</button>
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

        <!-- Manual payout modal -->
        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
        <div class="modal fade" id="manualPayoutModal" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Register Manual Payout</h5>
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
                                <input type="number" step="0.01" name="amount" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Paid date (optional)</label>
                                <input type="date" name="paid_date" class="form-control">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="manual_payout" class="btn btn-primary">Record Payout</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>