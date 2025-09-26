<?php
// Start session  if it's not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Reset per-request guard flags (e.g., group id missing flash suppression)
if (isset($_SESSION['__group_id_missing_flag'])) {
    unset($_SESSION['__group_id_missing_flag']);
}

// Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Error reporting for development (remove in production)
error_reporting(E_ALL);

// Include required files
require_once __DIR__ .'/includes/config.php';
require_once __DIR__ .'/includes/functions.php';
require_once __DIR__ .'/includes/auth.php';
require_once __DIR__ .'/includes/constants.php';
require_once __DIR__ .'/includes/translator.php';

// Handle language change (do this near the top of index.php)
if (isset($_GET['lang'])) {
    // whitelist languages
    $lang = substr((string)$_GET['lang'], 0, 2);
    $allowed = ['en','fr','ht','es'];
    if (in_array($lang, $allowed, true)) {
        setCurrentLanguage($lang); // from includes/translator.php
    }
    // redirect to same URL without query string to avoid repeat
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}


// Determine which page to display
$page = $_GET['page'] ?? 'home';

// Debug the requested page
error_log('Page requested: ' . $page . ' | Time: ' . time());



// List of valid pages
$validPages = [
    'home', 'dashboard', 'login', 'register', 'profile', 'settings', 'wallet', 'accounts', 'add-account', 'account-details','edit-account','add-money', 'payment-methods',
    'sol-groups', 'sols', 'sol-join', 'sol-details', 'sol-manage','sol-edit','group-chat','sol-delete','sol-contributions', 'sol-payouts', 'sol-finance',
    'loans','loan-center','loan-details','loan-offers','offer-details','edit-offer','loan-payment', 'repayment-schedule','crowdfunding',
    'create-campaign', 'campaign', 'my-campaigns', 'help-center','faq',
    'contact', 'privacy-policy', 'terms', 'transfer', 'request-money', 'request-history',
    'notifications', 'password-reset', 'verification', 'transactions', 
    'admin-login', 'admin-dashboard', 'admin-users', 'admin-campaigns', 'admin-accounts', 'admin-transactions', 'admin-sols', 'admin-requests', 'admin-logs', 'admin-settings',
    'admin-add-user', 'admin-edit-user', 'admin-delete-user', 'admin-add-campaign', 'admin-edit-campaign', 'admin-delete-campaign', 'admin-add-account', 'admin-edit-account', 'admin-delete-account', 'admin-add-sol', 'admin-edit-sol', 'admin-delete-sol',
    'admin-add-request', 'admin-edit-request', 'admin-delete-request', 'admin-add-log', 'admin-edit-log', 'admin-delete-log', 'admin-add-setting', 'admin-edit-setting', 'admin-delete-setting',
    'admin-add-transaction', 'admin-edit-transaction', 'admin-delete-transaction',
    'admin-add-notification', 'admin-edit-notification', 'admin-delete-notification',
    'admin-add-help-article', 'admin-edit-help-article', 'admin-delete-help-article',
    'admin-add-contact', 'admin-edit-contact', 'admin-delete-contact',
    'admin-add-privacy-policy', 'admin-edit-privacy-policy', 'admin-delete-privacy-policy',
    'admin-add-terms', 'admin-edit-terms', 'admin-delete-terms',
    'admin-add-password-reset', 'admin-edit-password-reset', 'admin-delete-password-reset',
    'admin-add-verification', 'admin-edit-verification', 'admin-delete-verification',
    'admin-add-transaction', 'admin-edit-transaction', 'admin-delete-transaction',
    'admin-add-transaction', 'admin-edit-transaction', 'admin-delete-transaction',
    'admin', 'admin-dashboard', 
    // Investments feature pages
    'investments', 'investment-create', 'investment-details',
    // Feed
    'feed',
    // Ti Kane
    'ti-kane', 'ti-kane-payments', 'ti-kane-calendar',

];

