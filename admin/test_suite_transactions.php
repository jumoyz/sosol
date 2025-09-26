<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management Test Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2>🧪 Transaction Management Test Suite</h2>
                        <p class="mb-0">Comprehensive testing of all transaction management features</p>
                    </div>
                    <div class="card-body">
                        
                        <?php
                        session_start();
                        require_once '../includes/config.php';
                        require_once '../includes/functions.php';
                        
                        // Create admin session if not exists
                        if (!isset($_SESSION['admin_id'])) {
                            $_SESSION['admin_id'] = 1;
                            $_SESSION['admin_username'] = 'test_admin';
                            $_SESSION['admin_email'] = 'admin@test.com';
                            $_SESSION['admin_role'] = 'admin';
                            echo "<div class='alert alert-info'>✅ Admin session created</div>";
                        }
                        
                        $tests = [];
                        
                        // Test 1: Database Connection
                        try {
                            $conn = getDbConnection();
                            $tests['db_connection'] = ['status' => 'pass', 'message' => 'Database connection successful'];
                        } catch (Exception $e) {
                            $tests['db_connection'] = ['status' => 'fail', 'message' => 'Database connection failed: ' . $e->getMessage()];
                        }
                        
                        // Test 2: Transaction Count
                        try {
                            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions");
                            $stmt->execute();
                            $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            $tests['transaction_count'] = ['status' => 'pass', 'message' => "Found $count transactions in database"];
                        } catch (Exception $e) {
                            $tests['transaction_count'] = ['status' => 'fail', 'message' => 'Failed to count transactions: ' . $e->getMessage()];
                        }
                        
                        // Test 3: Transaction Query with User Join
                        try {
                            $stmt = $conn->prepare("
                                SELECT t.*, u.first_name, u.last_name, u.email, u.phone 
                                FROM transactions t 
                                LEFT JOIN users u ON t.user_id = u.id 
                                LIMIT 5
                            ");
                            $stmt->execute();
                            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            $tests['transaction_query'] = ['status' => 'pass', 'message' => 'Transaction query with user join successful - ' . count($results) . ' records'];
                        } catch (Exception $e) {
                            $tests['transaction_query'] = ['status' => 'fail', 'message' => 'Transaction query failed: ' . $e->getMessage()];
                        }
                        
                        // Test 4: Transaction Statistics
                        try {
                            $stmt = $conn->prepare("
                                SELECT 
                                    COUNT(*) as total_transactions,
                                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                                    ROUND(SUM(CASE WHEN status = 'completed' AND currency = 'HTG' THEN amount ELSE 0 END), 2) as total_htg,
                                    ROUND(SUM(CASE WHEN status = 'completed' AND currency = 'USD' THEN amount ELSE 0 END), 2) as total_usd
                                FROM transactions
                            ");
                            $stmt->execute();
                            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                            $tests['transaction_stats'] = ['status' => 'pass', 'message' => 'Statistics calculated successfully', 'data' => $stats];
                        } catch (Exception $e) {
                            $tests['transaction_stats'] = ['status' => 'fail', 'message' => 'Statistics calculation failed: ' . $e->getMessage()];
                        }
                        
                        // Test 5: Main Page Response
                        try {
                            $url = 'http://sosol.local/admin/transactions.php';
                            $context = stream_context_create([
                                'http' => [
                                    'method' => 'GET',
                                    'header' => 'Cookie: ' . session_name() . '=' . session_id()
                                ]
                            ]);
                            $response = file_get_contents($url, false, $context);
                            $has_table = strpos($response, '<table') !== false;
                            $has_filters = strpos($response, 'status-filter') !== false;
                            $tests['main_page'] = ['status' => $has_table && $has_filters ? 'pass' : 'fail', 
                                                  'message' => $has_table && $has_filters ? 'Main page loads with table and filters' : 'Main page missing key elements'];
                        } catch (Exception $e) {
                            $tests['main_page'] = ['status' => 'fail', 'message' => 'Main page test failed: ' . $e->getMessage()];
                        }
                        
                        // Test 6: Transaction Details Endpoint
                        try {
                            $url = 'http://sosol.local/admin/get_transaction_details.php?id=1';
                            $context = stream_context_create([
                                'http' => [
                                    'method' => 'GET',
                                    'header' => 'Cookie: ' . session_name() . '=' . session_id()
                                ]
                            ]);
                            $response = file_get_contents($url, false, $context);
                            $has_content = !empty(trim($response)) && strpos($response, 'Error') === false;
                            $tests['details_endpoint'] = ['status' => $has_content ? 'pass' : 'fail', 
                                                         'message' => $has_content ? 'Transaction details endpoint working' : 'Transaction details endpoint has issues'];
                        } catch (Exception $e) {
                            $tests['details_endpoint'] = ['status' => 'fail', 'message' => 'Details endpoint test failed: ' . $e->getMessage()];
                        }
                        
                        // Display Results
                        $passed = 0;
                        $total = count($tests);
                        
                        echo "<div class='row'>";
                        foreach ($tests as $test_name => $result) {
                            $icon = $result['status'] === 'pass' ? '✅' : '❌';
                            $class = $result['status'] === 'pass' ? 'success' : 'danger';
                            if ($result['status'] === 'pass') $passed++;
                            
                            echo "<div class='col-md-6 mb-3'>";
                            echo "<div class='card border-{$class}'>";
                            echo "<div class='card-header bg-{$class} text-white'>";
                            echo "<h6 class='mb-0'>{$icon} " . ucwords(str_replace('_', ' ', $test_name)) . "</h6>";
                            echo "</div>";
                            echo "<div class='card-body'>";
                            echo "<p class='mb-0'>{$result['message']}</p>";
                            if (isset($result['data'])) {
                                echo "<small class='text-muted'><pre>" . json_encode($result['data'], JSON_PRETTY_PRINT) . "</pre></small>";
                            }
                            echo "</div>";
                            echo "</div>";
                            echo "</div>";
                        }
                        echo "</div>";
                        
                        // Overall Results
                        $percentage = round(($passed / $total) * 100);
                        $overall_class = $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger');
                        ?>
                        
                        <div class="alert alert-<?= $overall_class ?> mt-4">
                            <h4>Overall Test Results: <?= $passed ?>/<?= $total ?> (<?= $percentage ?>%)</h4>
                            <?php if ($percentage >= 80): ?>
                                <p class="mb-0">🎉 <strong>Excellent!</strong> Transaction management system is working well.</p>
                            <?php elseif ($percentage >= 60): ?>
                                <p class="mb-0">⚠️ <strong>Good progress</strong> but some issues need attention.</p>
                            <?php else: ?>
                                <p class="mb-0">🚨 <strong>Issues detected</strong> - significant problems need to be resolved.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4">
                            <a href="transactions.php" class="btn btn-primary">🔍 View Main Transactions Page</a>
                            <a href="test_transactions_display.php" class="btn btn-secondary">📊 View Raw Data Test</a>
                            <a href="test_transaction_details.php" class="btn btn-info">🔧 Test Details Endpoint</a>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>