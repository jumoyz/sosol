<?php
/**
 * Reset Password Action Handler
 * 
 * Processes password reset from token link
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form inputs
    $token = $_POST['token'] ?? '';
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (!$email) {
        setFlashMessage('error', 'Invalid email address.');
        redirect('../?page=reset-password&token=' . urlencode($token) . '&email=' . urlencode($email));
        exit;
    }
    
    if (strlen($password) < 8) {
        setFlashMessage('error', 'Password must be at least 8 characters long.');
        redirect('../?page=reset-password&token=' . urlencode($token) . '&email=' . urlencode($email));
        exit;
    }
    
    if ($password !== $confirmPassword) {
        setFlashMessage('error', 'Passwords do not match.');
        redirect('../?page=reset-password&token=' . urlencode($token) . '&email=' . urlencode($email));
        exit;
    }
    
    try {
        // Connect to database
        $db = getDbConnection();
        
        // Get user by email
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Don't reveal if email exists or not
            setFlashMessage('error', 'Invalid or expired reset link.');
            redirect('../?page=login');
            exit;
        }
        
        // Check for valid reset token
        $stmt = $db->prepare("
            SELECT * FROM password_resets 
            WHERE user_id = ? AND expires_at > NOW() 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resetRequest || !password_verify($token, $resetRequest['token'])) {
            setFlashMessage('error', 'Invalid or expired reset link.');
            redirect('../?page=login');
            exit;
        }
        
        // Hash new password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user password
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$passwordHash, $user['id']]);
        
        // Delete used token and any other tokens for this user
        $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        
        // Log action
        $stmt = $db->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'password-reset')");
        $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
        // Set success message
        setFlashMessage('success', 'Your password has been reset successfully. You can now log in with your new password.');
        
        // Redirect to login page
        redirect('../?page=login');
        
    } catch (PDOException $e) {
        // Log error and show friendly message
        error_log('Reset password error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred. Please try again later.');
        redirect('../?page=reset-password&token=' . urlencode($token) . '&email=' . urlencode($email));
    }
} else {
    // Not a POST request
    redirect('../?page=login');
}