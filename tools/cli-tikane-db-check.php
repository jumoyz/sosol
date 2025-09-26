<?php
chdir(__DIR__ . '/..');
if (php_sapi_name() !== 'cli') { echo "CLI only\n"; exit(1); }
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
try {
    $db = getDbConnection();
    $tables = ['ti_kane_accounts','ti_kane_payments'];
    foreach ($tables as $t) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as c FROM `$t`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "$t: " . ($row['c'] ?? 0) . " rows\n";
        } catch (Exception $e) {
            echo "$t: ERROR - " . $e->getMessage() . "\n";
        }
    }
    exit(0);
} catch (Exception $e) {
    echo "DB connect error: " . $e->getMessage() . "\n";
    exit(2);
}
