<?php
session_start();

// Temporary debugging - set admin session
if (!isset($_SESSION['is_admin'])) {
    $_SESSION['user_id'] = '1';
    $_SESSION['is_admin'] = true;
    $_SESSION['user_role'] = 'admin';
    echo "<div class='alert alert-info'>Debug: Admin session created</div>";
}

require_once '../includes/config.php';

// Get a test user
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT id, full_name, email FROM users LIMIT 1");
    $test_user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Database error: " . $e->getMessage() . "</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>User Details Test</h2>
        
        <div class="alert alert-info">
            <strong>Test User:</strong> <?= htmlspecialchars($test_user['full_name']) ?> 
            <small>(ID: <?= htmlspecialchars($test_user['id']) ?>)</small>
        </div>
        
        <button class="btn btn-primary" onclick="testUserDetails('<?= $test_user['id'] ?>')">
            Test View User Details
        </button>
        
        <div id="debug-log" class="mt-3"></div>
        
        <!-- Modal -->
        <div class="modal fade" id="userModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">User Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="userDetails">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function log(message) {
        const debugLog = document.getElementById('debug-log');
        debugLog.innerHTML += '<div class="alert alert-info small">' + message + '</div>';
    }
    
    function testUserDetails(userId) {
        log('Starting test for user ID: ' + userId);
        
        fetch('get_user_details.php?id=' + userId)
            .then(response => {
                log('Response status: ' + response.status + ' ' + response.statusText);
                log('Response headers: ' + JSON.stringify([...response.headers]));
                
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.text(); // Get as text first to debug
            })
            .then(text => {
                log('Raw response: ' + text.substring(0, 500) + (text.length > 500 ? '...' : ''));
                
                try {
                    const data = JSON.parse(text);
                    log('Parsed JSON successfully');
                    
                    if (data.success) {
                        document.getElementById('userDetails').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('userModal')).show();
                        log('Modal opened successfully');
                    } else {
                        log('API returned error: ' + data.message);
                        alert('Error: ' + data.message);
                    }
                } catch (e) {
                    log('JSON parse error: ' + e.message);
                    alert('Invalid JSON response: ' + e.message);
                }
            })
            .catch(error => {
                log('Network error: ' + error.message);
                alert('Network error: ' + error.message);
            });
    }
    </script>
</body>
</html>