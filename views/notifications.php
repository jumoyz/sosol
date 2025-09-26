<?php
// Set page title
$pageTitle = "Notifications";

// Require authentication
if (!isLoggedIn()) {
    setFlashMessage('error', 'You must be logged in to view notifications.');
    redirect('?page=login');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];

// Initialize variables
$notifications = [];
$unreadCount = 0;
$error = null;
$success = null;

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_UNSAFE_RAW);
    
    try {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $userId]);
        
    } catch (PDOException $e) {
        error_log('Mark notification read error: ' . $e->getMessage());
    }
    
    // Redirect to prevent form resubmission
    redirect('?page=notifications');
    exit;
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        
        $success = 'All notifications marked as read.';
        
    } catch (PDOException $e) {
        error_log('Mark all notifications read error: ' . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}

// Handle delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_UNSAFE_RAW);
    
    try {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            DELETE FROM notifications 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $userId]);
        
        $success = 'Notification deleted.';
        
    } catch (PDOException $e) {
        error_log('Delete notification error: ' . $e->getMessage());
        $error = 'An error occurred while deleting the notification.';
    }
}

// Helper function to create notifications table if it doesn't exist
function createNotificationsTableIfNeeded($db) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id CHAR(36) NOT NULL PRIMARY KEY,
                user_id CHAR(36) NOT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(100) NOT NULL,
                message TEXT NOT NULL,
                reference_id CHAR(36) NULL,
                reference_type VARCHAR(50) NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");
        return true;
    } catch (PDOException $e) {
        error_log('Create notifications table error: ' . $e->getMessage());
        return false;
    }
}

// Get user's notifications
try {
    $db = getDbConnection();
    
    // Check if notifications table exists and create if needed
    $tableCheck = $db->query("SHOW TABLES LIKE 'notifications'");
    if ($tableCheck->rowCount() === 0) {
        createNotificationsTableIfNeeded($db);
    }
    
    // Get unread count
    $countStmt = $db->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $countStmt->execute([$userId]);
    $unreadCount = $countStmt->fetchColumn();
    
    // Get notifications with pagination
    $page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    
    $stmt = $db->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindParam(1, $userId);
    $stmt->bindParam(2, $perPage, PDO::PARAM_INT);
    $stmt->bindParam(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $totalStmt = $db->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE user_id = ?
    ");
    $totalStmt->execute([$userId]);
    $totalItems = $totalStmt->fetchColumn();
    $totalPages = ceil($totalItems / $perPage);
    
} catch (PDOException $e) {
    error_log('Notifications error: ' . $e->getMessage());
    $error = 'An error occurred while loading your notifications.';
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 fw-bold"><?= $pageTitle ?></h1>
            <p class="text-muted">Stay updated with important information and activity on your account</p>
        </div>
        <div class="col-md-4 text-md-end">
            <?php if ($unreadCount > 0): ?>
                <form method="POST" class="d-inline">
                    <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                        <i class="fas fa-check-double me-2"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell text-muted mb-3" style="font-size: 3rem;"></i>
                    <h4>No notifications yet</h4>
                    <p class="text-muted">You don't have any notifications at the moment.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item list-group-item-action p-4 <?= $notification['is_read'] ? '' : 'bg-light' ?>">
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <div class="notification-icon <?= getNotificationIconClass($notification['type']) ?>">
                                        <i class="<?= getNotificationIcon($notification['type']) ?>"></i>
                                    </div>
                                </div>
                                <div class="ms-3 flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($notification['title']) ?></h6>
                                        <div class="dropdown">
                                            <button class="btn btn-sm text-muted" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php if (!$notification['is_read']): ?>
                                                    <li>
                                                        <form method="POST">
                                                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                            <button type="submit" name="mark_read" class="dropdown-item">
                                                                <i class="fas fa-check me-2"></i> Mark as Read
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this notification?');">
                                                        <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                        <button type="submit" name="delete_notification" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash-alt me-2"></i> Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <p class="mb-2"><?= htmlspecialchars($notification['message']) ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><?= getTimeAgo($notification['created_at']) ?></small>
                                        
                                        <?php if (!empty($notification['reference_id']) && !empty($notification['reference_type'])): ?>
                                            <a href="<?= getNotificationLink($notification) ?>" class="btn btn-sm btn-outline-primary">
                                                View Details
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <div class="p-3">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=notifications&p=<?= ($page - 1) ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="fas fa-chevron-left"></i> Previous</span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=notifications&p=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=notifications&p=<?= ($page + 1) ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">Next <i class="fas fa-chevron-right"></i></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Notification Settings</h5>
            <p class="text-muted mb-3">
                You can manage your notification preferences in your profile settings.
            </p>
            <a href="?page=profile" class="btn btn-outline-primary">
                <i class="fas fa-cog me-2"></i> Manage Notification Settings
            </a>
        </div>
    </div>
</div>

<style>
.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-icon.bg-primary {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
}

.notification-icon.bg-success {
    background-color: rgba(25, 135, 84, 0.1);
    color: #198754;
}

.notification-icon.bg-warning {
    background-color: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.notification-icon.bg-danger {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.notification-icon.bg-info {
    background-color: rgba(13, 202, 240, 0.1);
    color: #0dcaf0;
}
</style>

<?php
// Helper functions
function getNotificationIcon($type) {
    switch ($type) {
        case 'transfer':
            return 'fas fa-exchange-alt';
        case 'deposit':
            return 'fas fa-plus-circle';
        case 'withdrawal':
            return 'fas fa-minus-circle';
        case 'loan':
            return 'fas fa-hand-holding-usd';
        case 'system':
            return 'fas fa-cog';
        case 'campaign':
            return 'fas fa-bullhorn';
        case 'security':
            return 'fas fa-shield-alt';
        default:
            return 'fas fa-bell';
    }
}

function getNotificationIconClass($type) {
    switch ($type) {
        case 'transfer':
            return 'bg-primary';
        case 'deposit':
            return 'bg-success';
        case 'withdrawal':
            return 'bg-warning';
        case 'loan':
            return 'bg-info';
        case 'system':
            return 'bg-secondary';
        case 'campaign':
            return 'bg-primary';
        case 'security':
            return 'bg-danger';
        default:
            return 'bg-primary';
    }
}

function getNotificationLink($notification) {
    switch ($notification['reference_type']) {
        case 'transaction':
            return '?page=wallet&transaction=' . $notification['reference_id'];
        case 'campaign':
            return '?page=campaign&id=' . $notification['reference_id'];
        case 'loan':
            return '?page=loan-center&loan=' . $notification['reference_id'];
        default:
            return '#';
    }
}

function getTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = round($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>