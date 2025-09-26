<?php
/**
 * Authentication Manager
 * 
 * Handles user authentication, session management, and access control
 * 
 * @package SOSOL
 * @version 1.0
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get Gravatar URL
 */
// Function to get Gravatar URL
function getGravatar($email, $size = 80, $default = 'mp', $rating = 'g') {
    $url = 'https://www.gravatar.com/avatar/';
    $url .= md5(strtolower(trim($email)));
    $url .= "?s=$size&d=$default&r=$rating";
    return $url;
}

/**
 * Authenticate user with email and password
 * 
 * @param string $email User email
 * @param string $password User password (plain text)
 * @param bool $remember Whether to remember the user
 * @return array Authentication result ['success' => bool, 'message' => string, 'user' => array|null]
 */
function authenticateUser($email, $password, $remember = false) {
    try {
        $db = getDbConnection();
        
        // Get user by email
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND status = 'active' LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Log failed attempt
            logLoginAttempt($email, false);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Check if password needs rehash
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
            $updateStmt->bindParam(':password', $newHash);
            $updateStmt->bindParam(':id', $user['id']);
            $updateStmt->execute();
        }
        
        // Remove password from user array before storing in session
        unset($user['password']);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['avatar'] = $_SESSION['avatar'] ?? getGravatar($user['email']);
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time(); // changed from last_activity
        
        // Set remember me cookie if requested
        if ($remember) {
            $selector = bin2hex(random_bytes(8));
            $validator = bin2hex(random_bytes(32));
            
            $token = $selector . ':' . hash('sha256', $validator);
            $expires = time() + 60 * 60 * 24 * 30; // 30 days
            
            setcookie('remember_me', $token, $expires, '/', '', true, true);
            
            // Store token in database
            $stmt = $db->prepare("INSERT INTO auth_tokens (user_id, selector, token, expires_at) 
                                 VALUES (:user_id, :selector, :token, :expires_at)");
            $hashedValidator = hash('sha256', $validator);
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->bindParam(':selector', $selector);
            $stmt->bindParam(':token', $hashedValidator);
            $stmt->bindParam(':expires_at', date('Y-m-d H:i:s', $expires));
            $stmt->execute();
        }
        
        // Log successful login
        logLoginAttempt($email, true);
        
        // Update last login timestamp
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $updateStmt->bindParam(':id', $user['id']);
        $updateStmt->execute();
        
        return [
            'success' => true, 
            'message' => 'Authentication successful', 
            'user' => $user
        ];
        
    } catch (PDOException $e) {
        logError("Authentication error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during authentication'];
    }
}

/**
 * Log user login attempt
 * 
 * @param string $email User email
 * @param bool $success Whether the attempt was successful
 * @return void
 */
function logLoginAttempt($email, $success) {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("INSERT INTO login_logs (user_id, email, ip_address, user_agent, success) 
                             VALUES (:user_id, :email, :ip, :agent, :success)");

        $ip = $_SERVER['REMOTE_ADDR'];
        $agent = $_SERVER['HTTP_USER_AGENT'];

        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':agent', $agent);
        $stmt->bindParam(':success', $success, PDO::PARAM_BOOL);
        $stmt->execute();
    } catch (PDOException $e) {
        logError("Error logging login attempt: " . $e->getMessage());
    }
}

/**
 * Check if user is logged in from session or remember me cookie
 * 
 * @return bool True if user is logged in
 */
