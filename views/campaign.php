<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include flash messages
require_once __DIR__ .'/../includes/flash-messages.php';
require_once __DIR__ .'/../includes/config.php';
require_once __DIR__ .'/../includes/functions.php';

// Development mode flag
define('DEV_MODE', true); // Set to false in production

// Set page title
$pageTitle = "Campaign Details";

// Get campaign ID from URL
$campaignId = $_GET['id'] ?? null;

// If no campaign ID is provided, redirect to crowdfunding page
if (!$campaignId) {
    setFlashMessage('error', 'Campaign ID is required.');
    redirect('?page=crowdfunding');
    exit;
}

// Initialize variables
$campaign = null;
$creator = null;
$donations = [];
$updates = [];
$userDonation = null;
$similarCampaigns = [];
$error = null;

// Get current user data
$userId = $_SESSION['user_id'] ?? null;
$isLoggedIn = !empty($userId);

try {
    $db = getDbConnection();
    
    // Get campaign details
    $campaignStmt = $db->prepare("
        SELECT c.*, 
               u.full_name as creator_name,
               u.profile_photo as creator_photo,
               u.bio as creator_bio,
               u.email as creator_email,
               COALESCE((SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id), 0) as donor_count,
               COALESCE((SELECT SUM(d.amount) FROM donations d WHERE d.campaign_id = c.id), 0) as total_raised
        FROM campaigns c
        INNER JOIN users u ON c.creator_id = u.id
        WHERE c.id = ?
    ");
    $campaignStmt->execute([$campaignId]);
    $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        setFlashMessage('error', 'Campaign not found.');
        redirect('?page=crowdfunding');
        exit;
    }
    
    // Calculate progress percentage
    $progress = 0;
    if (!empty($campaign['goal_amount']) && $campaign['goal_amount'] > 0) {
        $progress = min(100, round(($campaign['total_raised'] / $campaign['goal_amount']) * 100));
    }
    
    // Calculate days left
    $daysLeft = 0;
    if (!empty($campaign['end_date'])) {
        $endDate = new DateTime($campaign['end_date']);
        $now = new DateTime();
        if ($endDate > $now) {
            $interval = $now->diff($endDate);
            $daysLeft = $interval->days;
        }
    }
    
    // Get similar campaigns based on category
    $similarStmt = $db->prepare("
        SELECT c.*, 
               u.full_name as creator_name,
               COALESCE((SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id), 0) as donor_count,
               COALESCE((SELECT SUM(d.amount) FROM donations d WHERE d.campaign_id = c.id), 0) as total_raised
        FROM campaigns c
        INNER JOIN users u ON c.creator_id = u.id
        WHERE c.category = ? AND c.id != ? AND c.status = 'active'
        ORDER BY c.created_at DESC
        LIMIT 3
    ");
    $similarStmt->execute([$campaign['category'], $campaignId]);
    $similarCampaigns = $similarStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get campaign updates
    $updatesStmt = $db->prepare("
        SELECT * FROM campaign_updates
        WHERE campaign_id = ?
        ORDER BY created_at DESC
    ");
    $updatesStmt->execute([$campaignId]);
    $updates = $updatesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent donations
    $donationsStmt = $db->prepare("
        SELECT d.*, 
               CASE WHEN d.is_anonymous = 1 THEN 'Anonymous' ELSE u.full_name END as donor_name,
               CASE WHEN d.is_anonymous = 1 THEN NULL ELSE u.profile_photo END as donor_photo
        FROM donations d
        LEFT JOIN users u ON d.donor_id = u.id
        WHERE d.campaign_id = ? AND d.status = 'completed'
        ORDER BY d.created_at DESC
        LIMIT 10
    ");
    $donationsStmt->execute([$campaignId]);
    $donations = $donationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if user has donated to this campaign
    if ($isLoggedIn) {
        $userDonationStmt = $db->prepare("
            SELECT * FROM donations
            WHERE campaign_id = ? AND donor_id = ? AND status = 'completed'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $userDonationStmt->execute([$campaignId, $userId]);
        $userDonation = $userDonationStmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log('Campaign details error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    $error = 'An error occurred while loading the campaign details.';
    
    // For development environment only
    if (defined('DEV_MODE') && DEV_MODE === true) {
        $error .= '<br><small class="text-danger">Error details: ' . htmlspecialchars($e->getMessage()) . '</small>';
    }
}
?>

<div class="container">
    <!-- Back Button -->
    <div class="mb-4">
        <a href="?page=crowdfunding" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to All Campaigns
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php else: ?>
    
    <div class="row">
        <!-- Main Campaign Content -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h1 class="h2 fw-bold mb-3"><?= htmlspecialchars($campaign['title']) ?></h1>
                    
                    <div class="d-flex align-items-center mb-4">
                        <?php if (!empty($campaign['creator_photo'])): ?>
                            <img src="<?= htmlspecialchars($campaign['creator_photo']) ?>" class="rounded-circle me-2" width="40" height="40" alt="<?= htmlspecialchars($campaign['creator_name']) ?>">
                        <?php else: ?>
                            <i class="fas fa-user-circle text-muted me-2" style="font-size: 2rem;"></i>
                        <?php endif; ?>
                        <div>
                            <p class="mb-0">Created by <strong><?= htmlspecialchars($campaign['creator_name']) ?></strong></p>
                            <p class="text-muted small mb-0">
                                Started on <?= date('M j, Y', strtotime($campaign['created_at'])) ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($campaign['image_url'])): ?>
                        <div class="mb-4 text-center">
                            <!--<img src="<?= htmlspecialchars($campaign['image_url']) ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($campaign['title']) ?>"> -->
                            <img src="<?= htmlspecialchars($campaign['image_url'] ?: '/public/uploads/campaigns/crowdfunding-default.jpg') ?>" 
                                onerror="this.onerror=null;this.src='/public/uploads/campaigns/crowdfunding-default.jpg';" 
                                class="img-fluid rounded" 
                                alt="<?= htmlspecialchars($campaign['title']) ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <h5 class="fw-bold mb-0"><?= number_format($campaign['total_raised']) ?> HTG raised</h5>
                            <span class="text-muted">of <?= number_format($campaign['goal_amount']) ?> HTG goal</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%" 
                                 aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 text-muted small">
                            <span><strong><?= $campaign['donor_count'] ?></strong> supporters</span>
                            <?php if ($daysLeft > 0): ?>
                                <span><strong><?= $daysLeft ?></strong> days left</span>
                            <?php else: ?>
                                <span>Campaign ended</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5 class="fw-bold mb-3">About this campaign</h5>
                        <div class="campaign-description">
                            <?= nl2br(htmlspecialchars($campaign['description'])) ?>
                        </div>
                    </div>
                    
                    <?php if (count($updates) > 0): ?>
                    <div class="mb-4">
                        <h5 class="fw-bold mb-3">Campaign Updates</h5>
                        
                        <?php foreach ($updates as $update): ?>
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h6 class="card-title fw-bold"><?= htmlspecialchars($update['title']) ?></h6>
                                <p class="text-muted small mb-2">
                                    Posted on <?= date('M j, Y', strtotime($update['created_at'])) ?>
                                </p>
                                <p class="card-text">
                                    <?= nl2br(htmlspecialchars($update['content'])) ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($userId === $campaign['creator_id']): ?>
                    <div class="mb-4">
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#postUpdateModal">
                            <i class="fas fa-plus-circle me-2"></i> Post Campaign Update
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar - Donation and Campaign Info -->
        <div class="col-lg-4">
            <!-- Donation Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Support this campaign</h5>
                    
                    <?php if ($campaign['status'] === 'active'): ?>
                        <form method="POST" action="?page=campaign&id=<?= $campaignId ?>">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Donation Amount (HTG)</label>
                                <div class="input-group">
                                    <span class="input-group-text">HTG</span>
                                    <input type="number" class="form-control" id="amount" name="amount" min="10" step="10" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Message (Optional)</label>
                                <textarea class="form-control" id="message" name="message" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="anonymous" name="anonymous">
                                <label class="form-check-label" for="anonymous">Donate anonymously</label>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="donate" class="btn btn-primary">
                                    <i class="fas fa-heart me-2"></i> Donate Now
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            This campaign is no longer accepting donations.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Campaign Info Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Campaign Details</h5>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <span>Category:</span>
                                <span class="badge bg-secondary"><?= htmlspecialchars($campaign['category']) ?></span>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <span>Status:</span>
                                <span class="badge bg-<?= $campaign['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($campaign['status']) ?>
                                </span>
                            </div>
                        </li>
                        <li class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <span>Created:</span>
                                <span><?= date('M j, Y', strtotime($campaign['created_at'])) ?></span>
                            </div>
                        </li>
                        <?php if (!empty($campaign['end_date'])): ?>
                        <li class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <span>End Date:</span>
                                <span><?= date('M j, Y', strtotime($campaign['end_date'])) ?></span>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Recent Donors Card -->
            <?php if (count($donations) > 0): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Recent Supporters</h5>
                    
                    <ul class="list-group list-group-flush">
                        <?php foreach ($donations as $donation): ?>
                        <li class="list-group-item px-0">
                            <div class="d-flex align-items-center">
                                <?php if ($donation['is_anonymous'] || empty($donation['donor_photo'])): ?>
                                    <i class="fas fa-user-circle text-muted me-2" style="font-size: 1.5rem;"></i>
                                <?php else: ?>
                                    <img src="<?= htmlspecialchars($donation['donor_photo']) ?>" class="rounded-circle me-2" width="32" height="32" alt="Donor">
                                <?php endif; ?>
                                <div class="ms-2">
                                    <p class="mb-0 fw-bold"><?= htmlspecialchars($donation['donor_name'] ?? '') ?></p>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-primary"><?= number_format($donation['amount']) ?> HTG</span>, 
                                        <small class="text-muted"><?= timeAgo($donation['created_at']) ?></small>
                                    </div>
                                    <?php if (!empty($donation['message'])): ?>
                                    <p class="small text-muted mt-1 mb-0">
                                        "<?= htmlspecialchars($donation['message']) ?>"
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Creator Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">About the Creator</h5>
                    
                    <div class="d-flex align-items-center mb-3">
                        <?php if (!empty($campaign['creator_photo'])): ?>
                            <img src="<?= htmlspecialchars($campaign['creator_photo']) ?>" class="rounded-circle me-3" width="64" height="64" alt="<?= htmlspecialchars($campaign['creator_name']) ?>">
                        <?php else: ?>
                            <i class="fas fa-user-circle text-muted me-3" style="font-size: 3rem;"></i>
                        <?php endif; ?>
                        <div>
                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($campaign['creator_name']) ?></h6>
                            <p class="text-muted small mb-0">Campaign Creator</p>
                        </div>
                    </div>
                    
                    <?php if (!empty($campaign['creator_bio'])): ?>
                    <p class="small mb-0"><?= nl2br(htmlspecialchars($campaign['creator_bio'])) ?></p>
                    <?php else: ?>
                    <p class="text-muted small mb-0">This user has not added a bio yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Similar Campaigns Section -->
    <?php if (count($similarCampaigns) > 0): ?>
    <section class="mb-5">
        <h3 class="fw-bold mb-4">Similar Campaigns</h3>
        <div class="row">
            <?php foreach ($similarCampaigns as $similarCampaign): 
                // Calculate progress for similar campaign
                $similarProgress = 0;
                if (!empty($similarCampaign['goal_amount']) && $similarCampaign['goal_amount'] > 0) {
                    $similarProgress = min(100, round(($similarCampaign['total_raised'] / $similarCampaign['goal_amount']) * 100));
                }
            ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="position-relative">
                            <?php if (!empty($similarCampaign['image_url'])): ?>
                                <!-- <img src="<?= htmlspecialchars($similarCampaign['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($similarCampaign['title']) ?>" style="height: 180px; object-fit: cover;"> -->
                                <img src="<?= htmlspecialchars($campaign['image_url'] ?: '/public/uploads/campaigns/crowdfunding-default.jpg') ?>" 
                                        onerror="this.onerror=null;this.src='/public/uploads/campaigns/crowdfunding-default.jpg';" 
                                        class="card-img-top" 
                                        alt="<?= htmlspecialchars($campaign['title']) ?>" style="height: 180px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light text-center py-5">
                                    <i class="fas fa-image text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            <span class="badge bg-light text-dark position-absolute top-0 end-0 m-3">
                                <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($similarCampaign['category']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="?page=campaign&id=<?= $similarCampaign['id'] ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($similarCampaign['title']) ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted small mb-3">
                                <?= substr(htmlspecialchars($similarCampaign['description'] ?? ''), 0, 80) ?><?= strlen($similarCampaign['description'] ?? '') > 80 ? '...' : '' ?>
                            </p>
                            
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $similarProgress ?>%" 
                                     aria-valuenow="<?= $similarProgress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex justify-content-between text-muted small">
                                <span><?= $similarProgress ?>% funded</span>
                                <span><?= number_format($similarCampaign['total_raised']) ?> HTG</span>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0">
                            <a href="?page=campaign&id=<?= $similarCampaign['id'] ?>" class="btn btn-outline-primary btn-sm d-block">View Campaign</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<!-- Modal for Posting Updates (For Campaign Creator) -->
<div class="modal fade" id="postUpdateModal" tabindex="-1" aria-labelledby="postUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="?page=campaign&id=<?= $campaignId ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="postUpdateModalLabel">Post Campaign Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="update_title" class="form-label">Update Title</label>
                        <input type="text" class="form-control" id="update_title" name="update_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="update_content" class="form-label">Update Content</label>
                        <textarea class="form-control" id="update_content" name="update_content" rows="6" required></textarea>
                        <div class="form-text">Share progress, news, or thank supporters for their contributions.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="post_update" class="btn btn-primary">Post Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add a time ago helper function if not already defined
function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) {
        return interval + " year" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) {
        return interval + " month" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 86400);
    if (interval >= 1) {
        return interval + " day" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 3600);
    if (interval >= 1) {
        return interval + " hour" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 60);
    if (interval >= 1) {
        return interval + " minute" + (interval === 1 ? "" : "s") + " ago";
    }
    
    return "just now";
}
</script>