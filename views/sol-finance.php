<?php
// SOL Finance Management Page (Phase 1 - Read Only + Pending Approvals)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/flash-messages.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { redirect('?page=login'); }
$groupId = $_GET['id'] ?? null; if (!$groupId) { setFlashMessage('error','Missing group id'); redirect('?page=sol-groups'); }

try { $db = getDbConnection(); } catch (Throwable $e) { setFlashMessage('error','DB error'); redirect('?page=sol-groups'); }

// Basic role verification
$roleStmt = $db->prepare("SELECT sp.role, sg.name, sg.contribution, sg.current_cycle, sg.total_cycles, sg.frequency, sg.created_at FROM sol_groups sg INNER JOIN sol_participants sp ON sg.id=sp.sol_group_id WHERE sg.id=? AND sp.user_id=? LIMIT 1");
$roleStmt->execute([$groupId,$userId]);
$group = $roleStmt->fetch(PDO::FETCH_ASSOC);
if (!$group || !in_array($group['role'], ['admin','manager'])) { setFlashMessage('error','Access denied'); redirect('?page=sol-details&id='.$groupId); }

// Pending contributions
$pending = $db->prepare("SELECT sc.id, sc.amount, sc.cycle_number, sc.created_at, u.full_name FROM sol_contributions sc INNER JOIN sol_participants sp ON sc.participant_id=sp.id INNER JOIN users u ON sp.user_id=u.id WHERE sp.sol_group_id=? AND sc.status='pending' ORDER BY sc.created_at ASC");
$pending->execute([$groupId]);
$pendingRows = $pending->fetchAll(PDO::FETCH_ASSOC);

// Recent payouts (placeholder if payout events table exists)
$payoutEvents = [];
if ($db->query("SHOW TABLES LIKE 'sol_payout_events'")->rowCount() === 1) {
    $pe = $db->prepare("SELECT * FROM sol_payout_events WHERE sol_group_id=? ORDER BY created_at DESC LIMIT 10");
    $pe->execute([$groupId]);
    $payoutEvents = $pe->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'SOL Finance Management';
?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h2 class="h4 mb-0">Finance Management - <?= htmlspecialchars($group['name']) ?></h2>
      <small class="text-muted">Cycle <?= (int)$group['current_cycle'] ?> of <?= (int)$group['total_cycles'] ?> • Contribution <?= number_format($group['contribution']) ?> HTG</small>
    </div>
    <div>
      <a href="?page=sol-manage&id=<?= $groupId ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
  </div>

  <?php if (isset($_SESSION['flash_type'])): ?>
    <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_SESSION['flash_message'] ?? '') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash_type'], $_SESSION['flash_message']); endif; ?>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Pending Contributions</h5>
          <span class="badge bg-warning"><?= count($pendingRows) ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Member</th>
                  <th>Amount</th>
                  <th>Cycle</th>
                  <th>Requested</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($pendingRows): foreach($pendingRows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['full_name']) ?></td>
                  <td><?= number_format($row['amount']) ?> HTG</td>
                  <td><?= (int)$row['cycle_number'] ?></td>
                  <td><small class="text-muted"><?= timeAgo($row['created_at']) ?></small></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No pending contributions</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="p-2 border-top text-end">
            <a href="#" class="btn btn-sm btn-outline-primary disabled" title="Bulk approval coming soon">Bulk Approve (Soon)</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Recent Payout Events</h5>
          <span class="badge bg-info"><?= count($payoutEvents) ?></span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light"><tr><th>Cycle</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
              <?php if ($payoutEvents): foreach($payoutEvents as $ev): ?>
                <tr>
                  <td><?= (int)$ev['cycle_number'] ?></td>
                  <td><?= number_format($ev['amount']) ?> HTG</td>
                  <td><?= htmlspecialchars($ev['payout_method']) ?></td>
                  <?php
                    $cls = 'info';
                    if ($ev['status'] === 'completed') $cls = 'success';
                    elseif ($ev['status'] === 'failed') $cls = 'danger';
                    elseif ($ev['status'] === 'processing') $cls = 'warning';
                    elseif ($ev['status'] === 'reversed') $cls = 'secondary';
                  ?>
                  <td><span class="badge bg-<?= $cls ?>"><?= htmlspecialchars($ev['status']) ?></span></td>
                  <td><small class="text-muted"><?= date('M j, Y', strtotime($ev['created_at'])) ?></small></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No payout events yet</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="p-2 border-top text-end">
            <a href="#" class="btn btn-sm btn-outline-secondary disabled">Export (Soon)</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header bg-white"><h5 class="mb-0">Upcoming Features Roadmap</h5></div>
        <div class="card-body small">
          <ul class="mb-0">
            <li>Bulk approve pending contributions</li>
            <li>Manual payout initiation & completion with audit</li>
            <li>Export CSV / JSON</li>
            <li>Cycle readiness indicator</li>
            <li>Reconciliation & overrides</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
