<?php
session_start();
require_once '../includes/config.php';

// Create admin session
$_SESSION['user_id'] = '1';
$_SESSION['is_admin'] = true;
$_SESSION['user_role'] = 'admin';

echo "<h2>Transactions Test</h2>";

try {
    $pdo = getDbConnection();
    
    echo "<h3>Raw Transaction Data:</h3>";
    
    $query = "
        SELECT t.*, u.full_name, u.email 
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "<p>No transactions found!</p>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Transaction ID</th><th>User</th><th>Type</th><th>Amount</th><th>Currency</th><th>Status</th><th>Date</th>";
        echo "</tr>";
        
        foreach ($transactions as $trans) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars(substr($trans['id'], 0, 8)) . "...</td>";
            echo "<td>" . htmlspecialchars($trans['transaction_id'] ?: 'Empty') . "</td>";
            echo "<td>" . htmlspecialchars($trans['full_name'] ?: 'Unknown') . "</td>";
            echo "<td>" . htmlspecialchars($trans['type'] ?: 'Empty') . "</td>";
            echo "<td>" . number_format($trans['amount'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($trans['currency']) . "</td>";
            echo "<td>" . htmlspecialchars($trans['status']) . "</td>";
            echo "<td>" . date('Y-m-d H:i', strtotime($trans['created_at'])) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<h3>Statistics:</h3>";
        $stats_query = "
            SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
            FROM transactions
        ";
        
        $stats_stmt = $pdo->prepare($stats_query);
        $stats_stmt->execute();
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<ul>";
        echo "<li><strong>Total:</strong> {$stats['total_transactions']}</li>";
        echo "<li><strong>Pending:</strong> {$stats['pending_count']}</li>";
        echo "<li><strong>Approved:</strong> {$stats['approved_count']}</li>";
        echo "<li><strong>Completed:</strong> {$stats['completed_count']}</li>";
        echo "<li><strong>Rejected:</strong> {$stats['rejected_count']}</li>";
        echo "</ul>";
    }
    
    echo "<h3>Test Link:</h3>";
    echo "<a href='transactions.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;'>Go to Admin Transactions Page</a>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 20px 0; }
th, td { text-align: left; padding: 8px; }
</style>