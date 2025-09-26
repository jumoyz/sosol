<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';
// Set page title
$pageTitle = "My Campaigns";

// Require authentication
if (!isLoggedIn()) {
    setFlashMessage('error', 'You must be logged in to view your campaigns.');
    redirect('?page=login');
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Initialize variables
$campaigns = [];
$donations = [];
$error = null;

// Get current tab (default to "campaigns")
$activeTab = $_GET['tab'] ?? 'campaigns';
$validTabs = ['campaigns', 'donations'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'campaigns';
}

try {
    $db = getDbConnection();
    
    // Get user's campaigns
    $campaignStmt = $db->prepare("
        SELECT c.*,
               COALESCE((SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id), 0) as donor_count,
               COALESCE((SELECT SUM(d.amount) FROM donations d WHERE d.campaign_id = c.id), 0) as total_raised
        FROM campaigns c
        WHERE c.creator_id = ?
        ORDER BY c.created_at DESC
    ");
    $campaignStmt->execute([$userId]);
    $campaigns = $campaignStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate progress for each campaign
    foreach ($campaigns as &$campaign) {
        $campaign['progress'] = 0;
        if (!empty($campaign['goal_amount']) && $campaign['goal_amount'] > 0) {
            $campaign['progress'] = min(100, round(($campaign['total_raised'] / $campaign['goal_amount']) * 100));
        }
        
        // Calculate days left or days since end
        $campaign['days_left'] = null;
        if (!empty($campaign['end_date'])) {
            $endDate = new DateTime($campaign['end_date']);
            $now = new DateTime();
            $interval = $now->diff($endDate);
            $campaign['days_left'] = $interval->days;
            $campaign['days_passed'] = $interval->invert == 1;
        }
    }
    
    // Get user's donations to others' campaigns
    $donationStmt = $db->prepare("
        SELECT d.*,
               c.title as campaign_title,
               c.image_url as campaign_image,
               c.status as campaign_status,
               u.full_name as creator_name
        FROM donations d
        INNER JOIN campaigns c ON d.campaign_id = c.id
        INNER JOIN users u ON c.creator_id = u.id
        WHERE d.donor_id = ? AND d.status = 'completed'
        ORDER BY d.created_at DESC
    ");
    $donationStmt->execute([$userId]);
    $donations = $donationStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('My campaigns error: ' . $e->getMessage());
    $error = 'An error occurred while loading your campaigns.';
}

// Handle campaign status updates (cancel, pause, resume)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['campaign_action'])) {
    $campaignId = filter_input(INPUT_POST, 'campaign_id', FILTER_UNSAFE_RAW);
    $action = filter_input(INPUT_POST, 'campaign_action', FILTER_UNSAFE_RAW);
    
    $validActions = ['cancel', 'pause', 'resume'];
    if (!in_array($action, $validActions) || empty($campaignId)) {
        setFlashMessage('error', 'Invalid request.');
        redirect('?page=my-campaigns');
        exit;
    }
    
    try {
        $db = getDbConnection();
        
        // Check if user owns this campaign
        $checkStmt = $db->prepare("SELECT id FROM campaigns WHERE id = ? AND creator_id = ?");
        $checkStmt->execute([$campaignId, $userId]);
        
        if ($checkStmt->rowCount() === 0) {
            setFlashMessage('error', 'You do not have permission to modify this campaign.');
            redirect('?page=my-campaigns');
            exit;
        }
        
        // Map action to status
        $newStatus = 'active';
        switch ($action) {
            case 'cancel':
                $newStatus = 'cancelled';
                break;
            case 'pause':
                $newStatus = 'paused';
                break;
            case 'resume':
                $newStatus = 'active';
                break;
        }
        
        // Update campaign status
        $updateStmt = $db->prepare("UPDATE campaigns SET status = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$newStatus, $campaignId]);
        
        // Add activity record
        $activityStmt = $db->prepare("
            INSERT INTO activities (user_id, activity_type, reference_id, details, created_at)
            VALUES (?, 'campaign_updated', ?, ?, NOW())
        ");
        
        $activityStmt->execute([
            $userId,
            $campaignId,
            json_encode(['status' => $newStatus, 'action' => $action])
        ]);
        
        setFlashMessage('success', 'Campaign has been ' . ($action === 'cancel' ? 'cancelled' : ($action === 'pause' ? 'paused' : 'resumed')) . '.');
        redirect('?page=my-campaigns');
        exit;
        
    } catch (PDOException $e) {
        error_log('Campaign status update error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred while updating campaign status.');
        redirect('?page=my-campaigns');
        exit;
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 fw-bold mb-2"><?= $pageTitle ?></h1>
            <p class="text-muted">Manage your crowdfunding campaigns and donations</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="?page=create-campaign" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Create New Campaign
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <!-- Navigation tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'campaigns' ? 'active' : '' ?>" href="?page=my-campaigns&tab=campaigns">
                <i class="fas fa-bullhorn me-2"></i> My Campaigns
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'donations' ? 'active' : '' ?>" href="?page=my-campaigns&tab=donations">
                <i class="fas fa-hand-holding-heart me-2"></i> My Donations
            </a>
        </li>
    </ul>
    
    <?php if ($activeTab === 'campaigns'): ?>
        <!-- My Campaigns Tab -->
        <?php if (empty($campaigns)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-bullhorn text-muted" style="font-size: 3rem;"></i>
                </div>
                <h4>You haven't created any campaigns yet</h4>
                <p class="text-muted">Share your project with the community and start raising funds</p>
                <a href="?page=create-campaign" class="btn btn-primary mt-2">Create Your First Campaign</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($campaigns as $campaign): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="position-relative">
                                <?php if (!empty($campaign['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($campaign['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($campaign['title']) ?>" style="height: 180px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light text-center py-5">
                                        <i class="fas fa-image text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="badge bg-<?= getCampaignStatusBadgeClass($campaign['status']) ?> position-absolute top-0 end-0 m-3">
                                    <?= ucfirst($campaign['status']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title mb-2">
                                    <a href="?page=campaign&id=<?= $campaign['id'] ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($campaign['title']) ?>
                                    </a>
                                </h5>
                                <p class="text-muted small mb-3">
                                    Created on <?= date('M j, Y', strtotime($campaign['created_at'])) ?>
                                </p>
                                
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $campaign['progress'] ?>%" 
                                         aria-valuenow="<?= $campaign['progress'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex justify-content-between small text-muted mb-3">
                                    <span><?= number_format($campaign['total_raised']) ?> HTG raised</span>
                                    <span><?= $campaign['progress'] ?>% of <?= number_format($campaign['goal_amount']) ?> HTG</span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-users me-1"></i> <?= $campaign['donor_count'] ?> donors
                                    </span>
                                    <span class="badge bg-light text-dark">
                                        <?php if ($campaign['days_left'] !== null): ?>
                                            <?php if ($campaign['days_passed']): ?>
                                                <i class="fas fa-calendar-check me-1"></i> Ended <?= $campaign['days_left'] ?> days ago
                                            <?php else: ?>
                                                <i class="fas fa-calendar-alt me-1"></i> <?= $campaign['days_left'] ?> days left
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="?page=campaign&id=<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    
                                    <?php if ($campaign['status'] === 'active' || $campaign['status'] === 'paused'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this campaign?');">
                                            <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                            <input type="hidden" name="campaign_action" value="cancel">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-times-circle me-1"></i> Cancel
                                            </button>
                                        </form>
                                        
                                        <?php if ($campaign['status'] === 'active'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                                <input type="hidden" name="campaign_action" value="pause">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-pause-circle me-1"></i> Pause
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                                <input type="hidden" name="campaign_action" value="resume">
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-play-circle me-1"></i> Resume
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- My Donations Tab -->
        <?php if (empty($donations)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-hand-holding-heart text-muted" style="font-size: 3rem;"></i>
                </div>
                <h4>You haven't made any donations yet</h4>
                <p class="text-muted">Support other community members by donating to their campaigns</p>
                <a href="?page=crowdfunding" class="btn btn-primary mt-2">Browse Campaigns</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Campaign</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donations as $donation): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($donation['campaign_image'])): ?>
                                            <img src="<?= htmlspecialchars($donation['campaign_image']) ?>" class="rounded me-2" width="40" height="40" alt="Campaign" style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-medium"><?= htmlspecialchars($donation['campaign_title']) ?></div>
                                            <div class="text-muted small">by <?= htmlspecialchars($donation['creator_name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <?= date('M j, Y', strtotime($donation['created_at'])) ?>
                                </td>
                                <td class="align-middle">
                                    <span class="fw-bold text-primary"><?= number_format($donation['amount']) ?> HTG</span>
                                </td>
                                <td class="align-middle">
                                    <span class="badge bg-<?= getCampaignStatusBadgeClass($donation['campaign_status']) ?>">
                                        <?= ucfirst($donation['campaign_status']) ?>
                                    </span>
                                </td>
                                <td class="align-middle text-end">
                                    <a href="?page=campaign&id=<?= $donation['campaign_id'] ?>" class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Helper function to determine badge class based on campaign status
function getCampaignStatusBadgeClass($status) {
    switch ($status) {
        case 'active':
            return 'success';
        case 'pending':
            return 'warning';
        case 'paused':
            return 'secondary';
        case 'cancelled':
            return 'danger';
        case 'completed':
            return 'info';
        default:
            return 'secondary';
    }
}
?>