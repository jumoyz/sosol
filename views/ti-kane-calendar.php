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

$pageTitle = "Ti Kanè Calendar";
$pageDescription = "View and manage your Ti Kanè payment calendar.";

// Require a logged-in user
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) redirect('?page=login');

// Load payments for this user
try {
    $db = getDbConnection();
    $stmt = $db->prepare('SELECT p.*, a.type AS account_type, a.duration, a.start_date, a.end_date, a.user_id, a.id as account_id FROM ti_kane_payments p JOIN ti_kane_accounts a ON p.account_id = a.id WHERE a.user_id = ?');
    $stmt->execute([$userId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payments = [];
}

// Generate CSRF token for AJAX
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

?>
<div class="row">
    <div class="col-12 mb-3">
        <h3>Ti Kanè — Calendar</h3>
        <p class="text-muted">Click on a due date (red) to mark payment as paid. Use export to sync with external calendars.</p>
    </div>

    <div class="col-lg-9">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <button id="prevMonth" class="btn btn-sm btn-outline-secondary">&lt; Prev</button>
                        <button id="nextMonth" class="btn btn-sm btn-outline-secondary">Next &gt;</button>
                    </div>
                    <div>
                        <button id="exportIcs" class="btn btn-sm btn-outline-primary">Export .ics</button>
                        <button id="refreshCal" class="btn btn-sm btn-outline-secondary">Refresh</button>
                    </div>
                </div>
                <div id="calendarRoot"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="card">
            <div class="card-body">
                <h6>Legend</h6>
                <div><span class="badge bg-danger me-2">&nbsp;</span> Due</div>
                <div><span class="badge bg-success me-2">&nbsp;</span> Paid</div>
                <div class="mt-3"><small class="text-muted">You can export payments as an .ics file and import into Google/Outlook.</small></div>
            </div>
        </div>
    </div>
</div>

<style>
    .tk-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap:8px; }
    .tk-day { border:1px solid #e9ecef; min-height:90px; padding:6px; background:#fff; border-radius:4px; }
    .tk-day .date { font-weight:600; font-size:0.9rem; }
    .tk-day.due { background: #fff5f5; border-color:#f5c6cb; }
    .tk-day.paid { background: #f6fff6; border-color:#b8e0b8; }
    .tk-day .amount { font-size:0.85rem; margin-top:6px; }
    .tk-day .pay-btn { margin-top:8px; }
</style>

<script>
const paymentsData = <?= json_encode($payments, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
const csrfToken = '<?= addslashes($csrfToken) ?>';

function buildMonthMatrix(year, month) {
    const first = new Date(year, month, 1);
    const startDay = first.getDay();
    const daysInMonth = new Date(year, month+1, 0).getDate();
    const rows = [];
    let week = new Array(7).fill(null);
    let dayCounter = 1;
    // Fill first week
    for (let i = startDay; i < 7; i++) { week[i] = dayCounter++; }
    rows.push(week.slice());
    while (dayCounter <= daysInMonth) {
        let row = new Array(7).fill(null);
        for (let i=0;i<7 && dayCounter<=daysInMonth;i++) { row[i] = dayCounter++; }
        rows.push(row);
    }
    return rows;
}

function formatDateYMD(y,m,d){ return y + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0'); }

let current = new Date();
let calYear = current.getFullYear();
let calMonth = current.getMonth();

function renderCalendar() {
    const root = document.getElementById('calendarRoot');
    const monthName = new Date(calYear, calMonth).toLocaleString(undefined,{month:'long', year:'numeric'});
    const rows = buildMonthMatrix(calYear, calMonth);
    let html = '<div class="mb-2"><strong>' + monthName + '</strong></div>';
    html += '<div class="d-flex tk-weekdays mb-2">';
    ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(d=> html += '<div style="width:14.28%" class="small text-muted text-center">'+d+'</div>');
    html += '</div>';
    html += '<div class="tk-calendar">';
    rows.forEach(week => {
        week.forEach(day => {
            if (!day) { html += '<div class="tk-day"></div>'; return; }
            const ymd = formatDateYMD(calYear, calMonth+1, day);
            const dayPayments = paymentsData.filter(p => p.due_date === ymd);
            let cls = '';
            if (dayPayments.length > 0) {
                // If any payment unpaid -> due, else paid
                const unpaid = dayPayments.some(p => p.status !== 'paid');
                cls = unpaid ? 'due' : 'paid';
            }
            html += '<div class="tk-day '+cls+'" data-date="'+ymd+'">';
            html += '<div class="date">' + day + '</div>';
            if (dayPayments.length) {
                dayPayments.forEach(p=>{
                    html += '<div class="amount">' + (p.status==='paid'?'<span class="badge bg-success">Paid</span>':'<span class="badge bg-danger">To pay</span>') + ' ' + Number(p.amount_due).toLocaleString() + ' Gdes</div>';
                });
                if (dayPayments.some(p=>p.status !== 'paid')) {
                    html += '<div><button class="btn btn-sm btn-primary pay-btn">Mark as paid</button></div>';
                }
                html += '<div class="small text-muted mt-1">' + dayPayments.length + ' payment(s)</div>';
            }
            html += '</div>';
        });
    });
    html += '</div>';
    root.innerHTML = html;

    // attach handlers on pay buttons
    document.querySelectorAll('.tk-day .pay-btn').forEach(btn => {
        btn.addEventListener('click', async function(e){
            const dayEl = this.closest('.tk-day');
            const date = dayEl.getAttribute('data-date');
            const payments = paymentsData.filter(p=>p.due_date === date && p.status !== 'paid');
            if (!payments.length) return alert('No unpaid payment found');
            // Mark first unpaid payment as paid (user action)
            const p = payments[0];
            if (!confirm('Mark the payment made on ' + date + ' as paid?')) return;
            try {
                const res = await fetch('actions/ti-kane-mark-paid.php', { method:'POST', headers: {'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({ account_id: p.account_id, day_number: p.day_number, csrf_token: csrfToken }) });
                const text = await res.text();
                const json = JSON.parse(text);
                if (json.success) {
                    // update local state
                    paymentsData.forEach(q => { if (q.id === p.id) { q.status = 'paid'; q.payment_date = new Date().toISOString().slice(0,10); } });
                    renderCalendar();
                } else {
                    alert(json.message || 'Error');
                }
            } catch (err) { console.error(err); alert('Network error'); }
        });
    });
}

document.getElementById('prevMonth').addEventListener('click', function(){ calMonth--; if (calMonth<0) { calMonth=11; calYear--; } renderCalendar(); });
document.getElementById('nextMonth').addEventListener('click', function(){ calMonth++; if (calMonth>11) { calMonth=0; calYear++; } renderCalendar(); });
document.getElementById('refreshCal').addEventListener('click', function(){ location.reload(); });

function exportIcs() {
    // Build simple ICS with one event per payment
    let ics = 'BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//SoSol TiKane//EN\r\n';
    paymentsData.forEach(p=>{
        const dt = p.due_date.replace(/-/g,'');
        const uid = p.id || (p.account_id + '-' + p.day_number);
        const title = encodeURIComponent('Ti Kanè payment - ' + (p.status==='paid' ? 'Paid' : 'Due'));
        const desc = encodeURIComponent('Amount: ' + p.amount_due + ' Gdes');
        ics += 'BEGIN:VEVENT\r\nUID:' + uid + '\r\nDTSTART;VALUE=DATE:' + dt + '\r\nDTEND;VALUE=DATE:' + dt + '\r\nSUMMARY:' + title + '\r\nDESCRIPTION:' + desc + '\r\nEND:VEVENT\r\n';
    });
    ics += 'END:VCALENDAR\r\n';
    const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'ti-kane-payments.ics'; document.body.appendChild(a); a.click(); a.remove();
}
document.getElementById('exportIcs').addEventListener('click', exportIcs);

// initial render
renderCalendar();
</script>