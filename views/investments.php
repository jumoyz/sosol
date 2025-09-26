<?php 
/**
 * Investments page - Listing of public/open investment opportunities.
 */
$pageTitle = "Investments";
$pageDescription = "Browse investment opportunities";
$activeNav = "investments";

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/flash-messages.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { redirect('?page=login'); }

$filters = [
  'sector' => trim($_GET['sector'] ?? ''),
  'q' => trim($_GET['q'] ?? ''),
  'status' => trim($_GET['status'] ?? 'open'),
  'sort' => trim($_GET['sort'] ?? 'newest'),
];

$pageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 12; // cards per page
$offset = ($pageNum - 1) * $perPage;

// Whitelist sorting options
$sortMap = [
  'newest' => 'i.created_at DESC',
  'goal_desc' => 'i.funding_goal DESC',
  'progress_desc' => 'progress_pct DESC',
  'end_soon' => 'i.end_date ASC',
];
$orderClause = $sortMap[$filters['sort']] ?? $sortMap['newest'];

$investments = [];
$error = null;
$sectors = [];

try {
    $db = getDbConnection();
    // Distinct sectors
    $sectorStmt = $db->query("SELECT DISTINCT sector FROM investments WHERE status IN ('open','funded') ORDER BY sector ASC");
    $sectors = $sectorStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

  $where = ["visibility = 'public'"];
    $params = [];

    if ($filters['status'] !== '') { $where[] = 'status = ?'; $params[] = $filters['status']; }
    if ($filters['sector'] !== '') { $where[] = 'sector = ?'; $params[] = $filters['sector']; }
    if ($filters['q'] !== '') {
        $where[] = '(title LIKE ? OR description LIKE ?)';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }

    // Total count for pagination
    $countSql = "SELECT COUNT(*) FROM investments i WHERE " . implode(' AND ', $where);
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($pageNum > $totalPages) { $pageNum = $totalPages; $offset = ($pageNum - 1) * $perPage; }

    $sql = "SELECT i.*, CASE WHEN funding_goal > 0 THEN ROUND((amount_raised / funding_goal)*100,2) ELSE 0 END as progress_pct,
               (SELECT COUNT(*) FROM investment_interests ii WHERE ii.investment_id = i.id) as interest_count
            FROM investments i WHERE " . implode(' AND ', $where) . " ORDER BY $orderClause LIMIT $perPage OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $investments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary metrics (open only + funded)
    $summary = [
        'open_count' => 0,
        'funded_count' => 0,
        'total_goal' => 0.0,
        'total_raised' => 0.0,
    ];
    if ($totalRows > 0) {
        $sumSql = "SELECT 
            SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) open_count,
            SUM(CASE WHEN status='funded' THEN 1 ELSE 0 END) funded_count,
            SUM(funding_goal) total_goal,
            SUM(amount_raised) total_raised
            FROM investments i WHERE " . implode(' AND ', $where);
        $sumStmt = $db->prepare($sumSql);
        $sumStmt->execute($params);
        $summary = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: $summary;
    }
} catch (PDOException $e) {
    error_log('Investments listing error: ' . $e->getMessage());
    $error = 'Unable to load investments at this time.';
}
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Investments</h2>
    <a href="?page=investment-create" class="btn btn-sm btn-primary"><i class="fas fa-plus-circle me-1"></i>Create Opportunity</a>
  </div>
  <?php if (!empty($summary) && $totalRows>0): ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body py-2 text-center">
          <div class="small text-muted">Open</div>
          <div class="fw-semibold"><?= (int)$summary['open_count'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body py-2 text-center">
          <div class="small text-muted">Funded</div>
          <div class="fw-semibold text-primary"><?= (int)$summary['funded_count'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body py-2 text-center">
          <div class="small text-muted">Total Goal</div>
          <div class="fw-semibold"><?= number_format((float)$summary['total_goal'],0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body py-2 text-center">
          <div class="small text-muted">Raised</div>
          <div class="fw-semibold text-success"><?= number_format((float)$summary['total_raised'],0) ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="GET" class="card card-body shadow-sm mb-4">
    <input type="hidden" name="page" value="investments" />
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label mb-1">Search</label>
        <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" class="form-control" placeholder="Title or description" />
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1">Sector</label>
        <select name="sector" class="form-select">
          <option value="">All Sectors</option>
          <?php foreach ($sectors as $sec): ?>
            <option value="<?= htmlspecialchars($sec) ?>" <?= $filters['sector']===$sec?'selected':'' ?>><?= htmlspecialchars(ucfirst($sec)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['open'=>'Open','funded'=>'Funded','closed'=>'Closed'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $filters['status']===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label mb-1">Sort</label>
        <select name="sort" class="form-select">
          <option value="newest" <?= $filters['sort']==='newest'?'selected':'' ?>>Newest</option>
          <option value="progress_desc" <?= $filters['sort']==='progress_desc'?'selected':'' ?>>Progress (High)</option>
          <option value="goal_desc" <?= $filters['sort']==='goal_desc'?'selected':'' ?>>Goal (High)</option>
          <option value="end_soon" <?= $filters['sort']==='end_soon'?'selected':'' ?>>Ending Soon</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-outline-primary"><i class="fas fa-filter me-1"></i>Filter</button>
      </div>
      <div class="col-md-2 d-grid">
        <a href="?page=investments" class="btn btn-outline-secondary"><i class="fas fa-undo me-1"></i>Reset</a>
      </div>
    </div>
  </form>
  <?php if (empty($investments)): ?>
    <div class="text-center py-5 text-muted">
      <i class="fas fa-folder-open fa-2x mb-3"></i>
      <p class="mb-0">No investment opportunities found.</p>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($investments as $inv): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column position-relative">
              <?php if ($inv['status']==='funded'): ?>
                <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-primary" style="z-index:2;">Funded</span>
              <?php elseif(!empty($inv['end_date']) && strtotime($inv['end_date']) <= strtotime('+7 days')): ?>
                <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-warning text-dark" style="z-index:2;">Ending Soon</span>
              <?php endif; ?>
              <div class="d-flex justify-content-between mb-2 small">
                <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($inv['sector'])) ?></span>
                <span class="badge bg-<?= $inv['status']==='open'?'success':($inv['status']==='funded'?'primary':'dark') ?>"><?= ucfirst($inv['status']) ?></span>
              </div>
              <h5 class="fw-semibold mb-2"><a class="text-decoration-none" href="?page=investment-details&id=<?= urlencode($inv['id']) ?>"><?= htmlspecialchars($inv['title']) ?></a></h5>
              <p class="text-muted small flex-grow-1 mb-3"><?= htmlspecialchars(mb_strimwidth($inv['description'] ?? '',0,120,'…')) ?></p>
              <div class="mb-2 small">Goal: <strong><?= number_format($inv['funding_goal'],2) ?></strong></div>
              <?php 
                $pct = (float)$inv['progress_pct'];
                $barClass = 'bg-danger';
                if ($pct >= 66) { $barClass='bg-success'; } elseif ($pct >= 33) { $barClass='bg-warning'; }
              ?>
              <div class="progress mb-2" style="height:6px;">
                <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width: <?= min(100,$pct) ?>%"></div>
              </div>
              <div class="d-flex justify-content-between small text-muted mb-3">
                <span><?= number_format($inv['amount_raised'],2) ?> raised</span>
                <span><?= $inv['progress_pct'] ?>%</span>
              </div>
              <div class="d-flex justify-content-between small text-muted">
                <span><i class="fas fa-users me-1"></i><?= (int)$inv['interest_count'] ?> interested</span>
                <?php if (!empty($inv['end_date'])): ?>
                  <span><i class="far fa-clock me-1"></i><?= htmlspecialchars($inv['end_date']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination pagination-sm justify-content-center">
          <?php 
            $baseParams = $_GET; unset($baseParams['p']);
            $queryBase = http_build_query(array_merge(['page'=>'investments'],$baseParams));
          ?>
          <li class="page-item <?= $pageNum<=1?'disabled':'' ?>">
            <a class="page-link" href="?<?= $queryBase . '&p=' . max(1,$pageNum-1) ?>" aria-label="Previous">&laquo;</a>
          </li>
          <?php for ($p=1;$p<=$totalPages && $p<=10;$p++): ?>
            <li class="page-item <?= $p===$pageNum?'active':'' ?>"><a class="page-link" href="?<?= $queryBase . '&p=' . $p ?>"><?= $p ?></a></li>
          <?php endfor; ?>
          <li class="page-item <?= $pageNum>=$totalPages?'disabled':'' ?>">
            <a class="page-link" href="?<?= $queryBase . '&p=' . min($totalPages,$pageNum+1) ?>" aria-label="Next">&raquo;</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
<!-- TODO: Add recommendation panel (sectors, personalized) in later iteration -->
