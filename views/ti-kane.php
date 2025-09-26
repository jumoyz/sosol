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

$pageTitle = "Ti Kanè";
$pageDescription = "Create and manage your Ti Kanè accounts for staggered investments.";

// Require a logged-in user
// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Generate a CSRF token if not already exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Provide CSRF token for forms & AJAX
$csrfToken = getCsrfToken();
$csrfToken = $_SESSION['csrf_token'];

?>
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h2 class="fw-bold mb-3">Ti Kanè</h2>
                        <p class="text-muted">Ti Kanè are Staggered investment: create a Ti Kanè account and control your daily payments.</p>
                    </div>
                    <div class="col-md-5 text-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTiKaneModal">
                            <i class="fas fa-plus-circle me-2"></i> Create New Ti Kanè
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <h4 class="fw-bold mb-3">
            <i class="fas fa-wallet text-primary me-2"></i> My Ti Kanè
        </h4>

    </div>

    <!-- Ti Kanè accounts list -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">Ti Kanè Accounts</div>
            <div class="card-body">
                <div id="accountsList">Loading...</div>
            </div>
        </div>
    </div>

    <!-- Calendar and details -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Payment Schedule</strong>
                <div>
                    <a class="btn btn-sm btn-outline-primary me-2" href="?page=ti-kane-calendar">Calendar</a>
                    <a class="btn btn-sm btn-outline-secondary me-2" href="?page=ti-kane-payments">Payments</a>
                    <button class="btn btn-sm btn-outline-secondary me-2" id="refreshBtn">Refresh</button>
                    <button class="btn btn-sm btn-outline-success" id="exportBtn">Export Receipt</button>
                </div>
            </div>
            <div class="card-body">
                <div id="accountSummary" class="mb-3">Select or create a Ti Kanè to view the schedule.</div>

                <div id="progressContainer" class="mb-3 d-none">
                    <div class="mb-1 small text-muted">Progress</div>
                    <div class="progress">
                        <div id="progressBar" class="progress-bar" role="progressbar" style="width:0%">0%</div>
                    </div>
                </div>

                <div id="scheduleContainer">No calendar generated.</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for creating Ti Kanè -->
<div class="modal fade" id="createTiKaneModal" tabindex="-1" aria-labelledby="createTiKaneModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createTiKaneModalLabel">Create a Ti Kanè</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createFormModal">
                    <input type="hidden" id="csrf_token" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select id="type" class="form-select">
                            <option value="progressif">Progressive</option>
                            <option value="fixe">Fixed</option>
                        </select>
                    </div>

                    <div class="mb-3" id="progressiveOptions">
                        <label class="form-label">Base Amount (Gdes/day)</label>
                        <select id="progressiveBase" class="form-select">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <div class="form-text">The amount increases each day: Day N = N × base</div>
                    </div>

                    <div class="mb-3 d-none" id="fixedOptions">
                        <label class="form-label">Fixed Amount (Gdes/day)</label>
                        <input type="number" id="fixedBase" class="form-control" min="250" value="250">
                        <div class="form-text">Minimum 250 Gdes/day for fixed amount</div>
                    </div>
            
                    <div class="mb-3">
                        <label class="form-label">Duration</label>
                        <select id="duration" class="form-select">
                            <option value="30">1 month (30 days)</option>
                            <option value="90">3 months (90 days)</option>
                            <option value="180">6 months (180 days)</option>
                            <option value="270">9 months (270 days)</option>
                            <option value="365">1 year (365 days)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" id="startDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Create</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Ti Kanè client-side (DB-backed) — interacts with server endpoints in /actions
const userId = '<?= addslashes($userId) ?>';
const csrfToken = '<?= addslashes($csrfToken) ?>';

function formatCurrency(n) { return (Number(n) || 0).toLocaleString() + ' Gdes'; }

