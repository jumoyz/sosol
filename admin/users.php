<?php
// Set page title
$pageTitle = "Users Management";

// Include admin header (which includes sidebar)
require_once 'header.php';

// Include database connection
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDbConnection();
        
        if (isset($_POST['update_user_status'])) {
            $userId = $_POST['user_id'];
            $newStatus = $_POST['new_status'];
            $reason = $_POST['reason'] ?? '';
            
            // Check if status column exists before updating
            $hasStatusColumn = false;
            try {
                $statusCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'status'");
                $hasStatusColumn = (bool) $statusCheck->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('Status column check failed: ' . $e->getMessage());
            }
            
            if ($hasStatusColumn) {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $userId]);
                
                // Log the status change
                logActivity($pdo, $_SESSION['user_id'], 'user_status_changed', 
                           "Changed user {$userId} status to {$newStatus}. Reason: {$reason}");
                
                setFlashMessage('success', 'User status updated successfully.');
            } else {
                setFlashMessage('error', 'User status update not available - status column does not exist.');
            }
            
        } elseif (isset($_POST['update_kyc_status'])) {
            $userId = $_POST['user_id'];
            $kycStatus = $_POST['kyc_status'];
            $notes = $_POST['kyc_notes'] ?? '';
            
            // Check which KYC column exists and update accordingly
            if ($hasKycStatus) {
                $stmt = $pdo->prepare("UPDATE users SET kyc_status = ?, kyc_notes = ? WHERE id = ?");
                $stmt->execute([$kycStatus, $notes, $userId]);
            } elseif ($hasKycVerified) {
                $kycVerified = ($kycStatus === 'verified') ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE users SET kyc_verified = ?, kyc_notes = ? WHERE id = ?");
                $stmt->execute([$kycVerified, $notes, $userId]);
            }
            
            logActivity($pdo, $_SESSION['user_id'], 'kyc_status_changed', 
                       "Updated KYC status for user {$userId} to {$kycStatus}");
            
            setFlashMessage('success', 'KYC status updated successfully.');
            
        } elseif (isset($_POST['send_notification'])) {
            $userIds = $_POST['selected_users'] ?? [];
            $title = $_POST['notification_title'];
            $message = $_POST['notification_message'];
            $sent = 0;
            
            if (empty($userIds)) {
                setFlashMessage('error', 'No users selected for notification.');
            } elseif (empty($title) || empty($message)) {
                setFlashMessage('error', 'Title and message are required.');
            } else {
                // Check if notifications table exists, create if not
                try {
                    $pdo->query("DESCRIBE notifications");
                } catch (Exception $e) {
                    // Create notifications table
                    $pdo->exec("
                        CREATE TABLE notifications (
                            id INT PRIMARY KEY AUTO_INCREMENT,
                            user_id INT NOT NULL,
                            title VARCHAR(255) NOT NULL,
                            message TEXT NOT NULL,
                            created_by INT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            read_at TIMESTAMP NULL,
                            INDEX idx_user_id (user_id),
                            INDEX idx_created_at (created_at)
                        )
                    ");
                }
                
                foreach ($userIds as $userId) {
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, created_by, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$userId, $title, $message, $_SESSION['user_id']]);
                    $sent++;
                }
                
                setFlashMessage('success', "Notification sent to {$sent} user(s).");
            }
        }
    } catch (Exception $e) {
        error_log('Admin user action error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred while processing the request.');
    }
    
    header('Location: users.php');
    exit();
}

// Get filters
$status_filter = $_GET['status'] ?? 'all';
$kyc_filter = $_GET['kyc'] ?? 'all';
$role_filter = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';

