<?php
/**
 * Session Helper
 * 
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Get the current logged-in user role
 */
function current_user_role(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Require user to be logged in
 */
function require_login(): void {
    if (!is_logged_in()) {
        header("Location: /login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Require a specific role (e.g., 'admin', 'member')
 */
function require_role(string $role): void {
    require_login(); // first ensure logged in
    if (current_user_role() !== $role) {
        http_response_code(403);
        die("Access denied: insufficient permissions.");
    }
}

/**
 * Require multiple roles (array of allowed roles)
 */
function require_roles(array $roles): void {
    require_login();
    if (!in_array(current_user_role(), $roles, true)) {
        http_response_code(403);
        die("Access denied: insufficient permissions.");
    }
}
