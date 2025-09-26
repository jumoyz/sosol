<?php
// Ti Kanè view (DB-backed)
// This view uses server endpoints in /actions to persist and manage Ti Kanè accounts.
// Start the session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once __DIR__ . '/../includes/flash-messages.php';
// Include files if not already included in index.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = "Ti Kanè Payments";
$pageDescription = "View and manage your Ti Kanè payment schedule and history.";

// Require a logged-in user
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    redirect('?page=login');
} 

// Pagination inputs
$pageNum = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
$perPage = isset($_GET['per_page']) ? max(5, min(200, (int)$_GET['per_page'])) : 20;
$offset = ($pageNum - 1) * $perPage;

// Fetch payments and total count for this user (paginated)
try {
    $db = getDbConnection();
    $countStmt = $db->prepare('SELECT COUNT(*) FROM ti_kane_payments p JOIN ti_kane_accounts a ON p.account_id = a.id WHERE a.user_id = ?');
    $countStmt->execute([$userId]);
    $totalCount = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare('SELECT p.*, a.type AS account_type, a.amount AS base_amount, a.start_date, a.end_date, a.id AS account_id FROM ti_kane_payments p JOIN ti_kane_accounts a ON p.account_id = a.id WHERE a.user_id = ? ORDER BY p.due_date DESC LIMIT ? OFFSET ?');
    // PDO requires integer types for LIMIT/OFFSET bound params on some drivers, cast explicitly
    $stmt->bindValue(1, $userId);
    $stmt->bindValue(2, (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payments = [];
    $totalCount = 0;
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

?>
<div class="row">
    <div class="col-12 mb-3">
        <h3>Ti Kanè — Payments</h3>
        <p class="text-muted">History of payments and cash-out for matured Ti Kanè accounts.</p>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3 align-items-center">
                    <div class="d-flex align-items-center">
                        <button id="exportCsv" class="btn btn-sm btn-outline-primary me-2">Export CSV</button>
                        <label class="mb-0 me-2 small text-muted">Per page</label>
                        <select id="perPageSel" class="form-select form-select-sm me-2" style="width:auto; display:inline-block;">
                            <?php foreach ([10,20,50,100] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $perPage == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="ms-2 small text-muted">Showing <?= min($totalCount, $offset+1) ?> - <?= min($totalCount, $offset + count($payments)) ?> of <?= $totalCount ?></div>
                    </div>
                    <div>
                        <button id="withdrawBtn" class="btn btn-sm btn-success">Withdraw matured</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Account</th>
                                <th>Due date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $i => $p): 
                            // Provide safe defaults to avoid passing null into htmlspecialchars (PHP 8.1+ deprecation)
                            $acctLabel = (string)(($p['account_type'] ?? '') . ' / ' . ($p['base_amount'] ?? '') . ' / ' . ($p['start_date'] ?? '') . ' - ' . ($p['end_date'] ?? ''));
                            $dueDate = (string)($p['due_date'] ?? '');
                            $amountDue = number_format((float)($p['amount_due'] ?? 0), 2);
                            $status = (string)($p['status'] ?? '');
                            $paymentDate = (string)($p['payment_date'] ?? '');
                        ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><?= htmlspecialchars($acctLabel) ?></td>
                                <td><?= htmlspecialchars($dueDate) ?></td>
                                <td><?= $amountDue ?></td>
                                <td><?= htmlspecialchars($status) ?></td>
                                <td><?= htmlspecialchars($paymentDate) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- pagination controls -->
                <div class="mt-3 d-flex justify-content-between align-items-center">
                    <div>
                        <nav aria-label="Payments pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                    $totalPages = max(1, (int)ceil($totalCount / $perPage));
                                    $prev = max(1, $pageNum - 1);
                                    $next = min($totalPages, $pageNum + 1);
                                ?>
                                <li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?page=ti-kane-payments&pg=<?= $prev ?>&per_page=<?= $perPage ?>">&laquo;</a></li>
                                <?php
                                    $startPage = max(1, $pageNum - 2);
                                    $endPage = min($totalPages, $pageNum + 2);
                                    for ($p = $startPage; $p <= $endPage; $p++):
                                ?>
                                    <li class="page-item <?= $p == $pageNum ? 'active' : '' ?>"><a class="page-link" href="?page=ti-kane-payments&pg=<?= $p ?>&per_page=<?= $perPage ?>"><?= $p ?></a></li>
                                <?php endfor; ?>
                                <li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="?page=ti-kane-payments&pg=<?= $next ?>&per_page=<?= $perPage ?>">&raquo;</a></li>
                            </ul>
                        </nav>
                    </div>
                    <div class="small text-muted">Page <?= $pageNum ?> of <?= $totalPages ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const payments = <?= json_encode($payments, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
document.getElementById('exportCsv').addEventListener('click', function(){
    // Export only the currently displayed page rows
    let csv = 'id,account_id,due_date,amount_due,status,payment_date\n';
    payments.forEach(p => { csv += [p.id, p.account_id, p.due_date, p.amount_due, p.status, p.payment_date].map(v=> '"'+(v||'')+'"').join(',') + '\n'; });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'ti-kane-payments-page-<?= $pageNum ?>.csv'; document.body.appendChild(a); a.click(); a.remove();
});

// per-page selector handler: reload with selected per_page
document.getElementById('perPageSel').addEventListener('change', function(){
    const per = Number(this.value) || <?= $perPage ?>;
    const params = new URLSearchParams(window.location.search);
    params.set('per_page', per);
    params.set('pg', 1);
    window.location.search = params.toString();
});

document.getElementById('withdrawBtn').addEventListener('click', async function(){
    if (!confirm('Withdraw matured Ti Kanè payouts to your wallet?')) return;
    try {
        const res = await fetch('actions/ti-kane-withdraw.php', { method: 'POST', headers: {'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ csrf_token: '<?= addslashes($csrf) ?>' }) });
        const text = await res.text(); const json = JSON.parse(text);
        if (json.success) { alert('Withdrawn: ' + (json.amount || 0)); location.reload(); }
        else alert(json.message || 'Erreur');
    } catch (e) { console.error(e); alert('Erreur réseau'); }
});
</script>