// Security: Make sure the page is valid, otherwise default to home
if (!in_array($page, $validPages)) {
    $page = 'home';
}

// Define page title based on current page
$pageTitles = [
    'home' => 'Home',
    'dashboard' => 'Dashboard',
    'login' => 'Login',
    'register' => 'Register',
    'profile' => 'My Profile',
    'settings' => 'Settings',
    'wallet' => 'My Wallet',
    'accounts' => 'My Accounts',
    'add-account' => 'Add Account',
    'account-details' => 'Account Details',
    'edit-account' => 'Edit Account',
    'add-money' => 'Add Money',
    'payment-methods' => 'Payment Methods',
    'transactions' => 'Transaction History',
    'sols' => 'SOL Savings Groups',
    'sol-groups' => 'SOL Savings Groups',
    'sol-details' => 'SOL Group Details',
    'sol-join' => 'Join SOL Group',
    'sol-manage' => 'Manage SOL Group',
    'sol-edit' => 'Edit SOL Group',
    'group-chat' => 'Group Chat',
    'sol-delete' => 'Delete SOL Group',
    'sol-contributions' => 'Contributions',
    'sol-payouts' => 'Payouts',
    'sol-finance' => 'Finance Management',
    'loans' => 'Loans',
    'loan-center' => 'Loan Center',
    'loan-details' => 'Loan Details',
    'loan-offers' => 'Loan Offers',
    'offer-details' => 'Offer Details',
    'edit-offer' => 'Edit Offer',
    'loan-payment' => 'Loan Payment',
    'repayment-schedule' => 'Repayment Schedule',
    'crowdfunding' => 'Crowdfunding',
    'create-campaign' => 'Create Campaign',
    'campaign' => 'Campaign Details',
    'my-campaigns' => 'My Campaigns',
    'help-center' => 'Help Center',
    'faq' => 'FAQ',
    'contact' => 'Contact Us',
    'privacy-policy' => 'Privacy Policy',
    'terms' => 'Terms of Service',
    'transfer' => 'Transfer',
    'request-money' => 'Request Money',
    'request-history' => 'Request History',
    'notifications' => 'Notifications',
    'password-reset' => 'Reset Password',
    'verification' => 'Verification',
    'admin' => 'Admin',
    'admin-dashboard' => 'Admin Dashboard',
    'admin-users' => 'Admin Users',
    'admin-campaigns' => 'Admin Campaigns',
    'admin-accounts' => 'Admin Accounts',
    'admin-transactions' => 'Admin Transactions',
    'admin-sols' => 'Admin SOLs',
    'admin-requests' => 'Admin Requests',
    'admin-logs' => 'Admin Logs',
    'admin-settings' => 'Admin Settings',
    'admin-add-user' => 'Add User',
    'admin-edit-user' => 'Edit User',
    'admin-delete-user' => 'Delete User',
    'admin-add-campaign' => 'Add Campaign',
    'admin-edit-campaign' => 'Edit Campaign',
    'admin-delete-campaign' => 'Delete Campaign',
    'admin-add-account' => 'Add Account',
    'admin-edit-account' => 'Edit Account',
    'admin-delete-account' => 'Delete Account',
    'admin-add-sol' => 'Add SOL',
    'admin-edit-sol' => 'Edit SOL',
    'admin-delete-sol' => 'Delete SOL',
    'admin-add-request' => 'Add Request',
    'admin-edit-request' => 'Edit Request',
    'admin-delete-request' => 'Delete Request',
    'admin-add-log' => 'Add Log',
    'admin-edit-log' => 'Edit Log',
    'admin-delete-log' => 'Delete Log',
    'admin-add-setting' => 'Add Setting',
    'admin-edit-setting' => 'Edit Setting',
    'admin-delete-setting' => 'Delete Setting',
    'admin-add-transaction' => 'Add Transaction',
    'admin-edit-transaction' => 'Edit Transaction',
    'admin-delete-transaction' => 'Delete Transaction',
    'admin-add-notification' => 'Add Notification',
    'admin-edit-notification' => 'Edit Notification',
    'admin-delete-notification' => 'Delete Notification',
    'admin-add-help-article' => 'Add Help Article',
    'admin-edit-help-article' => 'Edit Help Article',
    'admin-delete-help-article' => 'Delete Help Article',
    'admin-add-contact' => 'Add Contact',
    'admin-edit-contact' => 'Edit Contact',
    'admin-delete-contact' => 'Delete Contact',
    'admin-add-privacy-policy' => 'Add Privacy Policy',
    'admin-edit-privacy-policy' => 'Edit Privacy Policy',
    'admin-delete-privacy-policy' => 'Delete Privacy Policy',
    'admin-add-terms' => 'Add Terms',
    'admin-edit-terms' => 'Edit Terms',
    'admin-delete-terms' => 'Delete Terms',
    'admin-add-password-reset' => 'Add Password Reset',
    'admin-edit-password-reset' => 'Edit Password Reset',
    'admin-delete-password-reset' => 'Delete Password Reset',
    'admin-add-verification' => 'Add Verification',
    'admin-edit-verification' => 'Edit Verification',
    'admin-delete-verification' => 'Delete Verification',
    'admin-add-transaction' => 'Add Transaction',
    'admin-edit-transaction' => 'Edit Transaction',
    'admin-delete-transaction' => 'Delete Transaction',
    'admin-add-transaction' => 'Add Transaction',
    'admin-edit-transaction' => 'Edit Transaction',
    'admin-delete-transaction' => 'Delete Transaction',
    // Investments page titles
    'investments' => 'Investments',
    'investment-create' => 'Create Investment Opportunity',
    'investment-details' => 'Investment Details',
    'feed' => 'Latest Activity Feed',
    'ti-kane',
    'ti-kane-payments' => 'Ti Kanè Payments',
    'ti-kane-calendar' => 'Ti Kanè Calendar',                                  
];

