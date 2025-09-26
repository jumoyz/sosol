<?php
// Test bootstrap
// Minimal bootstrapping: load config & functions, set up in-memory sqlite if possible (fallback to skipping DB tests)

$root = dirname(__DIR__);
require_once $root . '/includes/functions.php';

// Attempt lightweight PDO SQLite for isolated logic tests (no schema defined here yet)
try {
    $GLOBALS['__test_pdo'] = new PDO('sqlite::memory:');
    $GLOBALS['__test_pdo']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    // Not critical; some tests may skip if DB not available
}