// We'll build WHERE clauses after detecting which KYC column exists (kyc_status vs kyc_verified)
try {
    $pdo = getDbConnection();

    // Detect KYC column presence using SHOW COLUMNS (safer permissions-wise)
    $hasKycStatus = false;
    $hasKycVerified = false;
    try {
        $res = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'kyc_status'");
        $hasKycStatus = (bool) $res->fetch(PDO::FETCH_ASSOC);

        $res2 = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'kyc_verified'");
        $hasKycVerified = (bool) $res2->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('KYC column detection error (SHOW COLUMNS): ' . $e->getMessage());
        // Fallback: try to fetch a single row and inspect returned keys
        try {
            $sample = $pdo->query('SELECT * FROM users LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            if ($sample) {
                $hasKycStatus = array_key_exists('kyc_status', $sample);
                $hasKycVerified = array_key_exists('kyc_verified', $sample);
            }
        } catch (Exception $e2) {
            error_log('KYC column detection fallback failed: ' . $e2->getMessage());
            $hasKycStatus = false;
            $hasKycVerified = false;
        }
    }

    // Check if status column exists
    $hasStatusColumn = false;
    try {
        $statusCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'status'");
        $hasStatusColumn = (bool) $statusCheck->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Status column check failed: ' . $e->getMessage());
    }

    // Build query conditions dynamically
    $where_conditions = [];
    $params = [];

    if ($status_filter !== 'all' && $hasStatusColumn) {
        $where_conditions[] = 'status = ?';
        $params[] = $status_filter;
    }

    if ($role_filter !== 'all') {
        $where_conditions[] = 'role = ?';
        $params[] = $role_filter;
    }

    if ($kyc_filter !== 'all') {
        if ($hasKycStatus) {
            $where_conditions[] = 'kyc_status = ?';
            $params[] = $kyc_filter;
        } elseif ($hasKycVerified) {
            // Map filter to kyc_verified boolean values
            if ($kyc_filter === 'verified') {
                $where_conditions[] = 'kyc_verified = 1';
            } elseif ($kyc_filter === 'pending') {
                $where_conditions[] = '(kyc_verified = 0 OR kyc_verified IS NULL)';
            } else {
                // 'rejected' or unknown - no reliable mapping, add a clause that won't match
                $where_conditions[] = '0 = 1';
            }
        }
    }

    if (!empty($search)) {
        $where_conditions[] = '(full_name LIKE ? OR email LIKE ? OR phone_number LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // Get user statistics - use appropriate columns
    if ($hasKycStatus && $hasStatusColumn) {
        $stats_query = "
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
                SUM(CASE WHEN kyc_status = 'verified' THEN 1 ELSE 0 END) as verified_users,
                SUM(CASE WHEN kyc_status = 'pending' THEN 1 ELSE 0 END) as pending_kyc,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users
            FROM users
            {$where_clause}
        ";
    } elseif ($hasStatusColumn) {
        // Has status column but uses kyc_verified boolean
        $stats_query = "
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
                SUM(CASE WHEN kyc_verified = 1 THEN 1 ELSE 0 END) as verified_users,
                SUM(CASE WHEN (kyc_verified = 0 OR kyc_verified IS NULL) THEN 1 ELSE 0 END) as pending_kyc,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users
            FROM users
            {$where_clause}
        ";
    } else {
        // No status column, treat all users as active
        $stats_query = "
            SELECT 
                COUNT(*) as total_users,
                COUNT(*) as active_users,
                0 as suspended_users,
                SUM(CASE WHEN kyc_verified = 1 THEN 1 ELSE 0 END) as verified_users,
                SUM(CASE WHEN (kyc_verified = 0 OR kyc_verified IS NULL) THEN 1 ELSE 0 END) as pending_kyc,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users
            FROM users
            {$where_clause}
        ";
    }

    $stats_stmt = $pdo->prepare($stats_query);
    // Debug: log stats query and params
    error_log('Users stats_query: ' . $stats_query);
    error_log('Users stats_params: ' . json_encode($params));
    error_log('Columns detected: kyc_status=' . ($hasKycStatus ? '1' : '0') . ', kyc_verified=' . ($hasKycVerified ? '1' : '0') . ', status=' . ($hasStatusColumn ? '1' : '0'));
    $stats_stmt->execute($params);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    error_log('Users stats result: ' . json_encode($stats));

    // Get users with pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    // Try simplified query first to debug
    $query = "
        SELECT u.*,
               COALESCE(w.balance_htg, 0) as balance_htg, 
               COALESCE(w.balance_usd, 0) as balance_usd,
               COALESCE(t.transaction_count, 0) as transaction_count,
               COALESCE(s.sol_count, 0) as sol_count
        FROM users u
        LEFT JOIN wallets w ON u.id = w.user_id
        LEFT JOIN (SELECT user_id, COUNT(*) as transaction_count FROM transactions GROUP BY user_id) t ON u.id = t.user_id
        LEFT JOIN (SELECT user_id, COUNT(*) as sol_count FROM sol_participants GROUP BY user_id) s ON u.id = s.user_id
        {$where_clause}
        ORDER BY u.created_at DESC
        LIMIT {$per_page} OFFSET {$offset}
    ";

    $stmt = $pdo->prepare($query);
    // Debug: log main user query and params
    error_log('Users main_query: ' . $query);
    error_log('Users main_params: ' . json_encode($params));
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug: log number of users fetched
    error_log('Users fetched: ' . count($users));
    if (empty($users)) {
        error_log('No users found - checking if users table has data...');
        $test_query = "SELECT COUNT(*) as total FROM users";
        $test_stmt = $pdo->prepare($test_query);
        $test_stmt->execute();
        $total_in_db = $test_stmt->fetchColumn();
        error_log('Total users in database: ' . $total_in_db);
        
        if ($total_in_db > 0) {
            error_log('Users exist in DB but query returned empty - checking query conditions');
            error_log('Where clause: ' . $where_clause);
            error_log('Parameters: ' . json_encode($params));
            
            // Test basic query
            $basic_query = "SELECT id, full_name, email FROM users LIMIT 5";
            $basic_stmt = $pdo->prepare($basic_query);
            $basic_stmt->execute();
            $basic_result = $basic_stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log('Basic query result: ' . json_encode($basic_result));
        }
    }

    // Get total for pagination
    $count_query = "SELECT COUNT(*) FROM users {$where_clause}";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_users = $count_stmt->fetchColumn();
    $total_pages = ceil($total_users / $per_page);

} catch (Exception $e) {
    error_log('User fetch error: ' . $e->getMessage());
    error_log('User fetch error trace: ' . $e->getTraceAsString());
    $users = [];
    $stats = ['total_users' => 0, 'active_users' => 0, 'suspended_users' => 0, 'verified_users' => 0, 'pending_kyc' => 0, 'new_users' => 0];
    $total_pages = 1;
    
    // Display error for debugging in development
    if (defined('DEBUG') && constant('DEBUG') === true) {
        echo '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>

<?php include_once 'sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold">User Management</h1>
                <p class="text-muted mb-0">Manage user accounts, KYC status, and permissions</p>
                <small class="text-info">Found <?= count($users) ?> users (Total in DB: <?= $stats['total_users'] ?? 0 ?>)</small>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline-secondary" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
                <button class="btn btn-primary" id="sendNotificationBtn" disabled>
                    <i class="fas fa-bell me-2"></i>Send Notification
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-primary mb-2">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?= number_format($stats['total_users']) ?></h4>
                        <small class="text-muted">Total Users</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-success mb-2">
                            <i class="fas fa-user-check fa-2x"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?= number_format($stats['active_users']) ?></h4>
                        <small class="text-muted">Active Users</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-danger mb-2">
                            <i class="fas fa-user-times fa-2x"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?= number_format($stats['suspended_users']) ?></h4>
                        <small class="text-muted">Suspended</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-info mb-2">
                            <i class="fas fa-id-card fa-2x"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?= number_format($stats['verified_users']) ?></h4>
                        <small class="text-muted">KYC Verified</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-warning mb-2">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?= number_format($stats['pending_kyc']) ?></h4>
                        <small class="text-muted">Pending KYC</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-purple mb-2">
                            <i class="fas fa-user-plus fa-2x"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?= number_format($stats['new_users']) ?></h4>
                        <small class="text-muted">New (30d)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="kyc" class="form-label">KYC Status</label>
                        <select class="form-select" id="kyc" name="kyc">
                            <option value="all" <?= $kyc_filter === 'all' ? 'selected' : '' ?>>All KYC</option>
                            <option value="pending" <?= $kyc_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="verified" <?= $kyc_filter === 'verified' ? 'selected' : '' ?>>Verified</option>
                            <option value="rejected" <?= $kyc_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                            <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No users found</h5>
                        <p class="text-muted">Try adjusting your filters to see more results.</p>
                        <?php if (defined('DEBUG') && constant('DEBUG') === true): ?>
                            <div class="mt-3 p-3 bg-light text-start">
                                <strong>Debug Info:</strong><br>
                                KYC Status Column: <?= $hasKycStatus ? 'Yes' : 'No' ?><br>
                                KYC Verified Column: <?= $hasKycVerified ? 'Yes' : 'No' ?><br>
                                Status Column: <?= $hasStatusColumn ? 'Yes' : 'No' ?><br>
                                Filter Status: <?= $status_filter ?><br>
                                Filter KYC: <?= $kyc_filter ?><br>
                                Filter Role: <?= $role_filter ?><br>
                                Search: <?= $search ?><br>
                                Total Users: <?= $stats['total_users'] ?? 0 ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <form id="bulkActionForm" method="POST">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th>User</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>KYC</th>
                                        <th>Wallet Balance</th>
                                        <th>Activity</th>
                                        <th>Joined</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input user-checkbox" type="checkbox" 
                                                           name="user_ids[]" value="<?= $user['id'] ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center">
                                                        <?= strtoupper(substr($user['full_name'], 0, 2)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($user['full_name']) ?></div>
                                                        <small class="text-muted"><?= ucfirst($user['role']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($user['email']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($user['phone_number'] ?? 'No phone') ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                // Handle status column existence
                                                if (isset($user['status'])) {
                                                    $statusColors = [
                                                        'active' => 'success',
                                                        'suspended' => 'danger',
                                                        'inactive' => 'warning'
                                                    ];
                                                    $statusColor = $statusColors[$user['status']] ?? 'secondary';
                                                    $statusText = ucfirst($user['status']);
                                                } else {
                                                    // No status column, show as active
                                                    $statusColor = 'success';
                                                    $statusText = 'Active';
                                                }
                                                ?>
                                                <span class="badge bg-<?= $statusColor ?>">
                                                    <?= $statusText ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                // Handle both kyc_status and kyc_verified fields
                                                $kycStatus = '';
                                                if (isset($user['kyc_status'])) {
                                                    $kycStatus = $user['kyc_status'];
                                                } elseif (isset($user['kyc_verified'])) {
                                                    $kycStatus = $user['kyc_verified'] ? 'verified' : 'pending';
                                                } else {
                                                    $kycStatus = 'pending';
                                                }
                                                
                                                $kycColors = [
                                                    'pending' => 'warning',
                                                    'verified' => 'success',
                                                    'rejected' => 'danger'
                                                ];
                                                $kycColor = $kycColors[$kycStatus] ?? 'secondary';
                                                $kycIcon = $kycStatus === 'verified' ? 'check' : 
                                                          ($kycStatus === 'rejected' ? 'times' : 'clock');
                                                ?>
                                                <span class="badge bg-<?= $kycColor ?>-subtle text-<?= $kycColor ?>">
                                                    <i class="fas fa-<?= $kycIcon ?> me-1"></i>
                                                    <?= ucfirst($kycStatus) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-nowrap">
                                                    <div><?= number_format($user['balance_htg'] ?? 0, 2) ?> HTG</div>
                                                    <small class="text-muted"><?= number_format($user['balance_usd'] ?? 0, 2) ?> USD</small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-nowrap">
                                                    <div><?= $user['transaction_count'] ?> transactions</div>
                                                    <small class="text-muted"><?= $user['sol_count'] ?> SOL groups</small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-nowrap">
                                                    <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                                    <br><small class="text-muted"><?= date('g:i A', strtotime($user['created_at'])) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="viewUser(<?= $user['id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-warning" 
                                                            onclick="editUserStatus(<?= $user['id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info" 
                                                            onclick="editKycStatus(<?= $user['id'] ?>)">
                                                        <i class="fas fa-id-card"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="User pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetails">
                <!-- Details loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Change User Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="statusUserInfo"></div>
                    <div class="mb-3">
                        <label for="new_status" class="form-label">New Status *</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="">Select Status</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <textarea class="form-control" id="reason" name="reason" 
                                  rows="3" placeholder="Optional reason for status change..."></textarea>
                    </div>
                    <input type="hidden" name="user_id" id="statusUserId">
                    <input type="hidden" name="update_user_status" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- KYC Status Modal -->
<div class="modal fade" id="kycModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Update KYC Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="kycUserInfo"></div>
                    <div class="mb-3">
                        <label for="kyc_status" class="form-label">KYC Status *</label>
                        <select class="form-select" id="kyc_status" name="kyc_status" required>
                            <option value="">Select KYC Status</option>
                            <option value="pending">Pending</option>
                            <option value="verified">Verified</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="kyc_notes" class="form-label">KYC Notes</label>
                        <textarea class="form-control" id="kyc_notes" name="kyc_notes" 
                                  rows="3" placeholder="Notes about KYC verification..."></textarea>
                    </div>
                    <input type="hidden" name="user_id" id="kycUserId">
                    <input type="hidden" name="update_kyc_status" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update KYC Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Send Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This notification will be sent to <span id="selectedUserCount">0</span> selected user(s).
                    </div>
                    <div class="mb-3">
                        <label for="notification_title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="notification_title" name="notification_title" 
                               required placeholder="Notification title...">
                    </div>
                    <div class="mb-3">
                        <label for="notification_message" class="form-label">Message *</label>
                        <textarea class="form-control" id="notification_message" name="notification_message" 
                                  rows="4" required placeholder="Notification message..."></textarea>
                    </div>
                    <div id="selectedUsersList"></div>
                    <input type="hidden" name="send_notification" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Send Notification
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// User management JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Select all functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const sendNotificationBtn = document.getElementById('sendNotificationBtn');
    
    selectAllCheckbox?.addEventListener('change', function() {
        userCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateNotificationButton();
    });
    
    userCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateNotificationButton);
    });
    
    function updateNotificationButton() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        sendNotificationBtn.disabled = checkedBoxes.length === 0;
        
        // Update select all checkbox state
        const checkedCount = checkedBoxes.length;
        const totalCount = userCheckboxes.length;
        
        if (selectAllCheckbox) {
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
            selectAllCheckbox.checked = totalCount > 0 && checkedCount === totalCount;
        }
    }
    
    // Send notification
    sendNotificationBtn?.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        if (checkedBoxes.length === 0) return;
        
        // Update selected user count
        document.getElementById('selectedUserCount').textContent = checkedBoxes.length;
        
        // Clear previous selected users list
        const selectedUsersList = document.getElementById('selectedUsersList');
        selectedUsersList.innerHTML = '';
        
        // Add hidden inputs for selected users
        checkedBoxes.forEach(checkbox => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'selected_users[]';
            hiddenInput.value = checkbox.value;
            selectedUsersList.appendChild(hiddenInput);
        });
        
        new bootstrap.Modal(document.getElementById('notificationModal')).show();
    });
});