$pageTitle = $pageTitles[$page] ?? 'SOSOL';

// Check if page requires authentication and redirect if necessary
$authRequiredPages = ['dashboard', 'profile', 'settings', 'wallet', 'accounts', 'add-account', 'account-details','edit-account', 'add-money', 'transactions', 'payment-methods', 'sols', 'sol-groups', 'sol-details', 'sol-edit', 'sol-join', 'sol-manage',
                    'group-chat', 'sol-delete','sol-contributions', 'sol-payouts','sol-finance','faq',
                    'loans', 'loan-center', 'loan-details','loan-offers','offer-details','edit-offer','loan-payment','repayment-schedule','crowdfunding', 'create-campaign', 'campaign', 'my-campaigns',
                    'notifications', 'help-center', 'contact', 'privacy-policy', 'terms', 'transfer', 'request-money', 'request-history', 'password-reset', 'verification',
                    'investments', 'investment-create', 'investment-details',
                    'admin', 'admin-dashboard', 'admin-users', 'admin-campaigns', 'admin-accounts', 'admin-transactions', 'admin-sols', 'admin-requests', 'admin-logs', 'admin-settings',
                    'ti-kane', 'ti-kane-payments', 'ti-kane-calendar'   
                ];

if (in_array($page, $authRequiredPages) && !isLoggedIn()) {
    // Save intended destination for redirect after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect('?page=dashboard');
    exit; // Make sure to exit after redirect
}

// IMPORTANT: Only include view-specific logic that might trigger redirects BEFORE header.php
if (file_exists("views/{$page}-preprocess.php")) {
    include_once "views/{$page}-preprocess.php";
}

// Include header
include_once 'includes/header.php';

// Include the appropriate view file
$viewFile = "views/{$page}.php";

if (file_exists($viewFile)) {
    include_once $viewFile;
} else {
    // Fallback to 404 page if view doesn't exist
    error_log('Page file not found: ' . $viewFile);
    include_once 'views/404.php';
}

// Include footer
include_once 'includes/footer.php';