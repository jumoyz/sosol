<?php
// Simplified get_user_details.php - working version
header('Content-Type: application/json');
session_start();

// Check admin authentication
$userRole = isset($_SESSION['user_role']) ? strtolower(trim((string) $_SESSION['user_role'])) : '';
$isAdminFlag = isset($_SESSION['is_admin']) ? (bool) $_SESSION['is_admin'] : false;
$adminRoleAccepted = in_array($userRole, ['admin', 'super_admin', 'superadmin', 'administrator'], true) || strpos($userRole, 'admin') !== false;

if (!$adminRoleAccepted && $isAdminFlag !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit();
}

try {
    require_once '../includes/config.php';
    $pdo = getDbConnection();
    
    $userId = intval($_GET['id']);
    
    // Simple user query - no complex joins
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Check which columns exist
    $hasKycStatus = array_key_exists('kyc_status', $user);
    $hasKycVerified = array_key_exists('kyc_verified', $user);
    $hasStatusColumn = array_key_exists('status', $user);
    
    // Determine KYC status display
    $kycStatus = 'Unknown';
    $kycBadgeClass = 'bg-secondary';
    
    if ($hasKycStatus && isset($user['kyc_status'])) {
        $kycStatus = ucfirst($user['kyc_status']);
        switch ($user['kyc_status']) {
            case 'verified': $kycBadgeClass = 'bg-success'; break;
            case 'pending': $kycBadgeClass = 'bg-warning'; break;
            case 'rejected': $kycBadgeClass = 'bg-danger'; break;
        }
    } elseif ($hasKycVerified && isset($user['kyc_verified'])) {
        $kycStatus = $user['kyc_verified'] ? 'Verified' : 'Not Verified';
        $kycBadgeClass = $user['kyc_verified'] ? 'bg-success' : 'bg-warning';
    }
    
    // User status
    $userStatus = 'Active'; // Default
    $statusBadgeClass = 'bg-success';
    
    if ($hasStatusColumn && isset($user['status'])) {
        $userStatus = ucfirst($user['status']);
        switch ($user['status']) {
            case 'active': $statusBadgeClass = 'bg-success'; break;
            case 'suspended': $statusBadgeClass = 'bg-danger'; break;
            case 'inactive': $statusBadgeClass = 'bg-warning'; break;
            default: $statusBadgeClass = 'bg-secondary';
        }
    }
    
    // Format dates
    $createdAt = $user['created_at'] ? date('M j, Y \a\t g:i A', strtotime($user['created_at'])) : 'N/A';
    $lastLogin = isset($user['last_login']) && $user['last_login'] ? date('M j, Y \a\t g:i A', strtotime($user['last_login'])) : 'Never';
    
    // Get simple counts (if tables exist)
    $transactionCount = 0;
    $solCount = 0;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $transactionCount = $stmt->fetchColumn();
    } catch (Exception $e) {
        // Transactions table doesn't exist
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sol_participants WHERE user_id = ?");
        $stmt->execute([$userId]);
        $solCount = $stmt->fetchColumn();
    } catch (Exception $e) {
        // SOL participants table doesn't exist
    }
    
    // Generate simple HTML for modal
    $html = '
    <div class="row">
        <div class="col-md-6">
            <h6>Personal Information</h6>
            <table class="table table-sm">
                <tr><td><strong>ID:</strong></td><td>#' . $user['id'] . '</td></tr>
                <tr><td><strong>Full Name:</strong></td><td>' . htmlspecialchars($user['full_name']) . '</td></tr>
                <tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($user['email']) . '</td></tr>
                <tr><td><strong>Phone:</strong></td><td>' . htmlspecialchars($user['phone_number'] ?? 'N/A') . '</td></tr>
                <tr><td><strong>Role:</strong></td><td><span class="badge bg-info">' . htmlspecialchars(ucfirst($user['role'])) . '</span></td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6>Account Status</h6>
            <table class="table table-sm">
                <tr><td><strong>Status:</strong></td><td><span class="badge ' . $statusBadgeClass . '">' . $userStatus . '</span></td></tr>
                <tr><td><strong>KYC Status:</strong></td><td><span class="badge ' . $kycBadgeClass . '">' . $kycStatus . '</span></td></tr>
                <tr><td><strong>Created:</strong></td><td>' . $createdAt . '</td></tr>
                <tr><td><strong>Last Login:</strong></td><td>' . $lastLogin . '</td></tr>
            </table>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-12">
            <h6>Activity Summary</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="card bg-light text-center">
                        <div class="card-body py-2">
                            <h5>' . $transactionCount . '</h5>
                            <small>Transactions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-light text-center">
                        <div class="card-body py-2">
                            <h5>' . $solCount . '</h5>
                            <small>SOL Participations</small>
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