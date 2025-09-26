<?php
// Set page title
$pageTitle = "Dashboard";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

try {
    $db = getDbConnection();
    
    // Get wallet data
    $walletStmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ?");
    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get SOL groups the user participates in
    $solStmt = $db->prepare("
        SELECT sg.* 
        FROM sol_groups sg
        INNER JOIN sol_participants sp ON sg.id = sp.sol_group_id
        WHERE sp.user_id = ?
        ORDER BY sg.created_at DESC
        LIMIT 3
    ");
    $solStmt->execute([$userId]);
    $solGroups = $solStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent transactions
    $txnStmt = $db->prepare("
        SELECT t.*
        FROM transactions t
        INNER JOIN wallets w ON t.wallet_id = w.id
        WHERE w.user_id = ?
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $txnStmt->execute([$userId]);
    $recentTransactions = $txnStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Dashboard data fetch error: ' . $e->getMessage());
    $error = 'Unable to load dashboard data. Please try again later.';
}
?>

<!-- Welcome Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-3"><?= htmlspecialchars(__t('welcome')) ?>, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></h2>
                <p class="text-muted"><?= htmlspecialchars(__t('welcome_desc')) ?></p>
                
                <?php if (empty($solGroups)): ?>
                <div class="mt-3">
                    <a href="?page=sol-groups" class="btn btn-primary">
                        <i class="fas fa-users me-2"></i> <?= htmlspecialchars(__t('join_group')) ?>Join a SOL Group
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-center text-md-end">
                <img src="/public/images/dashboard-welcome.png" alt="Welcome" class="img-fluid" style="max-width: 200px;">
            </div>
        </div>
    </div>
</div>

<!-- Stats Overview -->
<div class="row mb-4">
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-primary-light me-3">
                        <i class="fas fa-users text-primary"></i>
                    </div>
                    <h5 class="mb-0">SOL Groups</h5>
                </div>
                <h2 class="fw-bold mb-0"><?= count($solGroups) ?></h2>
                <p class="text-muted">Active Memberships</p>
                <a href="?page=sol-groups" class="btn btn-sm btn-outline-primary">Manage Groups</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-success-light me-3">
                        <i class="fas fa-wallet text-success"></i>
                    </div>
                    <h5 class="mb-0">Wallet</h5>
                </div>
                <h2 class="fw-bold mb-0"><?= number_format($wallet['balance_htg'] ?? 0) ?> HTG</h2>
                <p class="text-muted">Available Balance</p>
                <a href="?page=wallet&action=add-funds" class="btn btn-sm btn-outline-success">Add Funds</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-circle bg-info-light me-3">
                        <i class="fas fa-exchange-alt text-info"></i>
                    </div>
                    <h5 class="mb-0">Transactions</h5>
                </div>
                <h2 class="fw-bold mb-0"><?= count($recentTransactions) ?></h2>
                <p class="text-muted">Recent Activities</p>
                <a href="?page=wallet&action=history" class="btn btn-sm btn-outline-info">View History</a>
            </div>
        </div>
    </div>
</div>

<!-- Main Dashboard Content -->
<div class="row">
    <!-- SOL Groups -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-users text-primary me-2"></i> My SOL Groups
                </h5>
                <a href="?page=sol-groups" class="btn btn-sm btn-link text-decoration-none">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($solGroups)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Group Name</th>
                                    <th>Cycle</th>
                                    <th>Contribution</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solGroups as $group): ?>
                                <tr>
                                    <td>
                                        <a href="?page=sol-details&id=<?= $group['id'] ?>" class="text-decoration-none fw-medium">
                                            <?= htmlspecialchars($group['name']) ?>
                                        </a>
                                    </td>
                                    <td><?= $group['current_cycle'] ?>/<?= $group['total_cycles'] ?></td>
                                    <td><?= number_format($group['contribution']) ?> HTG</td>
                                    <td>
                                        <span class="badge bg-<?= $group['status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($group['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="fas fa-user-friends text-muted" style="font-size: 2rem;"></i>
                        </div>
                        <p class="mb-3">You haven't joined any SOL groups yet.</p>
                        <a href="?page=sol-groups" class="btn btn-primary">
                            Join a SOL Group
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="col-lg-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exchange-alt text-primary me-2"></i> Recent Transactions
                </h5>
                <a href="?page=wallet&action=history" class="btn btn-sm btn-link text-decoration-none">See All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentTransactions)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTransactions as $txn): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="icon-circle bg-light me-2">
                                            <i class="fas fa-<?=
                                                $txn['type'] == 'recharge' ? 'arrow-up text-success' :
                                                ($txn['type'] == 'withdrawal' ? 'arrow-down text-danger' :
                                                'exchange-alt text-info')
                                            ?>"></i>
                                        </span>
                                        <?= ucfirst($txn['type']) ?>
                                    </div>
                                </td>
                                <td class="fw-medium">
                                    <?= number_format($txn['amount'], 2) ?>
                                    <?= isset($txn['currency']) ? $txn['currency'] : 'HTG' ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?=
                                        $txn['status'] == 'completed' ? 'success' :
                                        ($txn['status'] == 'pending' ? 'warning' : 'danger')
                                    ?>">
                                        <?= ucfirst($txn['status']) ?>
                                    </span>
                                </td>
                                <td class="text-muted"><?= date('M j, Y', strtotime($txn['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted text-center py-4 mb-0">No recent transactions</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bg-primary-light {
    background-color: rgba(13, 110, 253, 0.1);
}

.bg-success-light {
    background-color: rgba(25, 135, 84, 0.1);
}

.bg-info-light {
    background-color: rgba(13, 202, 240, 0.1);
}
</style>