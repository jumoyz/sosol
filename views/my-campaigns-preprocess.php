<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';
// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
    exit;
}

// Get campaign ID from URL
$campaignId = $_GET['id'] ?? null;

if (!$campaignId) {
    setFlashMessage('error', 'Campaign ID is required.');
    redirect('?page=crowdfunding');
    exit;
}

// Initialize variables to be used in the view
$campaign = null;
$creator = null;
$donations = [];
$updates = [];
$userDonation = null;
$similarCampaigns = [];
$walletBalance = 0;
$error = null;

try {
    $db = getDbConnection();
    
    // Get campaign details
    $campaignStmt = $db->prepare("
        SELECT c.*, 
               u.full_name as creator_name,
               u.profile_photo as creator_photo,
               u.email as creator_email,
               (SELECT COUNT(*) FROM donations d WHERE d.campaign_id = c.id) as donor_count,
               (SELECT SUM(d.amount) FROM donations d WHERE d.campaign_id = c.id) as total_raised
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
    
    // Get all the rest of the data needed for the page
    // ... your existing code to fetch wallet, donations, etc.
} catch (PDOException $e) {
    error_log('Campaign details error: ' . $e->getMessage());
    $error = 'An error occurred while loading the campaign details.';
}