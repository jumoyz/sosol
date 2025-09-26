<?php
/**
 * Login Action Handler
 * 
 * Pro        // Prepare query to find user
        $stmt = $db->prepare("SELECT id, full_name, email, password_hash, kyc_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if user exists and password is correct
        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['is_verified'] = $user['kyc_verified'];
            $_SESSION['logged_in'] = true;equests 
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/flash-messages.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Redirect URL (default to dashboard)
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'dashboard';
    
    // Basic validation
    if (!$email) {
        setFlashMessage('error', 'Please enter a valid email address.');
        redirect('../?page=login');
        exit;
    }
    
    if (empty($password)) {
        setFlashMessage('error', 'Please enter your password.');
        redirect('../?page=login');
        exit;
    }
    
    try {
        // Connect to database
        $db = getDbConnection();
        
        // Prepare query to find user  
        $stmt = $db->prepare("SELECT id, full_name, email, password_hash, role, kyc_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if user exists and password is correct
        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['is_verified'] = $user['kyc_verified'];
            $_SESSION['logged_in'] = true;
            
            // Set remember me cookie if selected (30 days) - simplified version
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Set secure httpOnly cookie
                setcookie('remember_token', $token, $expiry, '/', '', false, true);
                setcookie('remember_user', $user['id'], $expiry, '/', '', false, true);
                // Store token in auth_tokens table
                $stmt = $db->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', $expiry)]);
            }

            // Update last login timestamp
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Set success message and redirect
            setFlashMessage('success', 'Login successful! Welcome back, ' . $user['full_name']);
            // inside /actions/login.php after successful login
            logLogin($db, $user['id'], $user['email'], 'success');
            logActivity($db, $user['id'], 'login', null, ['ip' => $_SERVER['REMOTE_ADDR']]);
            notifyUser($db, $user['id'], 'login', 'Welcome back', 'You logged in successfully.');

            // Add debugging
            error_log("Login successful for user: " . $user['full_name'] . " (ID: " . $user['id'] . ")");
            error_log("Session variables set, redirecting to dashboard");
            
            redirect('../?page=dashboard');
            exit; // Ensure execution stops here
        } else {
            // Log failed login details
            if (!$user) {
                error_log("Login failed - User not found for email: " . $email);
            } else {
                error_log("Login failed - Invalid password for email: " . $email);
            }
            
            // Show generic error message (for security)
            setFlashMessage('error', 'Invalid email or password. Please try again.');
            redirect('../?page=login');
            exit;
        }
    } catch (PDOException $e) {
        // Log detailed error information
        error_log('Login database error: ' . $e->getMessage());
        error_log('Error code: ' . $e->getCode());
        error_log('Email attempted: ' . $email);
        
        setFlashMessage('error', 'An error occurred. Please try again later.');
        redirect('../?page=login');
        exit;
    } catch (Exception $e) {
        // Catch any other errors
        error_log('Login general error: ' . $e->getMessage());
        setFlashMessage('error', 'An unexpected error occurred. Please try again later.');
        redirect('../?page=login');
        exit;
    }
} else {
    // Not a POST request
    redirect('../?page=login');
}