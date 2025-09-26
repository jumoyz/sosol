<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDbConnection();

echo "Checking loan data for the specific loan...\n";
$stmt = $db->prepare('SELECT * FROM loans WHERE id = ?');
$stmt->execute(['l1111111-1111-1111-1111-111111111111']);
$loan = $stmt->fetch(PDO::FETCH_ASSOC);

if ($loan) {
    echo "Loan data:\n";
    foreach ($loan as $key => $value) {
        echo $key . ': ' . ($value ?? 'NULL') . "\n";
    }
} else {
    echo 'No loan found';
}
?>
