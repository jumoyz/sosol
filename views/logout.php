<?php
/**
 * Logout Action Handler
 * 
 * Logs out user and destroys session
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    try {
        // Connect to database
        $db = getDbConnection();
        
        // Remove any remember-me tokens
        if (isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
            $stmt = $db->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete the cookies
            setcookie('remember_token', '', time() - 3600, '/');
            setcookie('remember_user', '', time() - 3600, '/');
        }
        
        // Log the logout
        $stmt = $db->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'logout')");
        $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
    } catch (PDOException $e) {
        // Just log the error, don't stop the logout process
        error_log('Logout error: ' . $e->getMessage());
    }
}

// Destroy all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set a logout message
setFlashMessage('success', 'You have been successfully logged out.');

// Redirect to the home page
redirect('../?page=login');