async function fetchAccounts() {
    const res = await fetch('actions/ti-kane-list.php', { credentials: 'include' });
    const text = await res.text();
    try {
        const data = JSON.parse(text);
        // ti-kane-list returns an array of accounts on success
        if (!res.ok) {
            console.error('fetchAccounts error', res.status, data);
            // If unauthorized, redirect to login
            if (res.status === 401) {
                window.location.href = '?page=login';
            }
            throw new Error(data && data.message ? data.message : 'Failed to load accounts');
        }
        if (Array.isArray(data)) return data;
        if (data && data.accounts) return data.accounts;
        return [];
    } catch (err) {
        console.error('fetchAccounts: non-JSON response', res.status, text);
        throw new Error('Server returned invalid response');
    }
}

async function createAccount(payload) {
    payload.csrf_token = csrfToken;
    const res = await fetch('actions/ti-kane-create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(payload)
    });
    const text = await res.text();
    // Try to parse JSON; if server emitted warnings/HTML this will show useful debug info
    try {
        return JSON.parse(text);
    } catch (err) {
        console.error('createAccount: non-JSON response', text);
        throw new Error('Server returned invalid response');
    }
}

async function markPaid(accountId, dayNumber) {
    const body = { account_id: accountId, day_number: dayNumber, csrf_token: csrfToken };
    const res = await fetch('actions/ti-kane-mark-paid.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(body)
    });
    const text = await res.text();
    try { return JSON.parse(text); }
    catch (e) { console.error('markPaid: non-JSON response', text); throw new Error('Server returned invalid response'); }
}

function renderAccountsList(accounts) {
    const el = document.getElementById('accountsList');
    if (!el) return;
    if (!accounts || accounts.length === 0) { el.innerHTML = 'No Ti kanè account.'; return; }
    let html = '<div class="list-group">';
    accounts.slice().reverse().forEach(acc => {
        html += '<button class="list-group-item list-group-item-action" data-id="' + acc.id + '">';
        html += '<div class="d-flex justify-content-between align-items-center">';
        html += '<div><strong>' + (acc.type === 'progressif' ? 'Progressive' : 'Fixed') + ' - ' + acc.duration + ' days</strong><br><small class="text-muted">Base amount: ' + formatCurrency(acc.amount) + '</small></div>';
        html += '<div><small class="text-muted">Start: ' + acc.start_date + '</small></div>';
        html += '</div></button>';
    });
    html += '</div>';
    el.innerHTML = html;

    document.querySelectorAll('#accountsList [data-id]').forEach(btn => {
        btn.addEventListener('click', async function() {
            const id = this.getAttribute('data-id');
            const account = (await fetchAccounts()).find(a => a.id == id);
            if (account) renderAccount(account);
        });
    });
}

