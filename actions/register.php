<?php
/**
 * Register Action Handler
 * 
 * Processes new user registrations
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $fullName = trim(filter_input(INPUT_POST, 'full_name', FILTER_UNSAFE_RAW));
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phoneNumber = trim(filter_input(INPUT_POST, 'phone_number', FILTER_UNSAFE_RAW));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $agree = isset($_POST['agree']);
    
    // Additional sanitization
    $fullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $phoneNumber = htmlspecialchars($phoneNumber, ENT_QUOTES, 'UTF-8');
    
    // Basic validation
    $errors = [];
    
    if (empty($fullName) || strlen($fullName) < 3) {
        $errors[] = 'Please enter your full name (at least 3 characters).';
    }
    
    if (!$email) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($phoneNumber)) {
        $errors[] = 'Please enter your phone number.';
    }
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (!$agree) {
        $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';
    }
    
    // If validation fails, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        $_SESSION['form_data'] = [
            'full_name' => $fullName,
            'email' => $email,
            'phone_number' => $phoneNumber
        ];
        redirect('../?page=register');
        exit;
    }
    
    try {
        // Connect to database
        $db = getDbConnection();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            setFlashMessage('error', 'Email address is already registered. Please log in or use a different email.');
            redirect('../?page=register');
            exit;
        }
        
        // Check if phone number already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->execute([$phoneNumber]);
        if ($stmt->rowCount() > 0) {
            setFlashMessage('error', 'Phone number is already registered. Please use a different number.');
            redirect('../?page=register');
            exit;
        }
        
        // Generate unique ID
        $userId = generateUuid();
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $db->prepare("
            INSERT INTO users (id, full_name, email, phone_number, password_hash, role, kyc_verified, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 'user', FALSE, NOW(), NOW())
        ");
        $stmt->execute([$userId, $fullName, $email, $phoneNumber, $passwordHash]);
        
        // Create wallets for new user
        $walletId = generateUuid();
        $stmt = $db->prepare("
            INSERT INTO wallets (id, user_id, currency, balance, created_at, updated_at)
            VALUES (?, ?, 'HTG', 0, NOW(), NOW())
        ");
        $stmt->execute([$walletId, $userId]);

        $walletId = generateUuid();
        $stmt = $db->prepare("
            INSERT INTO wallets (id, user_id, currency, balance, created_at, updated_at)
            VALUES (?, ?, 'USD', 0, NOW(), NOW())
        ");
        $stmt->execute([$walletId, $userId]);

        // Set session variables (auto-login)
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $role;
        $_SESSION['is_verified'] = false;
        $_SESSION['logged_in'] = true;
        
        // Set success message and redirect
        setFlashMessage('success', 'Account created successfully! Welcome to SOSOL, ' . $fullName);
        redirect('../?page=dashboard');
        
    } catch (PDOException $e) {
        // Log error and show friendly message
        error_log('Registration error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred during registration. Please try again later.');
        redirect('../?page=register');
    }
} else {
    // Not a POST request
    redirect('../?page=register');
}