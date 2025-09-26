<?php
// Direct test of get_user_details.php API without session
header('Content-Type: application/json');

require_once '../includes/config.php';

// Bypass session for testing
$user_id = $_GET['id'] ?? '00000000-0000-0000-0000-000000000001';

try {
    $pdo = getDbConnection();
    
    // Get basic user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Get wallet balances
    $wallet_stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN currency = 'HTG' THEN balance ELSE 0 END), 0) as balance_htg,
            COALESCE(SUM(CASE WHEN currency = 'USD' THEN balance ELSE 0 END), 0) as balance_usd
        FROM wallets 
        WHERE user_id = ?
    ");
    $wallet_stmt->execute([$user_id]);
    $wallet_data = $wallet_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get counts
    $trans_stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
    $trans_stmt->execute([$user_id]);
    $transaction_count = $trans_stmt->fetchColumn();
    
    $sol_stmt = $pdo->prepare("SELECT COUNT(*) FROM sol_participants WHERE user_id = ?");
    $sol_stmt->execute([$user_id]);
    $sol_count = $sol_stmt->fetchColumn();
    
    // Format HTML
    $html = '
    <div class="row">
        <div class="col-md-6">
            <h6 class="fw-bold mb-3">Personal Information</h6>
            <table class="table table-sm table-borderless">
                <tr><td class="fw-semibold">Full Name:</td><td>' . htmlspecialchars($user['full_name']) . '</td></tr>
                <tr><td class="fw-semibold">Email:</td><td>' . htmlspecialchars($user['email']) . '</td></tr>
                <tr><td class="fw-semibold">Phone:</td><td>' . htmlspecialchars($user['phone_number'] ?? 'N/A') . '</td></tr>
                <tr><td class="fw-semibold">Role:</td><td><span class="badge bg-info">' . ucfirst($user['role']) . '</span></td></tr>
                <tr><td class="fw-semibold">KYC:</td><td><span class="badge bg-' . ($user['kyc_verified'] ? 'success' : 'warning') . '">' . ($user['kyc_verified'] ? 'Verified' : 'Pending') . '</span></td></tr>
                <tr><td class="fw-semibold">Joined:</td><td>' . date('F j, Y', strtotime($user['created_at'])) . '</td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6 class="fw-bold mb-3">Account Summary</h6>
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <h6 class="card-title">Wallet Balance</h6>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="fw-bold fs-5">' . number_format($wallet_data['balance_htg'] ?? 0, 2) . '</div>
                            <small class="text-muted">HTG</small>
                        </div>
                        <div class="col-6">
                            <div class="fw-bold fs-5">' . number_format($wallet_data['balance_usd'] ?? 0, 2) . '</div>
                            <small class="text-muted">USD</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <div class="card bg-primary-subtle">
                        <div class="card-body text-center py-2">
                            <div class="fw-bold">' . $transaction_count . '</div>
                            <small class="text-muted">Transactions</small>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card bg-success-subtle">
                        <div class="card-body text-center py-2">
                            <div class="fw-bold">' . $sol_count . '</div>
                            <small class="text-muted">SOL Groups</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>