function renderAccount(account) {
    window.currentTiKane = account;
    const summary = document.getElementById('accountSummary');
    const container = document.getElementById('scheduleContainer');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    if (!summary || !container) return;

    const totalDue = account.payments.reduce((s,p) => s + Number(p.amount_due), 0);
    const totalPaid = account.payments.reduce((s,p) => s + Number(p.amount_paid || 0), 0);
    const paidDays = account.payments.filter(p => p.status === 'paid').length;
    const totalDays = account.payments.length;

    // Summary shows totals and counts
    summary.innerHTML = '<div class="d-flex justify-content-between align-items-start">' +
        '<div>' +
            '<h5 class="mb-1">' + (account.type === 'progressif' ? 'Progressive' : 'Fixed') + ' — ' + account.duration + ' days</h5>' +
            '<div class="small text-muted">Start: ' + account.start_date + ' | End: ' + account.end_date + '</div>' +
        '</div>' +
        '<div class="text-end">' +
            '<div class="small text-muted">Total payments</div>' +
            '<div class="fw-bold">' + totalDays + '</div>' +
            '<div class="small text-muted mt-1">Total due</div>' +
            '<div class="fw-bold">' + formatCurrency(totalDue) + '</div>' +
            '<div class="small text-muted mt-1">Total paid</div>' +
            '<div class="text-success fw-bold">' + formatCurrency(totalPaid) + '</div>' +
            '<div class="small text-muted mt-1">Remaining</div>' +
            '<div class="fw-bold">' + formatCurrency(Math.max(0, totalDue - totalPaid)) + '</div>' +
        '</div>' +
    '</div>';

    progressContainer.classList.remove('d-none');
    const percent = Math.round((paidDays / Math.max(1, totalDays)) * 100);
    progressBar.style.width = percent + '%';
    progressBar.textContent = percent + '%';

    // Paginate the schedule client-side for better UX
    const perPageOptions = [5,10,20];
    let currentPage = 1;
    let perPage = 10;

    function renderSchedulePage() {
        const start = (currentPage - 1) * perPage;
        const slice = account.payments.slice(start, start + perPage);
        let html = '<div class="table-responsive"><table class="table table-sm table-hover">';
        html += '<thead class="table-light"><tr><th>Day</th><th>Date</th><th>Amount due</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        slice.forEach(item => {
            let rowClass = '';
            if (item.status === 'paid') rowClass = 'table-success';
            else if (item.status === 'late') rowClass = 'table-danger';
            html += '<tr class="' + rowClass + '">';
            html += '<td>' + item.day_number + '</td>';
            html += '<td>' + item.due_date + '</td>';
            html += '<td>' + formatCurrency(item.amount_due) + '</td>';
            html += '<td>' + (item.status === 'paid' ? 'Paid' : (item.status === 'late' ? 'Late' : 'To pay')) + '</td>';
            if (item.status === 'paid') {
                html += '<td><small class="text-muted">' + (item.payment_date || '') + '</small></td>';
            } else {
                html += '<td><button class="btn btn-sm btn-primary markPaidBtn" data-day="' + item.day_number + '" data-account="' + account.id + '">Mark as paid</button></td>';
            }
            html += '</tr>';
        });
        html += '</tbody></table></div>';

        // pager
        const totalPages = Math.max(1, Math.ceil(account.payments.length / perPage));
        html += '<div class="d-flex justify-content-between align-items-center mt-2">';
        html += '<div><small class="text-muted">Showing ' + (start+1) + ' - ' + Math.min(account.payments.length, start + slice.length) + ' of ' + account.payments.length + '</small></div>';
        html += '<div class="d-flex align-items-center">';
        html += '<select id="perPageSelector" class="form-select form-select-sm me-2" style="width:auto">';
        perPageOptions.forEach(opt => { html += '<option value="'+opt+'"' + (opt===perPage? ' selected' : '') + '>'+opt+'</option>'; });
        html += '</select>';
        html += '<nav><ul class="pagination pagination-sm mb-0">';
        html += '<li class="page-item ' + (currentPage<=1? 'disabled':'') + '"><a class="page-link" href="#" data-page="'+Math.max(1,currentPage-1)+'">&laquo;</a></li>';
        const startP = Math.max(1, currentPage - 2);
        const endP = Math.min(totalPages, currentPage + 2);
        for (let p = startP; p <= endP; p++) {
            html += '<li class="page-item ' + (p===currentPage? 'active':'') + '"><a class="page-link" href="#" data-page="'+p+'">'+p+'</a></li>';
        }
        html += '<li class="page-item ' + (currentPage>=totalPages? 'disabled':'') + '"><a class="page-link" href="#" data-page="'+Math.min(totalPages,currentPage+1)+'">&raquo;</a></li>';
        html += '</ul></nav>';
        html += '</div></div>';

        container.innerHTML = html;

        // attach handlers
        document.getElementById('perPageSelector').addEventListener('change', function(){ perPage = Number(this.value) || 10; currentPage = 1; renderSchedulePage(); });
        container.querySelectorAll('.page-link[data-page]').forEach(a=> a.addEventListener('click', function(e){ e.preventDefault(); const p = Number(this.getAttribute('data-page')||1); currentPage = p; renderSchedulePage(); }));

        container.querySelectorAll('.markPaidBtn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const day = Number(this.getAttribute('data-day'));
                const acc = this.getAttribute('data-account');
                try {
                    const result = await markPaid(acc, day);
                    if (result.success) {
                        const refreshed = (await fetchAccounts()).find(a => a.id == acc);
                        renderAccountsList(await fetchAccounts());
                        renderAccount(refreshed);
                    } else {
                        alert(result.message || 'Erreur');
                    }
                } catch (err) { console.error(err); alert('Erreur réseau'); }
            });
        });
    }

    renderSchedulePage();
}