function checkLogin() {
    // Already logged in via session
    if (isset($_SESSION['user_id'])) {
        // Check session timeout
        $sessionLifetime = 120; // Default 120 minutes
        
        // Try to get from config if function exists
        if (function_exists('config')) {
            $configValue = config('SESSION_LIFETIME', 120);
            if (is_numeric($configValue)) {
                $sessionLifetime = $configValue;
            }
        }
        
        $timeout = $sessionLifetime * 60; // Convert minutes to seconds
        
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            // Session expired
            logoutUser();
            return false;
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    // Check for remember me cookie
    if (isset($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        
        // Parse token
        list($selector, $validator) = explode(':', $token);
        
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT t.*, u.* FROM auth_tokens t 
                                 JOIN users u ON t.user_id = u.id 
                                 WHERE t.selector = :selector AND t.expires_at > NOW() LIMIT 1");
            $stmt->bindParam(':selector', $selector);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && hash_equals($result['token'], hash('sha256', $validator))) {
                // Valid remember me token
                
                // Remove password from user array before storing in session
                unset($result['password']);
                
                // Set session variables
                $_SESSION['user_id'] = $result['user_id'];
                $_SESSION['user_name'] = $result['name'];
                $_SESSION['user_email'] = $result['email'];
                $_SESSION['user_role'] = $result['role'];
                $_SESSION['avatar'] = $_SESSION['avatar'] ?? getGravatar($result['email']);
                $_SESSION['last_activity'] = time();
                
                // Update last login timestamp
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                $updateStmt->bindParam(':id', $result['user_id']);
                $updateStmt->execute();
                
                return true;
            } else {
                // Invalid token, clear cookie
                setcookie('remember_me', '', time() - 3600, '/', '', true, true);
                return false;
            }
        } catch (PDOException $e) {
            logError("Remember me authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Register a new user
 * 
 * @param array $userData User data (name, email, password, etc.)
 * @return array Registration result ['success' => bool, 'message' => string, 'user_id' => int|null]
 */
function registerUser($userData) {
    try {
        $db = getDbConnection();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $userData['email']);
        $stmt->execute();
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email address is already registered'];
        }
        
        // Hash password
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));
        
        // Insert new user
        $stmt = $db->prepare("INSERT INTO users (name, email, password, phone, role, status, verification_token, created_at) 
                             VALUES (:name, :email, :password, :phone, :role, :status, :token, NOW())");
        
        $status = 'pending'; // Require email verification
        $role = 'user';      // Default role
        
        $stmt->bindParam(':name', $userData['name']);
        $stmt->bindParam(':email', $userData['email']);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':phone', $userData['phone'] ?? null);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':token', $verificationToken);
        $stmt->execute();
        
        $userId = $db->lastInsertId();
        
        // Create HTG wallet for new user
        $walletStmt = $db->prepare("INSERT INTO wallets (user_id, currency, balance, created_at) VALUES (:user_id, 'HTG', 0, NOW())");
        $walletStmt->bindParam(':user_id', $userId);
        $walletStmt->execute();

        // Create USD wallet for new user
        $walletStmt = $db->prepare("INSERT INTO wallets (user_id, currency, balance, created_at) VALUES (:user_id, 'USD', 0, NOW())");
        $walletStmt->bindParam(':user_id', $userId);
        $walletStmt->execute();

        
        // TODO: Send verification email with verification token
        
        return [
            'success' => true, 
            'message' => 'Registration successful. Please check your email to verify your account.', 
            'user_id' => $userId
        ];
        
    } catch (PDOException $e) {
        logError("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during registration'];
    }
}

