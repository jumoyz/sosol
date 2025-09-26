<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

try {
    $db = getDbConnection();
    
    echo "Checking sol_groups table structure:\n";
    $result = $db->query('DESCRIBE sol_groups');
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\nChecking sol_invitations table structure:\n";
    $result = $db->query('DESCRIBE sol_invitations');
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\nChecking for existing invitations:\n";
    $result = $db->query('SELECT COUNT(*) as count FROM sol_invitations');
    $count = $result->fetchColumn();
    echo "Total invitations: $count\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