document.addEventListener('DOMContentLoaded', async function() {
    const typeEl = document.getElementById('type');
    const progressiveOptions = document.getElementById('progressiveOptions');
    const fixedOptions = document.getElementById('fixedOptions');
    // The create form was moved into a modal; support both IDs for backward compatibility
    const createForm = document.getElementById('createForm') || document.getElementById('createFormModal');
    const refreshBtn = document.getElementById('refreshBtn');
    const exportBtn = document.getElementById('exportBtn');

    if (typeEl) typeEl.addEventListener('change', function() {
        if (this.value === 'progressif') { progressiveOptions.classList.remove('d-none'); fixedOptions.classList.add('d-none'); }
        else { progressiveOptions.classList.add('d-none'); fixedOptions.classList.remove('d-none'); }
    });

    if (createForm) {
        createForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const type = document.getElementById('type').value;
            const duration = Number(document.getElementById('duration').value);
            let base = 0;
            if (type === 'progressif') base = Number(document.getElementById('progressiveBase').value);
            else base = Math.max(250, Number(document.getElementById('fixedBase').value || 0));
            const startDate = document.getElementById('startDate').value;
            try {
                const res = await createAccount({ type, amount: base, duration, start_date: startDate });
                if (res && res.success) {
                    // hide modal if present
                    const modalEl = document.getElementById('createTiKaneModal');
                    try {
                        if (modalEl) {
                            // Prefer an existing instance; fall back to creating one.
                            const m = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                            m.hide();

                            // Extra cleanup: sometimes a backdrop or the 'modal-open' class remains
                            // (especially in dev builds or when Xdebug outputs warnings). Remove
                            // any leftover backdrops and restore body state so clicks work.
                            document.body.classList.remove('modal-open');
                            document.body.style.overflow = '';
                            const backdrops = document.querySelectorAll('.modal-backdrop');
                            backdrops.forEach(b => b.parentNode && b.parentNode.removeChild(b));

                            // Also ensure the modal element doesn't remain shown in DOM state
                            modalEl.classList.remove('show');
                            modalEl.style.display = 'none';
                            modalEl.setAttribute('aria-hidden', 'true');
                        }
                    } catch (e) { console.debug('Bootstrap modal hide/cleanup failed', e); }

                    // refresh list & show the newly created account
                    const accounts = await fetchAccounts();
                    renderAccountsList(accounts);
                    if (accounts.length > 0) renderAccount(accounts[accounts.length - 1]);
                } else {
                    const msg = (res && res.message) ? res.message : 'Erreur lors de la cr\u00e9ation';
                    alert(msg);
                    console.error('TiKane create failed', res);
                }
            } catch (err) { console.error(err); alert('Erreur réseau'); }
        });
    }

    if (refreshBtn) refreshBtn.addEventListener('click', async () => { const a = await fetchAccounts(); renderAccountsList(a); if (window.currentTiKane) renderAccount(window.currentTiKane); });
    if (exportBtn) exportBtn.addEventListener('click', function() { if (window.currentTiKane) alert('Use Export from account details.'); else alert('Sélectionnez un compte d\'abord.'); });

    try {
        const accounts = await fetchAccounts();
        renderAccountsList(accounts);
        if (accounts.length > 0) renderAccount(accounts[accounts.length - 1]);
    } catch (err) {
        console.error(err);
        document.getElementById('accountsList').textContent = 'Impossible de charger les comptes.';
    }
});
</script>

