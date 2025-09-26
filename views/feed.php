<?php
// Unified Feed View
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

try { $db = getDbConnection(); } catch (Exception $e) { $db = null; }
$pageNum = isset($_GET['p']) ? max(1,(int)$_GET['p']) : 1;
$pageSize = 30; // overall combined limit per page
$itemsAll = $db ? fetchUnifiedFeed($db, 200) : [];
$totalItems = count($itemsAll);
$totalPages = max(1, (int)ceil($totalItems / $pageSize));
if ($pageNum > $totalPages) { $pageNum = $totalPages; }
$offset = ($pageNum - 1) * $pageSize;
$items = array_slice($itemsAll, $offset, $pageSize);
$pageTitle = 'Latest Activity Feed';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0"><i class="fas fa-stream me-2 text-primary"></i>Latest Activity</h2>
  </div>
  <?php if (!$db): ?>
    <div class="alert alert-danger">Database connection unavailable.</div>
  <?php endif; ?>
  <?php if (empty($items)): ?>
    <div class="text-center text-muted py-5">
      <i class="fas fa-inbox fa-2x mb-3"></i>
      <p class="mb-0 small">No recent public activity yet.</p>
    </div>
  <?php else: ?>
    <div class="list-group shadow-sm">
      <?php foreach ($items as $it): ?>
        <?php
          $badgeClass = 'secondary';
          switch($it['type']) {
            case 'sol_group': $icon='fa-users'; $badgeClass='info'; break;
            case 'loan_request': $icon='fa-hand-holding-usd'; $badgeClass='warning'; break;
            case 'campaign': $icon='fa-seedling'; $badgeClass='success'; break;
            case 'investment': $icon='fa-briefcase'; $badgeClass='primary'; break;
            default: $icon='fa-circle';
          }
        ?>
        <a href="<?= htmlspecialchars($it['url']) ?>" class="list-group-item list-group-item-action">
          <div class="d-flex w-100 justify-content-between">
            <h6 class="mb-1 fw-semibold">
              <span class="badge bg-<?= $badgeClass ?> me-2"><i class="fas <?= $icon ?> me-1"></i><?= htmlspecialchars($it['badge']) ?></span>
              <?= htmlspecialchars(mb_strimwidth($it['title'],0,80,'…')) ?>
            </h6>
            <small class="text-muted"><?= htmlspecialchars(timeAgo($it['created_at'])) ?></small>
          </div>
          <div class="small text-muted d-flex flex-wrap gap-3">
            <?php if (isset($it['amount'])): ?><span><i class="fas fa-coins me-1"></i><?= number_format((float)$it['amount'],2) ?> HTG</span><?php endif; ?>
            <?php if (!empty($it['status'])): ?><span class="text-capitalize"><i class="fas fa-info-circle me-1"></i><?= htmlspecialchars($it['status']) ?></span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($totalPages > 1): ?>
    <nav class="mt-3" aria-label="Feed pagination">
      <ul class="pagination pagination-sm justify-content-center">
        <li class="page-item <?= $pageNum<=1?'disabled':'' ?>">
          <a class="page-link" href="?page=feed&p=<?= $pageNum-1 ?>" tabindex="-1">Prev</a>
        </li>
        <?php for($i=1;$i<=$totalPages && $i<=10;$i++): ?>
          <li class="page-item <?= $i===$pageNum?'active':'' ?>"><a class="page-link" href="?page=feed&p=<?= $i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $pageNum>=$totalPages?'disabled':'' ?>">
          <a class="page-link" href="?page=feed&p=<?= $pageNum+1 ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</div>
<!-- Future Enhancements:
1. Infinite scroll via AJAX endpoint (e.g., /api/feed?p=N) returning JSON.
2. Personalization: show groups user participates in first; hide already joined items.
3. Caching layer: store aggregated list in transient/redis for 60s to reduce DB load.
4. Activity types expansion: donations, repayments, payout events, KYC verifications.
5. Filtering UI: tabs or pill filters (All | SOL | Loans | Campaigns | Investments).
6. Real-time updates: WebSocket/pusher integration to prepend new items.
7. Progress metrics: campaigns (sum donations), investments (amount_raised), loans (repayment progress).
-->
