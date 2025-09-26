<?php
/**
 * Google OAuth Login Handler
 *
 * Handles Google Sign-in functionality using Google API PHP Client
 * Install via: composer require google/apiclient:^2.0
 */

// Prevent vendor deprecation notices and other errors from being sent to output
// before headers are sent. Long-term: upgrade google/apiclient or use a PHP
// version compatible with the vendor. These settings stop display of errors
// during this request only.
// Start output buffering immediately to catch any accidental output from
// vendor files (deprecation notices, echoes) so header() calls still work.
if (!ob_get_level()) {
    ob_start();
}
ini_set('display_startup_errors', '0');
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load required files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Autoload Google API Client
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    setFlashMessage('error', 'Google authentication is not available. Please use email/password to login.');
    redirect('../?page=login');
    exit;
}
require_once __DIR__ . '/../vendor/autoload.php';

// Use necessary Google classes
// The google/apiclient v2 packages use namespaced classes under Google\Service\Oauth2
// and also provide a class alias for backward compatibility named Google_Service_Oauth2.
// To help static analyzers like Intelephense, import the namespaced class and alias it.
use Google\Service\Oauth2 as Google_Service_Oauth2;

// Google OAuth Configuration
$clientId = getenv('GOOGLE_CLIENT_ID') ?: 'YOUR-CLIENT-ID.apps.googleusercontent.com';
$clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR-CLIENT-SECRET';
$redirectUri = getenv('GOOGLE_REDIRECT_URI') ?: 'http://sosol.local/actions/google-login.php';

// Create Google client
$client = new Google_Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope(['email', 'profile']);

// Handle OAuth flow
if (isset($_GET['code'])) {
    // Exchange auth code for access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (isset($token['error'])) {
        setFlashMessage('error', 'Google authentication failed. Please try again.');
        redirect('../?page=login');
        exit;
    }

    $client->setAccessToken($token['access_token']);

    // Get user profile
    $googleService = new Google_Service_Oauth2($client);
    $googleUser = $googleService->userinfo->get();

    $email = $googleUser->email ?? '';
    $name = $googleUser->name ?? '';
    $picture = $googleUser->picture ?? '';

    try {
        $db = getDbConnection();

        // Check if user exists
        $stmt = $db->prepare("SELECT id, full_name, email, kyc_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Login existing user
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['is_verified'] = $user['kyc_verified'];
            $_SESSION['logged_in'] = true;

            // Update profile picture
            if ($picture) {
                $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                $stmt->execute([$picture, $user['id']]);
            }

            // Log login
            $stmt = $db->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, 'google-signin')");
            $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            setFlashMessage('success', 'Login successful! Welcome back, ' . $user['full_name']);
        } else {
            // Register new user
            $userId = generateUuid();
            $tempPhone = '+509' . rand(10000000, 99999999);

            $stmt = $db->prepare("
                INSERT INTO users (id, full_name, email, phone_number, profile_photo, password_hash, kyc_verified, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, FALSE, NOW(), NOW())
            ");
            $stmt->execute([
                $userId,
                $name,
                $email,
                $tempPhone,
                $picture,
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)
            ]);

            // Create wallet
            $walletId = generateUuid();
            $stmt = $db->prepare("
                INSERT INTO wallets (id, user_id, balance_htg, balance_usd, created_at, updated_at)
                VALUES (?, ?, 0, 0, NOW(), NOW())
            ");
            $stmt->execute([$walletId, $userId]);

            // Set session
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['is_verified'] = false;
            $_SESSION['logged_in'] = true;

            setFlashMessage('success', 'Account created successfully! Welcome to SoSol, ' . $name);
            setFlashMessage('info', 'Please update your phone number and complete your profile.');
        }

        redirect('../?page=dashboard');

    } catch (PDOException $e) {
        error_log('Google login error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred during sign in. Please try again later.');
        redirect('../?page=login');
    }

} else {
    // No auth code, redirect to Google login
    $authUrl = $client->createAuthUrl();
    header('Location: ' . $authUrl);
    exit;
}
