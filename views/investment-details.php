<?php
$pageTitle = 'Investment Details';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/flash-messages.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { redirect('?page=login'); }

$investmentId = $_GET['id'] ?? null;
if (!$investmentId) { setFlashMessage('error','Missing investment ID.'); redirect('?page=investments'); }

$errors = [];
$pledgeSuccess = false;
$investment = null;
$interests = [];
$isOwner = false;
$ownerActionErrors = [];

// Allowed status transitions (simple)
$allowedStatuses = ['draft','open','funded','closed','cancelled'];

try {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT i.*, u.full_name AS owner_name, 
        CASE WHEN funding_goal > 0 THEN ROUND((amount_raised / funding_goal)*100,2) ELSE 0 END as progress_pct
        FROM investments i INNER JOIN users u ON i.user_id = u.id WHERE i.id = ? LIMIT 1");
    $stmt->execute([$investmentId]);
    $investment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$investment) { setFlashMessage('error','Investment not found.'); redirect('?page=investments'); }
    $isOwner = ($investment['user_id'] === $userId);

  // Handle owner management actions (status change, update, export)
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner && isset($_POST['__owner_action'])) {
    $postedToken = $_POST['csrf_token'] ?? null;
    if (!csrf_validate($postedToken)) {
      setFlashMessage('error','Security token mismatch.');
      redirect('?page=investment-details&id=' . urlencode($investmentId));
    }
    $action = $_POST['__owner_action'];
    try {
      if ($action === 'update_basic') {
        $newTitle = trim($_POST['title'] ?? '');
        $newGoal = (float)($_POST['funding_goal'] ?? 0);
        $newEquity = ($_POST['equity_offered'] === '' ? null : (float)$_POST['equity_offered']);
        $newEnd   = trim($_POST['end_date'] ?? '');
        $newVisibility = in_array($_POST['visibility'] ?? '', ['public','private'], true) ? $_POST['visibility'] : $investment['visibility'];
        if ($newTitle === '' || $newGoal <= 0) {
          $ownerActionErrors[] = 'Title and positive funding goal are required.';
        }
        if (!$ownerActionErrors) {
          $upd = $db->prepare("UPDATE investments SET title=?, funding_goal=?, equity_offered=?, end_date = ?, visibility=?, updated_at=NOW() WHERE id=? AND user_id=? LIMIT 1");
          $upd->execute([
            $newTitle,
            $newGoal,
            $newEquity,
            ($newEnd !== '' ? $newEnd : null),
            $newVisibility,
            $investmentId,
            $userId,
          ]);
          logActivity($db, $userId, 'investment_updated', $investmentId, []);
          setFlashMessage('success','Investment updated.');
          redirect('?page=investment-details&id=' . urlencode($investmentId));
        }
      } elseif ($action === 'change_status') {
        $newStatus = $_POST['status'] ?? '';
        if (!in_array($newStatus, $allowedStatuses, true)) {
          $ownerActionErrors[] = 'Invalid status.';
        } else {
          // Basic rules: cannot revert funded to open, cannot change cancelled
          if ($investment['status'] === 'cancelled') {
            $ownerActionErrors[] = 'Cancelled investments cannot change status.';
          } elseif ($investment['status'] === 'funded' && $newStatus === 'open') {
            $ownerActionErrors[] = 'Cannot reopen a funded investment.';
          }
        }
        if (!$ownerActionErrors && $newStatus !== $investment['status']) {
          $stUpd = $db->prepare("UPDATE investments SET status=?, updated_at=NOW() WHERE id=? AND user_id=? LIMIT 1");
          $stUpd->execute([$newStatus, $investmentId, $userId]);
          logActivity($db, $userId, 'investment_status_changed', $investmentId, ['from'=>$investment['status'],'to'=>$newStatus]);
          setFlashMessage('success','Status updated.');
          redirect('?page=investment-details&id=' . urlencode($investmentId));
        }
      }
    } catch (PDOException $oe) {
      error_log('Owner action error: ' . $oe->getMessage());
      setFlashMessage('error','Action failed due to database error.');
      redirect('?page=investment-details&id=' . urlencode($investmentId));
    }
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pledge_amount']) && !$isOwner) {
        $amount = (float)($_POST['pledge_amount'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if ($amount <= 0) { $errors[] = 'Pledge amount must be greater than 0.'; }
        // Ensure not exceeding remaining need if open
        $remaining = max(0, ($investment['funding_goal'] - $investment['amount_raised']));
        if ($remaining > 0 && $amount > $remaining) {
            $errors[] = 'Amount exceeds remaining funding need (' . number_format($remaining,2) . ').';
        }
        if (empty($errors)) {
            try {
                $iid = generateUuid();
                $ins = $db->prepare("INSERT INTO investment_interests (id, investment_id, investor_id, amount_pledged, message, status, created_at, updated_at) VALUES (?,?,?,?,?,'interested',NOW(),NOW())");
                $ins->execute([$iid, $investmentId, $userId, $amount, $message !== '' ? $message : null]);

                // Update amount_raised (simple additive model; future: separate state until confirmed)
                $upd = $db->prepare("UPDATE investments SET amount_raised = amount_raised + ? , status = CASE WHEN amount_raised + ? >= funding_goal THEN 'funded' ELSE status END, updated_at = NOW() WHERE id = ?");
                $upd->execute([$amount, $amount, $investmentId]);

                // Refresh investment after update
                $stmt->execute([$investmentId]);
                $investment = $stmt->fetch(PDO::FETCH_ASSOC);

                // Activity / notification stub
                try {
                    logActivity($db, $userId, 'investment_interest', $investmentId, ['amount'=>$amount]);
                    if ($investment['user_id']) {
                        logActivity($db, $investment['user_id'], 'investment_received_interest', $investmentId, ['amount'=>$amount,'from'=>$userId]);
                    }
                    if ($investment['status'] === 'funded') {
                        logActivity($db, $investment['user_id'], 'investment_funded', $investmentId, []);
                    }
                } catch (Exception $lae) { error_log('Activity log error: ' . $lae->getMessage()); }

                setFlashMessage('success','Your interest was recorded.');
                $pledgeSuccess = true;
                redirect('?page=investment-details&id=' . urlencode($investmentId));
            } catch (PDOException $pe) {
                error_log('Pledge error: ' . $pe->getMessage());
                $errors[] = 'Database error recording interest.';
            }
        }
    }

    // Load interests (limited)
    $interestStmt = $db->prepare("SELECT ii.*, u.full_name AS investor_name FROM investment_interests ii INNER JOIN users u ON ii.investor_id = u.id WHERE investment_id = ? ORDER BY created_at DESC LIMIT 50");
    $interestStmt->execute([$investmentId]);
    $interests = $interestStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Investment details error: ' . $e->getMessage());
    $errors[] = 'Error loading investment details.';
}
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0"><i class="fas fa-briefcase me-2 text-primary"></i><?= htmlspecialchars($investment['title'] ?? 'Investment') ?></h2>
    <a href="?page=investments" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
  </div>
  <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $er): ?><li><?= htmlspecialchars($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <span class="badge bg-secondary"><i class="fas fa-tag me-1"></i><?= htmlspecialchars(ucfirst($investment['sector'])) ?></span>
            <span class="badge bg-<?= $investment['status']==='open'?'success':($investment['status']==='funded'?'primary':'dark') ?>">
              <i class="fas fa-circle me-1 small"></i><?= ucfirst($investment['status']) ?>
            </span>
            <span class="badge bg-info"><i class="fas fa-user-shield me-1"></i><?= htmlspecialchars($investment['owner_name']) ?></span>
            <?php if (!empty($investment['end_date'])): ?>
              <span class="badge bg-light text-dark border"><i class="far fa-clock me-1"></i><?= htmlspecialchars($investment['end_date']) ?></span>
            <?php endif; ?>
          </div>
          <p class="mb-3 text-muted small"><?= nl2br(htmlspecialchars($investment['description'] ?? 'No description provided.')) ?></p>
          <?php if (!empty($investment['pitch_deck'])): ?>
            <p class="mb-2"><i class="fas fa-file-pdf me-1 text-danger"></i><a href="<?= htmlspecialchars($investment['pitch_deck']) ?>" target="_blank" rel="noopener">Pitch Deck</a></p>
          <?php endif; ?>
          <?php if (!empty($investment['video_url'])): ?>
            <p class="mb-2"><i class="fas fa-video me-1 text-warning"></i><a href="<?= htmlspecialchars($investment['video_url']) ?>" target="_blank" rel="noopener">Video Pitch</a></p>
          <?php endif; ?>
          <?php $pct=(float)$investment['progress_pct']; $barClass='bg-danger'; if($pct>=66){$barClass='bg-success';} elseif($pct>=33){$barClass='bg-warning';} ?>
          <div class="mb-3">
            <div class="small text-muted mb-1">Funding Progress</div>
            <div class="progress" style="height:10px;">
              <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width: <?= min(100,$pct) ?>%"></div>
            </div>
            <div class="d-flex flex-wrap gap-3 small mt-2 text-muted">
              <span><strong><?= number_format($investment['amount_raised'],2) ?></strong> raised of <?= number_format($investment['funding_goal'],2) ?> HTG</span>
              <span><?= $pct ?>%</span>
              <?php $remaining=max(0,$investment['funding_goal']-$investment['amount_raised']); ?>
              <span><?= $remaining>0? number_format($remaining,2). ' HTG remaining' : 'Fully funded' ?></span>
            </div>
          </div>
          <?php if ($investment['status']==='funded'): ?>
            <div class="alert alert-success py-2 small mb-3"><i class="fas fa-check-circle me-1"></i>This round has reached its funding goal.</div>
          <?php endif; ?>
          <?php if (!empty($investment['equity_offered'])): ?>
            <p class="small mb-1"><strong>Equity / Return Offered:</strong> <?= htmlspecialchars($investment['equity_offered']) ?>%</p>
          <?php endif; ?>
          <?php if (!empty($investment['end_date'])): ?>
            <p class="small text-muted mb-0"><i class="far fa-clock me-1"></i>Ends: <?= htmlspecialchars($investment['end_date']) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
          <h5 class="mb-0 small text-uppercase text-muted">Recent Interests</h5>
          <span class="badge bg-light text-dark border"><?= count($interests) ?></span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($interests)): ?>
            <div class="p-4 text-center text-muted small">No expressions of interest yet.</div>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($interests as $ii): ?>
                <div class="list-group-item">
                  <div class="d-flex justify-content-between small">
                    <span><i class="fas fa-user-circle me-1 text-primary"></i><?= htmlspecialchars($ii['investor_name']) ?></span>
                    <span class="text-muted"><?= htmlspecialchars(substr($ii['created_at'],0,10)) ?></span>
                  </div>
                  <div class="mt-1 small">
                    <strong><?= number_format($ii['amount_pledged'],2) ?> HTG</strong>
                    <?php if (!empty($ii['message'])): ?>
                      <div class="text-muted fst-italic mt-1">“<?= htmlspecialchars(mb_strimwidth($ii['message'],0,160,'…')) ?>”</div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <?php if ($isOwner): ?>
        <div class="card shadow-sm border-0 mb-4">
          <div class="card-body">
            <h5 class="fw-semibold mb-3"><i class="fas fa-cogs me-2 text-secondary"></i>Owner Tools</h5>
            <?php if (!empty($ownerActionErrors)): ?>
              <div class="alert alert-danger small py-2 mb-3"><ul class="mb-0"><?php foreach($ownerActionErrors as $oer): ?><li><?= htmlspecialchars($oer) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="POST" class="mb-3">
              <input type="hidden" name="__owner_action" value="change_status" />
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
              <div class="input-group input-group-sm mb-2">
                <label class="input-group-text">Status</label>
                <select name="status" class="form-select form-select-sm">
                  <?php foreach($allowedStatuses as $st): ?>
                    <option value="<?= $st ?>" <?= $investment['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary">Update</button>
              </div>
            </form>
            <details class="mb-3">
              <summary class="small fw-semibold mb-2">Edit Basic Details</summary>
              <form method="POST" class="mt-2">
                <input type="hidden" name="__owner_action" value="update_basic" />
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
                <div class="mb-2">
                  <label class="form-label small mb-1">Title</label>
                  <input type="text" name="title" value="<?= htmlspecialchars($investment['title']) ?>" class="form-control form-control-sm" required />
                </div>
                <div class="mb-2">
                  <label class="form-label small mb-1">Funding Goal (HTG)</label>
                  <input type="number" step="0.01" name="funding_goal" value="<?= htmlspecialchars($investment['funding_goal']) ?>" class="form-control form-control-sm" required />
                </div>
                <div class="mb-2">
                  <label class="form-label small mb-1">Equity (optional %)</label>
                  <input type="number" step="0.01" name="equity_offered" value="<?= htmlspecialchars($investment['equity_offered'] ?? '') ?>" class="form-control form-control-sm" />
                </div>
                <div class="mb-2">
                  <label class="form-label small mb-1">End Date</label>
                  <input type="date" name="end_date" value="<?= htmlspecialchars($investment['end_date'] ?? '') ?>" class="form-control form-control-sm" />
                </div>
                <div class="mb-3">
                  <label class="form-label small mb-1">Visibility</label>
                  <select name="visibility" class="form-select form-select-sm">
                    <option value="public" <?= $investment['visibility']==='public'?'selected':'' ?>>Public</option>
                    <option value="private" <?= $investment['visibility']==='private'?'selected':'' ?>>Private</option>
                  </select>
                </div>
                <button class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
              </form>
            </details>
            <!-- NOTE: CSV export of investor interests moved to dedicated action script at actions/investment-export-csv.php
                 to avoid premature output before headers and keep controller logic slim. -->
            <form method="POST" action="actions/investment-export-csv.php" class="mt-2">
              <input type="hidden" name="investment_id" value="<?= htmlspecialchars($investmentId) ?>" />
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>" />
              <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                <i class="fas fa-file-export me-1"></i>Export Investors CSV
              </button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <div class="card shadow-sm border-0 mb-4">
          <div class="card-body">
            <h5 class="fw-semibold mb-3"><i class="fas fa-hand-holding-usd me-2 text-success"></i>Express Interest</h5>
            <?php if ($investment['status'] !== 'open'): ?>
              <div class="alert alert-warning small mb-3"><i class="fas fa-lock me-1"></i>This round is not accepting new interests.</div>
            <?php else: ?>
              <?php $remaining = max(0,$investment['funding_goal']-$investment['amount_raised']); ?>
              <p class="small text-muted mb-2">Remaining funding need: <strong><?= number_format($remaining,2) ?> HTG</strong></p>
              <form method="POST">
                <div class="mb-3">
                  <label class="form-label small">Amount (HTG)</label>
                  <input type="number" min="1" step="0.01" name="pledge_amount" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label small">Message (optional)</label>
                  <textarea name="message" rows="3" class="form-control" placeholder="Add a short note (optional)"></textarea>
                </div>
                <button class="btn btn-primary w-100"><i class="fas fa-paper-plane me-1"></i>Submit Interest</button>
              </form>
            <?php endif; ?>
            <p class="text-muted small mt-3 mb-0">Funds are not moved yet. This is an expression of interest. <!-- TODO: Escrow integration here later. --></p>
          </div>
        </div>
      <?php endif; ?>
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h6 class="fw-semibold mb-2"><i class="fas fa-info-circle me-1 text-primary"></i>About This Round</h6>
          <ul class="small text-muted list-unstyled mb-0">
            <li><strong>Goal:</strong> <?= number_format($investment['funding_goal'],2) ?> HTG</li>
            <li><strong>Raised:</strong> <?= number_format($investment['amount_raised'],2) ?> HTG</li>
            <li><strong>Progress:</strong> <?= $pct ?>%</li>
            <?php if (!empty($investment['equity_offered'])): ?><li><strong>Equity:</strong> <?= htmlspecialchars($investment['equity_offered']) ?>%</li><?php endif; ?>
            <li><strong>Status:</strong> <?= ucfirst($investment['status']) ?></li>
            <li><strong>Visibility:</strong> <?= ucfirst($investment['visibility']) ?></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- TODO: Add sector-based recommendations, related opportunities, and investor chat integration -->
