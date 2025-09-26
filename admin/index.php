<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$pageTitle = "Admin Dashboard";

// Include admin header
require_once __DIR__ . '/header.php';

// Include database connection
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// Check if admin is logged in


// Initialize variables for dashboard statistics
$totalUsers = 0;
$activeUsers = 0;
$totalTransactions = 0;
$totalAmount = 0;
$pendingTransactions = 0;
$recentTransactions = [];
$recentUsers = [];
$systemStatus = [
    'database' => true,
    'api' => true,
    'storage' => true,
    'mail' => true
];
$database = [];
$api = [];
$storage = [];
$mail = [];

// Get dashboard statistics
try {
    $db = getDbConnection();
    
    // Total users
    $userStmt = $db->query("SELECT COUNT(*) as total_users FROM users");
    $totalUsers = $userStmt->fetch(PDO::FETCH_ASSOC)['total_users'];
    
    // Active users today
    $activeUserStmt = $db->query("SELECT COUNT(DISTINCT user_id) as active_today FROM user_sessions WHERE DATE(last_activity) = CURDATE()");
    $activeUsers = $activeUserStmt->fetch(PDO::FETCH_ASSOC)['active_today'];
    
    // Total transactions
    $txnStmt = $db->query("SELECT COUNT(*) as total_transactions, SUM(amount) as total_amount FROM transactions WHERE status = 'completed'");
    $txnData = $txnStmt->fetch(PDO::FETCH_ASSOC);
    $totalTransactions = $txnData['total_transactions'];
    $totalAmount = $txnData['total_amount'] ?? 0;
    
    // Pending transactions
    $pendingStmt = $db->query("SELECT COUNT(*) as pending_transactions FROM transactions WHERE status = 'pending'");
    $pendingTransactions = $pendingStmt->fetch(PDO::FETCH_ASSOC)['pending_transactions'];
    
    // Recent transactions
    $recentTxnStmt = $db->query("
        SELECT t.*, u.full_name, u.email 
        FROM transactions t 
        INNER JOIN users u ON t.user_id = u.id 
        ORDER BY t.created_at DESC 
        LIMIT 5
    ");
    $recentTransactions = $recentTxnStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent users
    $recentUserStmt = $db->query("
        SELECT id, full_name, email, kyc_verified, created_at 
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentUsers = $recentUserStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // System status
    $systemStatus = [
        'database' => true,
        'api' => true,
        'storage' => true,
        'mail' => true
    ];
    
} catch (Exception $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $error = 'Unable to load dashboard statistics.';
}
?>
<!-- Sidebar -->

<!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 fw-bold">Dashboard Overview</h1>
            <div class="btn-group">
                <button class="btn btn-outline-secondary">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
                <button class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>Export Report
                </button>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-2">Total Users</h6>
                                <h3 class="fw-bold mb-0"><?= number_format($totalUsers) ?></h3>
                                <p class="text-success small mb-0">
                                    <i class="fas fa-arrow-up me-1"></i> 12.5% growth
                                </p>
                            </div>
                            <div class="bg-primary-subtle p-3 rounded">
                                <i class="fas fa-users fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-2">Active Today</h6>
                                <h3 class="fw-bold mb-0"><?= number_format($activeUsers) ?></h3>
                                <p class="text-success small mb-0">
                                    <i class="fas fa-user-check me-1"></i> 27.3% active
                                </p>
                            </div>
                            <div class="bg-success-subtle p-3 rounded">
                                <i class="fas fa-user-check fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-2">Total Transactions</h6>
                                <h3 class="fw-bold mb-0"><?= number_format($totalTransactions) ?></h3>
                                <p class="text-success small mb-0">
                                    <i class="fas fa-exchange-alt me-1"></i> $<?= number_format($totalAmount, 2) ?>
                                </p>
                            </div>
                            <div class="bg-info-subtle p-3 rounded">
                                <i class="fas fa-exchange-alt fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-muted mb-2">Pending Actions</h6>
                                <h3 class="fw-bold mb-0"><?= number_format($pendingTransactions) ?></h3>
                                <p class="text-warning small mb-0">
                                    <i class="fas fa-clock me-1"></i> Requires attention
                                </p>
                            </div>
                            <div class="bg-warning-subtle p-3 rounded">
                                <i class="fas fa-clock fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-xl-8 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent">
                        <h6 class="fw-bold mb-0">Transaction Overview</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="transactionChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent">
                        <h6 class="fw-bold mb-0">User Distribution</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="userChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Row -->
        <div class="row">
            <!-- Recent Transactions -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">Recent Transactions</h6>
                        <a href="transactions.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Amount</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-2">
                                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                        <i class="fas fa-user text-muted"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="fw-medium"><?= htmlspecialchars($transaction['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($transaction['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="fw-bold <?= $transaction['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= $transaction['amount'] > 0 ? '+' : '' ?><?= number_format($transaction['amount'], 2) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?= ucfirst($transaction['type'] ?? 'N/A') ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $transaction['status'] === 'completed' ? 'success' : 
                                                ($transaction['status'] === 'pending' ? 'warning' : 'secondary')
                                            ?>">
                                                <?= ucfirst($transaction['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= date('M j, H:i', strtotime($transaction['created_at'])) ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="col-xl-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">Recent Users</h6>
                        <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Joined</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-2">
                                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                        <i class="fas fa-user text-muted"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="fw-medium"><?= htmlspecialchars($user['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $user['kyc_verified'] ? 'success' : 'warning' ?>">
                                                <?= $user['kyc_verified'] ? 'Verified' : 'Pending' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="user-details.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent">
                        <h6 class="fw-bold mb-0">System Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <div class="system-status-item">
                                    <i class="fas fa-database fa-2x mb-2 <?= $systemStatus['database'] ? 'text-success' : 'text-danger' ?>"></i>
                                    <h6>Database</h6>
                                    <span class="badge bg-<?= $systemStatus['database'] ? 'success' : 'danger' ?>">
                                        <?= $systemStatus['database'] ? 'Online' : 'Offline' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="system-status-item">
                                    <i class="fas fa-plug fa-2x mb-2 <?= $systemStatus['api'] ? 'text-success' : 'text-danger' ?>"></i>
                                    <h6>API Services</h6>
                                    <span class="badge bg-<?= $systemStatus['api'] ? 'success' : 'danger' ?>">
                                        <?= $systemStatus['api'] ? 'Online' : 'Offline' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="system-status-item">
                                    <i class="fas fa-hdd fa-2x mb-2 <?= $systemStatus['storage'] ? 'text-success' : 'text-danger' ?>"></i>
                                    <h6>Storage</h6>
                                    <span class="badge bg-<?= $systemStatus['storage'] ? 'success' : 'danger' ?>">
                                        <?= $systemStatus['storage'] ? 'OK' : 'Full' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="system-status-item">
                                    <i class="fas fa-envelope fa-2x mb-2 <?= $systemStatus['mail'] ? 'text-success' : 'text-danger' ?>"></i>
                                    <h6>Mail Service</h6>
                                    <span class="badge bg-<?= $systemStatus['mail'] ? 'success' : 'danger' ?>">
                                        <?= $systemStatus['mail'] ? 'Online' : 'Offline' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

<!-- Chart Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Transaction Chart
    const transactionCtx = document.getElementById('transactionChart').getContext('2d');
    const transactionChart = new Chart(transactionCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Transactions',
                data: [120, 150, 180, 210, 240, 270, 300, 330, 360, 390, 420, 450],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // User Chart
    const userCtx = document.getElementById('userChart').getContext('2d');
    const userChart = new Chart(userCtx, {
        type: 'doughnut',
        data: {
            labels: ['Verified', 'Pending', 'Suspended'],
            datasets: [{
                data: [75, 15, 10],
                backgroundColor: ['#198754', '#ffc107', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<?php
// Include admin footer
require_once __DIR__ . '/footer.php';
?>