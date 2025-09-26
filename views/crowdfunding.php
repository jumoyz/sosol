
<?php
// Set page title
$pageTitle = "Crowdfunding Campaigns";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$featuredCampaigns = [];
$allCampaigns = [];
$categories = [];
$userDonations = [];
$error = null;

try {
    $db = getDbConnection();
    
    // Get featured campaigns
    $featuredStmt = $db->prepare("
        SELECT c.*, 
               u.full_name as creator_name,
               u.profile_photo as creator_photo,
               (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id) as donor_count,
               (SELECT SUM(d.amount) FROM donations d WHERE d.campaign_id = c.id) as total_raised
        FROM campaigns c
        INNER JOIN users u ON c.creator_id = u.id
        WHERE c.status = 'active' AND c.is_featured = 1
        ORDER BY c.created_at DESC
        LIMIT 3
    ");
    $featuredStmt->execute();
    $featuredCampaigns = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all active campaigns
    $campaignStmt = $db->prepare("
        SELECT c.*, 
               u.full_name as creator_name,
               (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id) as donor_count,
               (SELECT SUM(d.amount) FROM donations d WHERE d.campaign_id = c.id) as total_raised
        FROM campaigns c
        INNER JOIN users u ON c.creator_id = u.id
        WHERE c.status = 'active'
        ORDER BY c.created_at DESC
        LIMIT 12
    ");
    $campaignStmt->execute();
    $allCampaigns = $campaignStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get campaign categories
    $categoryStmt = $db->prepare("
        SELECT DISTINCT category 
        FROM campaigns 
        WHERE status = 'active' 
        ORDER BY category
    ");
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get user's donations
    $donationStmt = $db->prepare("
        SELECT d.*, c.title as campaign_title, c.id as campaign_id
        FROM donations d
        INNER JOIN campaigns c ON d.campaign_id = c.id
        WHERE d.donor_id = ?
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    $donationStmt->execute([$userId]);
    $userDonations = $donationStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Crowdfunding data error: ' . $e->getMessage());
    $error = 'An error occurred while loading campaigns.';
}

// Handle search form submission
$searchTerm = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchTerm = filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW);
    $searchTerm = htmlspecialchars(trim($searchTerm), ENT_QUOTES, 'UTF-8');
    
    if (!empty($searchTerm)) {
        try {
            // Search campaigns
            $searchStmt = $db->prepare("
                SELECT c.*, 
                       u.full_name as creator_name,
                       (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id) as donor_count,
                       (SELECT SUM(d.amount) FROM donations d WHERE d.campaign_id = c.id) as total_raised
                FROM campaigns c
                INNER JOIN users u ON c.creator_id = u.id
                WHERE c.status = 'active' AND (c.title LIKE ? OR c.description LIKE ?)
                ORDER BY c.created_at DESC
                LIMIT 12
            ");
            $searchStmt->execute(["%$searchTerm%", "%$searchTerm%"]);
            $allCampaigns = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Campaign search error: ' . $e->getMessage());
            $error = 'An error occurred while searching campaigns.';
        }
    }
}

// Filter by category
$selectedCategory = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['category'])) {
    $selectedCategory = filter_input(INPUT_GET, 'category', FILTER_UNSAFE_RAW);
    $selectedCategory = htmlspecialchars(trim($selectedCategory), ENT_QUOTES, 'UTF-8');
    
    if (!empty($selectedCategory)) {
        try {
            // Filter campaigns by category
            $categoryFilterStmt = $db->prepare("
                SELECT c.*, 
                       u.full_name as creator_name,
                       (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id) as donor_count,
                       (SELECT SUM(d.amount) FROM donations d WHERE d.campaign_id = c.id) as total_raised
                FROM campaigns c
                INNER JOIN users u ON c.creator_id = u.id
                WHERE c.status = 'active' AND c.category = ?
                ORDER BY c.created_at DESC
                LIMIT 12
            ");
            $categoryFilterStmt->execute([$selectedCategory]);
            $allCampaigns = $categoryFilterStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Category filter error: ' . $e->getMessage());
            $error = 'An error occurred while filtering campaigns.';
        }
    }
}
?>

<!-- Hero Section -->
<div class="bg-primary-subtle py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h1 class="display-5 fw-bold mb-3">Support Community Projects</h1>
                <p class="lead mb-4">Discover campaigns that matter, fund projects that make a difference, and help build a better Haiti together.</p>
                <div class="d-flex gap-2">
                    <a href="#campaigns" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i> Find Campaigns
                    </a>
                    <a href="?page=create-campaign" class="btn btn-outline-primary">
                        <i class="fas fa-plus-circle me-2"></i> Start a Campaign
                    </a>
                </div>
            </div>
            <div class="col-lg-6 text-center text-lg-end">
                <img src="/public/images/crowdfunding-hero.jpg" alt="Crowdfunding" class="img-fluid" style="max-height: 300px;">
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <!-- Featured Campaigns -->
    <?php if (!empty($featuredCampaigns)): ?>
    <section class="mb-5">
        <h2 class="fw-bold mb-4">Featured Campaigns</h2>
        <div class="row">
            <?php foreach ($featuredCampaigns as $campaign): ?>
                <?php 
                $progress = 0;
                if (!empty($campaign['goal_amount']) && $campaign['goal_amount'] > 0 && !empty($campaign['total_raised'])) {
                    $progress = min(100, round(($campaign['total_raised'] / $campaign['goal_amount']) * 100));
                }
                $daysLeft = 0;
                if (!empty($campaign['end_date'])) {
                    $endDate = new DateTime($campaign['end_date']);
                    $now = new DateTime();
                    $interval = $now->diff($endDate);
                    $daysLeft = $interval->days;
                    if ($endDate < $now) {
                        $daysLeft = 0;
                    }
                }
                ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 shadow-sm border-0 featured-campaign-card">
                        <div class="position-relative">
                            <?php if (!empty($campaign['image_url'])): ?>
                                <img src="<?= htmlspecialchars($campaign['image_url']) ?>" class="card-img-top campaign-img" alt="<?= htmlspecialchars($campaign['title']) ?>">
                            <?php else: ?>
                                <div class="bg-light text-center py-5 campaign-img">
                                    <i class="fas fa-image text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            <span class="badge bg-primary position-absolute top-0 start-0 m-3">Featured</span>
                            <span class="badge bg-light text-dark position-absolute top-0 end-0 m-3">
                                <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($campaign['category']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title mb-2">
                                <a href="?page=campaign&id=<?= $campaign['id'] ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($campaign['title']) ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted mb-3">
                                <?= substr(htmlspecialchars($campaign['description']), 0, 100) ?>...
                            </p>
                            
                            <div class="d-flex align-items-center mb-3">
                                <?php if (!empty($campaign['creator_photo'])): ?>
                                    <img src="<?= htmlspecialchars($campaign['creator_photo']) ?>" class="rounded-circle me-2" width="24" height="24" alt="Creator">
                                <?php else: ?>
                                    <i class="fas fa-user-circle text-muted me-2" style="font-size: 1.5rem;"></i>
                                <?php endif; ?>
                                <small class="text-muted">By <?= htmlspecialchars($campaign['creator_name']) ?></small>
                            </div>
                            
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%" 
                                     aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0"><?= number_format($campaign['total_raised'] ?? 0) ?> HTG</h6>
                                    <small class="text-muted">raised of <?= number_format($campaign['goal_amount'] ?? 0) ?> HTG</small>
                                </div>
                                <div class="text-end">
                                    <h6 class="fw-bold mb-0"><?= $progress ?>%</h6>
                                    <small class="text-muted"><?= $daysLeft ?> days left</small>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i> <?= $campaign['donor_count'] ?? 0 ?> donors
                                </small>
                                <a href="?page=campaign&id=<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    View Campaign
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Search and Filters -->
    <section class="mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="GET" action="?page=crowdfunding" class="row g-3">
                    <input type="hidden" name="page" value="crowdfunding">
                    
                    <div class="col-lg-6 col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" name="search" placeholder="Search campaigns..." value="<?= htmlspecialchars($searchTerm) ?>">
                        </div>
                    </div>
                    
                    <div class="col-lg-4 col-md-4">
                        <select class="form-select" name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" <?= ($selectedCategory === $category) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
    
    <!-- All Campaigns -->
    <section id="campaigns" class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">All Campaigns</h2>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-sort me-1"></i> Sort By
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sortDropdown">
                    <li><a class="dropdown-item" href="#">Newest First</a></li>
                    <li><a class="dropdown-item" href="#">Ending Soon</a></li>
                    <li><a class="dropdown-item" href="#">Most Funded</a></li>
                    <li><a class="dropdown-item" href="#">Trending</a></li>
                </ul>
            </div>
        </div>
        
        <?php if (empty($allCampaigns)): ?>
            <div class="alert alert-info">
                <?php if (!empty($searchTerm) || !empty($selectedCategory)): ?>
                    No campaigns found matching your search criteria. <a href="?page=crowdfunding" class="alert-link">Clear filters</a>
                <?php else: ?>
                    No active campaigns available at the moment. Check back soon!
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($allCampaigns as $campaign): ?>
                    <?php 
                    $progress = 0;
                    if (!empty($campaign['goal_amount']) && $campaign['goal_amount'] > 0 && !empty($campaign['total_raised'])) {
                        $progress = min(100, round(($campaign['total_raised'] / $campaign['goal_amount']) * 100));
                    }
                    $daysLeft = 0;
                    if (!empty($campaign['end_date'])) {
                        $endDate = new DateTime($campaign['end_date']);
                        $now = new DateTime();
                        $interval = $now->diff($endDate);
                        $daysLeft = $interval->days;
                        if ($endDate < $now) {
                            $daysLeft = 0;
                        }
                    }
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="position-relative">
                                <?php if (!empty($campaign['image_url'])): ?>
                                    <!-- <img src="<?= htmlspecialchars($campaign['image_url']) ?>" class="card-img-top campaign-img" alt="<?= htmlspecialchars($campaign['title']) ?>"> -->
                                    <img src="<?= htmlspecialchars($campaign['image_url'] ?: '/public/uploads/campaigns/crowdfunding-default.jpg') ?>" 
                                        onerror="this.onerror=null;this.src='/public/uploads/campaigns/crowdfunding-default.jpg';" 
                                        class="card-img-top campaign-img" 
                                        alt="<?= htmlspecialchars($campaign['title']) ?>">
                                <?php else: ?>
                                    <div class="bg-light text-center py-5 campaign-img">
                                        <i class="fas fa-image text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="badge bg-light text-dark position-absolute top-0 end-0 m-2">
                                    <i class="fas fa-tag me-1"></i> <?= htmlspecialchars($campaign['category']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="?page=campaign&id=<?= $campaign['id'] ?>" class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($campaign['title']) ?>
                                    </a>
                                </h5>
                                
                                <p class="card-text text-muted small mb-3">
                                    <?= substr(htmlspecialchars($campaign['description']), 0, 80) ?>...
                                </p>
                                
                                <div class="progress mb-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%" 
                                         aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2 small">
                                    <div>
                                        <strong><?= number_format($campaign['total_raised'] ?? 0) ?> HTG</strong>
                                        <div class="text-muted">raised</div>
                                    </div>
                                    <div class="text-end">
                                        <strong><?= $progress ?>%</strong>
                                        <div class="text-muted"><?= $daysLeft ?> days left</div>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <a href="?page=campaign&id=<?= $campaign['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Campaign pagination">
                    <ul class="pagination">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </section>
    
    <!-- User Donations and Start Campaign Section -->
    <div class="row">
        <!-- User Donations -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0">
                    <h4 class="fw-bold mb-0">Your Donations</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($userDonations)): ?>
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-donate text-muted" style="font-size: 3rem;"></i>
                            </div>
                            <p class="text-muted mb-3">You haven't made any donations yet.</p>
                            <p class="mb-0">Support a campaign today and make a difference!</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($userDonations as $donation): ?>
                                <div class="list-group-item border-0 px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <a href="?page=campaign&id=<?= $donation['campaign_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($donation['campaign_title']) ?>
                                                </a>
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                Donated on <?= date('M j, Y', strtotime($donation['created_at'])) ?>
                                            </p>
                                        </div>
                                        <span class="badge bg-success rounded-pill">
                                            <?= number_format($donation['amount'], 2) ?> HTG
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="?page=donation-history" class="btn btn-sm btn-outline-primary">View All Donations</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Start a Campaign -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100 bg-primary-subtle">
                <div class="card-body p-4 text-center">
                    <div class="py-4">
                        <div class="mb-4">
                            <i class="fas fa-lightbulb text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-3">Have a Project Idea?</h3>
                        <p class="mb-4">
                            Start your campaign today and turn your community project into reality with the support of others.
                        </p>
                        <a href="?page=create-campaign" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-2"></i> Start a Campaign
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- How It Works -->
    <section class="mb-5">
        <h2 class="fw-bold mb-4 text-center">How Crowdfunding Works</h2>
        <div class="row text-center">
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <div class="p-4">
                    <div class="icon-circle mx-auto mb-3 bg-primary-subtle">
                        <i class="fas fa-lightbulb text-primary"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Create</h5>
                    <p class="text-muted">Start a campaign with your project idea and set a funding goal</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                <div class="p-4">
                    <div class="icon-circle mx-auto mb-3 bg-primary-subtle">
                        <i class="fas fa-share-alt text-primary"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Share</h5>
                    <p class="text-muted">Spread the word about your campaign to friends and community</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                <div class="p-4">
                    <div class="icon-circle mx-auto mb-3 bg-primary-subtle">
                        <i class="fas fa-donate text-primary"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Fund</h5>
                    <p class="text-muted">Collect donations from supporters who believe in your cause</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="p-4">
                    <div class="icon-circle mx-auto mb-3 bg-primary-subtle">
                        <i class="fas fa-check-circle text-primary"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Implement</h5>
                    <p class="text-muted">Use the funds to bring your project to life and make an impact</p>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
.featured-campaign-card {
    transition: all 0.2s ease;
}

.featured-campaign-card:hover {
    transform: translateY(-5px);
}

.campaign-img {
    height: 180px;
    object-fit: cover;
}

.icon-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}
</style>