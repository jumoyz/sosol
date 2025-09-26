<?php
// Include flash messages
require_once __DIR__ .'/../includes/flash-messages.php';
// Preprocess file for campaign page - handles form submissions before headers are sent

// Only process if this is a campaign page request
if ($page !== 'campaign') {
    return;
}

// Get campaign ID from URL
$campaignId = $_GET['id'] ?? null;

// If no campaign ID is provided, redirect to crowdfunding page
if (!$campaignId) {
    setFlashMessage('error', 'Campaign ID is required.');
    redirect('?page=crowdfunding');
    exit;
}

// Handle donation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['donate'])) {
    // Ensure user is logged in
    if (!isLoggedIn()) {
        // Save current URL to redirect back after login
        $_SESSION['redirect_after_login'] = '?page=campaign&id=' . $campaignId;
        redirect('?page=login');
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $message = filter_input(INPUT_POST, 'message', FILTER_UNSAFE_RAW);
    $message = htmlspecialchars($message ?? '', ENT_QUOTES, 'UTF-8');
    $isAnonymous = isset($_POST['anonymous']) ? 1 : 0;
    
    $errors = [];
    
    if (!$amount || $amount <= 0) {
        $errors[] = 'Please enter a valid donation amount.';
    }
    
    if (empty($errors)) {
        try {
            $db = getDbConnection();
            
            // Get campaign details first
            $campaignStmt = $db->prepare("SELECT * FROM campaigns WHERE id = ?");
            $campaignStmt->execute([$campaignId]);
            $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$campaign) {
                setFlashMessage('error', 'Campaign not found.');
                redirect('?page=crowdfunding');
                exit;
            }
            
            // Verify campaign is still active and accepting donations
            if ($campaign['status'] !== 'active') {
                setFlashMessage('error', 'This campaign is no longer accepting donations.');
                redirect('?page=campaign&id=' . $campaignId);
                exit;
            }
            
            // Check if campaign end date has passed
            if (!empty($campaign['end_date'])) {
                $endDate = new DateTime($campaign['end_date']);
                $now = new DateTime();
                if ($endDate <= $now) {
                    setFlashMessage('error', 'This campaign has ended and is no longer accepting donations.');
                    redirect('?page=campaign&id=' . $campaignId);
                    exit;
                }
            }
            
            // Verify user exists and get user wallet balance
            $walletStmt = $db->prepare("
                SELECT w.id, w.balance_htg 
                FROM wallets w
                INNER JOIN users u ON w.user_id = u.id
                WHERE u.id = ?
            ");
            $walletStmt->execute([$userId]);
            $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$wallet) {
                setFlashMessage('error', 'Wallet not found. Please contact support.');
                redirect('?page=campaign&id=' . $campaignId);
                exit;
            }
            
            if ($wallet['balance_htg'] < $amount) {
                setFlashMessage('error', 'Insufficient funds in your wallet. Please add funds before donating.');
                redirect('?page=campaign&id=' . $campaignId);
                exit;
            }
            
            $db->beginTransaction();
            
            // Create donation record
            $donationId = generateUuid();
            $donationStmt = $db->prepare("
                INSERT INTO donations 
                (id, campaign_id, donor_id, amount, message, is_anonymous, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $donationStmt->execute([
                $donationId, $campaignId, $userId, $amount, $message, $isAnonymous
            ]);
            
            // Update wallet balance
            $updateWalletStmt = $db->prepare("
                UPDATE wallets SET balance_htg = balance_htg - ?, updated_at = NOW() WHERE id = ?
            ");
            $updateWalletStmt->execute([$amount, $wallet['id']]);
            
            // Create transaction record
            $txnId = generateUuid();
            $txnStmt = $db->prepare("
                INSERT INTO transactions 
                (id, wallet_id, type, amount, currency, status, reference_id, provider, created_at)
                VALUES (?, ?, 'donation', ?, 'HTG', 'completed', ?, 'campaign_system', NOW())
            ");
            $txnStmt->execute([
                $txnId, $wallet['id'], $amount, $donationId
            ]);
            
            // Add activity record
            try {
                $activityStmt = $db->prepare("
                    INSERT INTO activities 
                    (user_id, activity_type, reference_id, details, created_at)
                    VALUES (?, 'donation', ?, ?, NOW())
                ");
                $activityStmt->execute([
                    $userId, 
                    $campaignId, 
                    json_encode([
                        'campaign_title' => $campaign['title'],
                        'amount' => $amount,
                        'is_anonymous' => $isAnonymous
                    ])
                ]);
            } catch (Exception $e) {
                // Activity logging is non-critical, just log the error
                error_log('Activity logging failed for donation ' . $donationId . ': ' . $e->getMessage());
            }
            
            $db->commit();
            
            setFlashMessage('success', 'Thank you for your donation of ' . number_format($amount) . ' HTG!');
            redirect('?page=campaign&id=' . $campaignId);
            exit;
            
        } catch (PDOException $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            error_log('Donation error for campaign ' . $campaignId . ', user ' . $userId . ': ' . $e->getMessage());
            
            // More detailed error for development
            if (defined('DEV_MODE') && DEV_MODE === true) {
                setFlashMessage('error', 'Donation failed: ' . $e->getMessage());
            } else {
                setFlashMessage('error', 'An error occurred while processing your donation. Please try again.');
            }
            
            redirect('?page=campaign&id=' . $campaignId);
            exit;
        } catch (Exception $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            error_log('General donation error for campaign ' . $campaignId . ', user ' . $userId . ': ' . $e->getMessage());
            
            // More detailed error for development
            if (defined('DEV_MODE') && DEV_MODE === true) {
                setFlashMessage('error', 'Donation failed: ' . $e->getMessage());
            } else {
                setFlashMessage('error', 'An error occurred while processing your donation. Please try again.');
            }
            
            redirect('?page=campaign&id=' . $campaignId);
            exit;
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
        redirect('?page=campaign&id=' . $campaignId);
        exit;
    }
}

// Handle creating an update (for campaign creator)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_update'])) {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Please log in to post updates.');
        redirect('?page=login');
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    // Get campaign to check creator permissions
    try {
        $db = getDbConnection();
        $campaignStmt = $db->prepare("SELECT creator_id FROM campaigns WHERE id = ?");
        $campaignStmt->execute([$campaignId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$campaign || $userId != $campaign['creator_id']) {
            setFlashMessage('error', 'Only the campaign creator can post updates.');
            redirect('?page=campaign&id=' . $campaignId);
            exit;
        }
        
        $title = filter_input(INPUT_POST, 'update_title', FILTER_UNSAFE_RAW);
        $title = htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8');
        $content = filter_input(INPUT_POST, 'update_content', FILTER_UNSAFE_RAW);
        $content = htmlspecialchars($content ?? '', ENT_QUOTES, 'UTF-8');
        
        if (!empty($title) && !empty($content)) {
            $updateId = generateUuid();
            $updateStmt = $db->prepare("
                INSERT INTO campaign_updates 
                (id, campaign_id, title, content, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $updateStmt->execute([$updateId, $campaignId, $title, $content]);
            
            setFlashMessage('success', 'Campaign update posted successfully.');
        } else {
            setFlashMessage('error', 'Please provide both title and content for the update.');
        }
        
    } catch (Exception $e) {
        error_log('Campaign update error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred while posting the update.');
    }
    
    redirect('?page=campaign&id=' . $campaignId);
    exit;
}
?>