function getUserById($userId) {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            unset($user['password']); // Remove password for security
            $user['avatar'] = getGravatar($user['email']);
            return $user;
        }
        
        return null;
        
    } catch (PDOException $e) {
        logError("Error getting user by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Verify user email with token
 * 
 * @param string $token Verification token
 * @return array Verification result ['success' => bool, 'message' => string]
 */
function verifyEmail($token) {
    try {
        $db = getDbConnection();
        
        $stmt = $db->prepare("SELECT id FROM users WHERE verification_token = :token AND status = 'pending' LIMIT 1");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired verification token'];
        }
        
        // Activate user account
        $updateStmt = $db->prepare("UPDATE users SET status = 'active', verification_token = NULL, email_verified_at = NOW() WHERE id = :id");
        $updateStmt->bindParam(':id', $user['id']);
        $updateStmt->execute();
        
        return ['success' => true, 'message' => 'Email verification successful. You can now log in.'];
        
    } catch (PDOException $e) {
        logError("Email verification error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during email verification'];
    }
}

/**
 * Logout current user
 * 
 * @return void
 */
function logoutUser() {
    // Clear remember me cookie if exists
    if (isset($_COOKIE['remember_me'])) {
        // Delete token from database
        try {
            $token = $_COOKIE['remember_me'];
            list($selector, $validator) = explode(':', $token);
            
            $db = getDbConnection();
            $stmt = $db->prepare("DELETE FROM auth_tokens WHERE selector = :selector");
            $stmt->bindParam(':selector', $selector);
            $stmt->execute();
        } catch (Exception $e) {
            logError("Error removing auth token: " . $e->getMessage());
        }
        
        // Clear cookie
        setcookie('remember_me', '', time() - 3600, '/', '', true, true);
    }
    
    // Clear session
    $_SESSION = array();
    
    // Destroy session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Request password reset
 * 
 * @param string $email User email
 * @return array Reset request result ['success' => bool, 'message' => string]
 */
function requestPasswordReset($email) {
    try {
        $db = getDbConnection();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = :email AND status = 'active' LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Don't reveal whether email exists or not for security
            return ['success' => true, 'message' => 'If your email is registered, you will receive reset instructions shortly.'];
        }
        
        // Generate reset token
        $selector = bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(32));
        
        // Token expires after 1 hour
        $expires = date('Y-m-d H:i:s', time() + 3600);
        
        // Delete any existing tokens for this user
        $deleteStmt = $db->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
        $deleteStmt->bindParam(':user_id', $user['id']);
        $deleteStmt->execute();
        
        // Insert new token
        $insertStmt = $db->prepare("INSERT INTO password_resets (user_id, selector, token, expires) 
                                   VALUES (:user_id, :selector, :token, :expires)");
        
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        
        $insertStmt->bindParam(':user_id', $user['id']);
        $insertStmt->bindParam(':selector', $selector);
        $insertStmt->bindParam(':token', $hashedToken);
        $insertStmt->bindParam(':expires', $expires);
        $insertStmt->execute();
        
        // TODO: Send password reset email
        
        return ['success' => true, 'message' => 'If your email is registered, you will receive reset instructions shortly.'];
        
    } catch (PDOException $e) {
        logError("Password reset request error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while processing your request'];
    }
}

/**
 * Reset user password with token
 * 
 * @param string $selector Token selector
 * @param string $token Token validator
 * @param string $newPassword New password
 * @return array Reset result ['success' => bool, 'message' => string]
 */
function resetPassword($selector, $token, $newPassword) {
    try {
        $db = getDbConnection();
        
        // Get reset record
        $stmt = $db->prepare("SELECT r.*, u.email 
                             FROM password_resets r 
                             JOIN users u ON r.user_id = u.id 
                             WHERE r.selector = :selector AND r.expires > NOW() LIMIT 1");
        $stmt->bindParam(':selector', $selector);
        $stmt->execute();
        
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reset || !password_verify($token, $reset['token'])) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
        
        // Update user password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $updateStmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
        $updateStmt->bindParam(':password', $hashedPassword);
        $updateStmt->bindParam(':id', $reset['user_id']);
        $updateStmt->execute();
        
        // Delete used token
        $deleteStmt = $db->prepare("DELETE FROM password_resets WHERE selector = :selector");
        $deleteStmt->bindParam(':selector', $selector);
        $deleteStmt->execute();
        
        return ['success' => true, 'message' => 'Password has been reset successfully. You can now log in with your new password.'];
        
    } catch (PDOException $e) {
        logError("Password reset error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while resetting your password'];
    }
}

/**
 * Check if user has a specific role
 * 
 * @param string|array $roles Role(s) to check
 * @return bool True if user has role
 */
function checkUserRole($roles) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Get current logged-in user data
 * 
 * @param string $field Specific field to return (optional)
 * @return mixed User data or specific field value, or null if not logged in
 */
function getCurrentUser($field = null) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT u.*, w.balance as wallet_balance 
                             FROM users u 
                             LEFT JOIN wallets w ON u.id = w.user_id 
                             WHERE u.id = :id LIMIT 1");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Session exists but user not found in database
            logoutUser();
            return null;
        }
        
        // Remove password for security
        unset($user['password']);
        
        if ($field !== null) {
            return $user[$field] ?? null;
        }
        
        return $user;
        
    } catch (PDOException $e) {
        logError("Error getting current user: " . $e->getMessage());
        return null;
    }
}

// Check login status automatically when included
checkLogin();