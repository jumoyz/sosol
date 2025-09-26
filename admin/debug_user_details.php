<?php
// Debug version of get_user_details.php
header('Content-Type: application/json');
session_start();

// Simulate admin session for testing
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['is_admin'] = true;

echo json_encode([
    'debug' => true,
    'session' => $_SESSION,
    'get_params' => $_GET,
    'user_id_received' => $_GET['id'] ?? 'NOT SET'
]);

// Now include the actual file logic
require_once '../includes/config.php';

try {
    $pdo = getDbConnection();
    
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit();
    }
    
    $userId = intval($_GET['id']);
    
    // Simple test query
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode([
            'success' => true,
            'message' => 'User found successfully',
            'user' => $user,
            'html' => '<div class="alert alert-success">User found: ' . htmlspecialchars($user['full_name']) . '</div>'
        ]);
    } else {
        // Check if any users exist
        $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
        $total = $count_stmt->fetchColumn();
        
        echo json_encode([
            'success' => false,
            'message' => 'User not found',
            'debug_info' => [
                'requested_id' => $userId,
                'total_users_in_db' => $total
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>