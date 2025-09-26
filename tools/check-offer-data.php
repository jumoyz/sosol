<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDbConnection();

echo "loan_offers table structure:\n";
$stmt = $db->prepare('DESCRIBE loan_offers');
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $column) {
    echo '- ' . $column['Field'] . ' (' . $column['Type'] . ")\n";
}

echo "\nSample offer data:\n";
$stmt = $db->prepare('SELECT * FROM loan_offers WHERE id = ? LIMIT 1');
$stmt->execute(['0d32748c-0e58-4743-b493-511eda64640c']);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($offer) {
    foreach ($offer as $key => $value) {
        echo $key . ': ' . ($value ?? 'NULL') . "\n";
    }
} else {
    echo 'No offer found';
}
?>
