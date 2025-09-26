#!/usr/bin/env php
<?php
// Simple migrations runner
// Usage: php tools/migrate.php [--pretend]

require_once __DIR__ . '/../includes/config.php';

$pretend = in_array('--pretend', $argv, true) || in_array('-p', $argv, true);
$pdo = getDbConnection();

$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255) NOT NULL UNIQUE, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");

$migrationsDir = realpath(__DIR__ . '/../database/migrations');
if (!$migrationsDir) {
    fwrite(STDERR, "Migrations directory not found\n");
    exit(1);
}

$files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql');
sort($files);

// Fetch applied migrations into a set
$stmt = $pdo->query('SELECT filename FROM migrations');
$applied = array_flip(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'filename'));

$pending = [];
foreach ($files as $file) {
    $base = basename($file);
    if (!isset($applied[$base])) {
        $pending[] = $file;
    }
}

if (empty($pending)) {
    echo "No pending migrations.\n";
    exit(0);
}

echo "Pending migrations:\n";
foreach ($pending as $p) {
    echo '  - ' . basename($p) . "\n";
}

if ($pretend) {
    echo "Pretend mode: no changes applied.\n";
    exit(0);
}

foreach ($pending as $path) {
    $name = basename($path);
    echo "Applying: $name...\n";
    $sql = file_get_contents($path);
    try {
        $pdo->beginTransaction();
        // Split on ; but keep simplistic (won't handle procedures). Filter empty statements.
        $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
        foreach ($statements as $statement) {
            if ($statement === '' || strpos($statement, '--') === 0) continue;
            $pdo->exec($statement);
        }
        $ins = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
        $ins->execute([$name]);
        $pdo->commit();
        echo "  -> Done\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Error applying $name: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "All pending migrations applied successfully.\n";
