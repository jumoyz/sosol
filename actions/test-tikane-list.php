<?php
// Test helper: simulate an authenticated session and forward to ti-kane-list.php
// Safe, non-destructive. Intended for local testing only.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Choose a test user id that exists in your DB, or create a transient one for read-only tests.
// If your DB has no users, the underlying action may return 401/empty array.
$_SESSION['user_id'] = $_GET['user_id'] ?? ($_SESSION['user_id'] ?? 1);

// Forward request to the real action
require __DIR__ . '/ti-kane-list.php';

?>
