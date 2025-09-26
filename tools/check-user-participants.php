<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

try {
    $db = getDbConnection();
    
    // Check table constraints
    $result = $db->query('SHOW CREATE TABLE sol_participants');
    $row = $result->fetch(PDO::FETCH_ASSOC);
    echo "sol_participants table structure:\n";
    echo $row['Create Table'] . "\n\n";
    
    // Check if user is already in the group
    $user_id = '00000000-0000-0000-0000-000000000002'; // Marie Claire
    $group_id = '2b8d47fc-a417-47f2-9597-d7ae05625c6d'; // Mona SOL3xDay v3
    
    $check = $db->prepare("SELECT * FROM sol_participants WHERE user_id = ? AND sol_group_id = ?");
    $check->execute([$user_id, $group_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "User is already in the group:\n";
        print_r($existing);
        
        // Remove the user to test again
        $delete = $db->prepare("DELETE FROM sol_participants WHERE user_id = ? AND sol_group_id = ?");
        $result = $delete->execute([$user_id, $group_id]);
        echo "\nRemoved user from group: " . ($result ? "Success" : "Failed") . "\n";
    } else {
        echo "User is not in the group yet\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