// View user details
function viewUser(userId) {
    console.log('Viewing user ID:', userId); // Debug log
    fetch('get_user_details.php?id=' + userId)
        .then(response => {
            console.log('Response status:', response.status, response.statusText);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('User details response:', data); // Debug log
            if (data.success) {
                document.getElementById('userDetails').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('userModal')).show();
            } else {
                console.error('User details error:', data.message);
                alert('Error loading user details: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            alert('Network error loading user details: ' + error.message);
        });
}

// Edit user status
function editUserStatus(userId) {
    // Get user data from the table row
    const row = event.target.closest('tr');
    const userName = row.cells[1].textContent.trim().split('\n')[0];
    const currentStatus = row.cells[3].textContent.trim();
    
    // Populate modal
    document.getElementById('statusUserId').value = userId;
    document.getElementById('new_status').value = '';
    document.getElementById('reason').value = '';
    
    document.getElementById('statusUserInfo').innerHTML = `
        <div class="card mb-3">
            <div class="card-body">
                <h6>User Information:</h6>
                <p class="mb-1"><strong>Name:</strong> ${userName}</p>
                <p class="mb-0"><strong>Current Status:</strong> <span class="badge bg-secondary">${currentStatus}</span></p>
            </div>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

// Edit KYC status
function editKycStatus(userId) {
    // Get user data from the table row
    const row = event.target.closest('tr');
    const userName = row.cells[1].textContent.trim().split('\n')[0];
    const currentKyc = row.cells[4].textContent.trim();
    
    // Populate modal
    document.getElementById('kycUserId').value = userId;
    document.getElementById('kyc_status').value = '';
    document.getElementById('kyc_notes').value = '';
    
    document.getElementById('kycUserInfo').innerHTML = `
        <div class="card mb-3">
            <div class="card-body">
                <h6>User Information:</h6>
                <p class="mb-1"><strong>Name:</strong> ${userName}</p>
                <p class="mb-0"><strong>Current KYC:</strong> <span class="badge bg-secondary">${currentKyc}</span></p>
            </div>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('kycModal')).show();
}
</script>

<?php include_once 'footer.php'; ?>