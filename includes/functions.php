<?php
/**
 * Helper Functions
 * 
 * Collection of utility functions used throughout the application
 */

/**
 * Sanitize user input
 * 
 * @param string $data Input to sanitize
 * @return string Sanitized input
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirect to another page
 *
 * @param string $url URL to redirect to
 * @return void
 */
function redirect($url) {
    // Check if headers have already been sent
    if (!headers_sent()) {
        // Safe to send headers
        header("Location: $url");
        exit;
    } else {
        // Headers already sent, use JavaScript fallback
        echo '<script>window.location.href="' . htmlspecialchars($url) . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
        echo '<p>Please click <a href="' . htmlspecialchars($url) . '">here</a> if you are not redirected.</p>';
        exit;
    }
}

/**
 * Set flash message in session
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message content
 * @return void
 */ 

/**
 * Get and clear flash message from session
 * 
 * @return array|null Flash message or null if none exists
 */ 
function addFlash($type, $message) {
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Generate a random string
 * 
 * @param int $length Length of random string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Generate a UUID v4
 *
 * @return string The generated UUID
 */
//function generateUuid() {
    // Check if PHP has the uuid extension
//    if (function_exists('uuid_create')) {
//        return uuid_create(UUID_TYPE_RANDOM);
 //   }
    
    // Fallback implementation
//    $data = random_bytes(16);
    
    // Set version to 0100
 //   $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
//    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    
    // Output the 36 character UUID
//    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
///}

/**
 * Generates a UUID v4
 * @return string The generated UUID
 */
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Format currency amount
 * 
 * @param float $amount Amount to format
 * @param string $currency Currency code (default: HTG)
 * @return string Formatted currency
 */
function formatCurrency($amount, $currency = 'HTG') {
    $currencies = [
        'HTG' => ['symbol' => 'G', 'decimals' => 2, 'position' => 'after'],
        'USD' => ['symbol' => '$', 'decimals' => 2, 'position' => 'before'],
        'CAD' => ['symbol' => '$', 'decimals' => 2, 'position' => 'before'],
        'EUR' => ['symbol' => '€', 'decimals' => 2, 'position' => 'before'],
        'GBP' => ['symbol' => '£', 'decimals' => 2, 'position' => 'before'],
    ];
    
    $config = $currencies[$currency] ?? $currencies['HTG'];
    $formattedAmount = number_format($amount, $config['decimals'], '.', ',');
    
    if ($config['position'] === 'before') {
        return $config['symbol'] . $formattedAmount;
    } else {
        return $formattedAmount . ' ' . $config['symbol'];
    }
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Retrieve and validate a SOL group ID from request parameters.
 * Centralizes logic to avoid duplicate / inconsistent flash messages.
 *
 * @param string $redirectOnFailure URL (query string) to redirect to if invalid / missing
 * @param string $paramName Query parameter name to inspect (default: 'id')
 * @param bool $allowPost If true, will also look in $_POST when GET empty
 * @return string Valid group id (UUID-like) – function will redirect/exit on failure
 */
function requireValidGroupId(string $redirectOnFailure = '?page=sol-groups', string $paramName = 'id', bool $allowPost = true): string {
    // Prefer GET param
    $groupId = $_GET[$paramName] ?? null;
    if (!$groupId && $allowPost) {
        $groupId = $_POST[$paramName] ?? ($_POST['group_id'] ?? null);
    }

    // Trim and normalize
    if (is_string($groupId)) {
        $groupId = trim($groupId);
    }

    // Only trigger error if truly missing or clearly malformed
    $isMissing = empty($groupId);

    // Basic UUID v4 pattern (relaxed to accept existing stored format)
    $uuidPattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';
    $looksUuid = !$isMissing && preg_match($uuidPattern, $groupId) === 1;

    // Some legacy IDs (if any) could be shorter; accept 16+ length alnum fallback
    $fallbackOk = !$isMissing && !$looksUuid && preg_match('/^[A-Za-z0-9_-]{16,}$/', $groupId) === 1;

    if ($isMissing || (!$looksUuid && !$fallbackOk)) {
        // Only set flash if not already set this request for this exact issue
        if (!isset($_SESSION['__group_id_missing_flag'])) {
            setFlashMessage('error', 'Group ID is missing.');
            $_SESSION['__group_id_missing_flag'] = true;
        }
        redirect($redirectOnFailure);
    }

    return $groupId;
}

// ===============================
// Role-based Access Control (RBAC)
// ===============================

/**
 * Get the current logged-in user's role
 */
function getUserRole(): string
{
    return $_SESSION['user_role'] ?? 'guest'; // fallback for non-logged users
}

/**
 * Check if the user has a specific role
 */
//function hasRole(string $role): bool
//{
//    return getUserRole() === $role;
//}

/**
 * Check if user has specific role
 * 
 * @param string|array $roles Role(s) to check
 * @return bool True if user has role
 */
function hasRole(array $roles): bool {
    if (!isset($_SESSION['user_role'])) {
        return false; // not logged in or no role assigned
    }
    return in_array($_SESSION['user_role'], $roles);
}

function hasRoles(array $roles): bool {
    if (!isset($_SESSION['user_role'])) {
        return false; // not logged in or no role assigned
    }
    foreach ($roles as $role) {
        if (in_array($role, $_SESSION['user_role'])) {
            return true;
        }
    }
    return false;
} // (end hasRoles )


/**
 * Check if the user has one of multiple roles
 */
function hasAnyRole(array $roles): bool
{
    return in_array(getUserRole(), $roles, true);
}

/**
 * Enforce minimum role access (hierarchical RBAC)
 */
function hasRoleAtLeast(string $role): bool
{
    $hierarchy = [
        'guest'       => 0,
        'user'        => 1,
        'auditor'     => 2,
        'manager'     => 3,
        'admin'       => 4,
        'super_admin' => 5,
    ];

    $current = $hierarchy[getUserRole()] ?? 0;
    $required = $hierarchy[$role] ?? 0;

    return $current >= $required;
}

/**
 * Check if user is admin
 * 
 * @return bool True if user is admin
 */
function isAdmin(): bool {
    return hasRole(['admin', 'super_admin']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Check if user is manager
 * 
 * @return bool True if user is manager
 */
function solAdmin(): bool {
    return hasRole(['admin', 'sol_admin']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
}

/**
 * Check if user is manager
 * 
 * @return bool True if user is manager
 */
function solManager(): bool {
    return hasRole(['manager', 'admin', 'sol_admin']) || (isset($_SESSION['is_manager']) && $_SESSION['is_manager'] === true);
}   

/**
 * Redirect if the user does not have required role
 */
function requireRole(string $role): void
{
    if (!hasRoleAtLeast($role)) {
        $_SESSION['error'] = "You do not have permission to access this page.";
        header("Location: /403.php"); // or your custom access denied page
        exit;
    }
}

/**
 * Log user activity
 * 
 * @param PDO $db Database connection
 * @param string $userId User ID
 * @param string $action Action performed
 * @param string $description Optional description
 * @return void
 */
function logActivity(
    PDO $db,
    string $userId,
    string $activityType,
    ?string $referenceId = null,
    array $details = [],
    bool $seen = false
): void {
    try {
        $stmt = $db->prepare("
            INSERT INTO activities (id, user_id, activity_type, reference_id, details, seen, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            generateUuid(),
            $userId,
            $activityType,
            $referenceId,
            json_encode($details, JSON_THROW_ON_ERROR),
            $seen ? 1 : 0,
        ]);
    } catch (PDOException $e) {
        error_log('Error logging activity: ' . $e->getMessage());
    }
}

/**
 * Log user login
 *
 * @param PDO $db Database connection
 * @param string|null $userId User ID
 * @param string $status Login status
 * @return void
 */
function logLogin(PDO $db, ?string $userId, string $email, string $status): void {
    try {
        $stmt = $db->prepare("
            INSERT INTO login_logs (id, user_id, email, ip_address, user_agent, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            generateUuid(),
            $userId,
            $email,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $status,
        ]);
    } catch (PDOException $e) {
        error_log('Error logging login: ' . $e->getMessage());
    }
}

/**
 * Notify user
 *
 * @param PDO $db Database connection
 * @param string $userId User ID
 * @param string $type Notification type
 * @param string $message Notification message
 * @return void
 */
function notifyUser(
    PDO $db,
    string $userId,
    string $type,
    string $title,
    string $message,
    ?string $referenceId = null,
    ?string $referenceType = null
): void {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (id, user_id, type, title, message, reference_id, reference_type, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([
            generateUuid(),
            $userId,
            $type,
            $title,
            $message,
            $referenceId,
            $referenceType,
        ]);
    } catch (PDOException $e) {
        error_log('Error creating notification: ' . $e->getMessage());
    }
}

/**
 * Format date to human-readable format
 * 
 * @param string $date Date string
 * @param string $format Format string (default: 'M j, Y')
 * @return string Formatted date
 */
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format date safely
 */
function format_date(DateTimeImmutable $date, string $format = 'Y-m-d H:i:s'): string {
    return $date->format($format);
}

/**
 * Convert date to time ago format
 * 
 * @param string $date Date string
 * @return string Time ago string
 */
function timeAgo($date) {
    $timestamp = strtotime($date);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return $difference . ' seconds ago';
    } elseif ($difference < 3600) {
        return round($difference/60) . ' minutes ago';
    } elseif ($difference < 86400) {
        return round($difference/3600) . ' hours ago';
    } elseif ($difference < 604800) {
        return round($difference/86400) . ' days ago';
    } elseif ($difference < 2592000) {
        return round($difference/604800) . ' weeks ago';
    } elseif ($difference < 31536000) {
        return round($difference/2592000) . ' months ago';
    } else {
        return round($difference/31536000) . ' years ago';
    }
}

/**
 * Get current UTC time (immutable)
 */
function now_utc(): DateTimeImmutable {
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

/**
 * Create a date from string with a timezone
 */
function date_from_string(string $date, string $tz = 'UTC'): DateTimeImmutable {
    return new DateTimeImmutable($date, new DateTimeZone($tz));
}

/**
 * Add days without mutating original
 */
function add_days(DateTimeImmutable $date, int $days): DateTimeImmutable {
    return $date->modify(($days >= 0 ? '+' : '') . $days . ' days');
}

/**
 * Difference in days between two dates
 */
function diff_in_days(DateTimeImmutable $a, DateTimeImmutable $b): int {
    return (int) $a->diff($b)->format('%r%a'); // signed number of days
}

/**
 * Map SOL frequency keyword to a canonical interval definition.
 * Supports legacy / potential future values gracefully.
 *
 * @param string $frequency (weekly, biweekly, monthly, daily, every3days)
 * @return array{type:string,value:int} Type is 'days' or 'months'. Value is interval count.
 */
function solFrequencyInterval(string $frequency): array {
    $f = strtolower(trim($frequency));
    switch ($f) {
        case 'daily':
            return ['type' => 'days', 'value' => 1];
        case 'every3days':
        case 'every_3_days':
            return ['type' => 'days', 'value' => 3];
        case 'weekly':
            return ['type' => 'days', 'value' => 7];
        case 'biweekly':
        case 'bi-weekly':
            return ['type' => 'days', 'value' => 14];
        case 'monthly':
            return ['type' => 'months', 'value' => 1];
        default:
            // Fallback: treat unknown as monthly to avoid overly tight cycles
            return ['type' => 'months', 'value' => 1];
    }
}

/**
 * Compute the scheduled date for a given cycle number (1-based) anchored on the SOL group's start date.
 * If start date is missing, falls back to created_at (passed in by caller) or 'today'.
 *
 * Monthly cycles use calendar month increments (DateInterval 'P1M') rather than fixed 30-day approximation.
 * Day-based cycles add N * (cycleNumber - 1) days.
 *
 * @param string $anchorDate Y-m-d or full datetime string
 * @param string $frequency See solFrequencyInterval()
 * @param int $cycleNumber 1-based cycle index
 * @return DateTimeImmutable
 */
function computeSolCycleDate(string $anchorDate, string $frequency, int $cycleNumber): DateTimeImmutable {
    if ($cycleNumber < 1) {
        $cycleNumber = 1; // normalize
    }
    try {
        $base = new DateTimeImmutable($anchorDate);
    } catch (Exception $e) {
        $base = new DateTimeImmutable('today');
    }

    $interval = solFrequencyInterval($frequency);
    $offsetIndex = $cycleNumber - 1; // 0 for first cycle

    if ($offsetIndex === 0) {
        return $base; // first cycle occurs at anchor
    }

    if ($interval['type'] === 'months') {
        // Add months iteratively to preserve end-of-month semantics (e.g., Jan 31 -> Feb 29/28 etc.)
        return $base->modify('+' . ($interval['value'] * $offsetIndex) . ' months');
    }

    // Days based
    $daysToAdd = $interval['value'] * $offsetIndex;
    return $base->modify('+' . $daysToAdd . ' days');
}

/**
 * Build an array of all cycle dates (1..$totalCycles).
 *
 * @param string $anchorDate
 * @param string $frequency
 * @param int $totalCycles
 * @return array<int,string> Map cycleNumber => 'Y-m-d' date string
 */
function computeSolCycleDates(string $anchorDate, string $frequency, int $totalCycles): array {
    $dates = [];
    for ($i = 1; $i <= max(1, $totalCycles); $i++) {
        $dates[$i] = computeSolCycleDate($anchorDate, $frequency, $i)->format('Y-m-d');
    }
    return $dates;
}

/**
 * Determine the next contribution due date for a participant based on group schedule
 * rather than an increment from "today". Logic:
 *   - If group is completed (current_cycle > total_cycles) => null
 *   - If participant has NOT contributed in current cycle => due date is current cycle date
 *   - Else if there is a future cycle (current_cycle + 1 <= total_cycles) => next cycle date
 *   - Else (they contributed last cycle which is final) => null
 *
 * @param string $anchorDate Start date (or creation date fallback)
 * @param string $frequency
 * @param int $currentCycle 1-based current payout / contribution cycle
 * @param int $totalCycles
 * @param bool $hasContributedThisCycle Whether participant has already contributed this cycle (paid or pending)
 * @return DateTimeImmutable|null
 */
function computeNextContributionDate(
    string $anchorDate,
    string $frequency,
    int $currentCycle,
    int $totalCycles,
    bool $hasContributedThisCycle
): ?DateTimeImmutable {
    if ($currentCycle > $totalCycles) {
        return null; // group done
    }
    if (!$hasContributedThisCycle) {
        return computeSolCycleDate($anchorDate, $frequency, $currentCycle);
    }
    $nextCycle = $currentCycle + 1;
    if ($nextCycle <= $totalCycles) {
        return computeSolCycleDate($anchorDate, $frequency, $nextCycle);
    }
    return null; // fully contributed through final cycle
}


/**
 * Validate file upload
 * 
 * @param array $file $_FILES array element
 * @param array $allowedTypes Array of allowed file extensions
 * @param int $maxSize Maximum file size in bytes
 * @return array ['status' => bool, 'message' => string]
 */
function validateFileUpload($file, $allowedTypes = null, $maxSize = null) {
    // Get allowed types and max size from env if not provided
    if ($allowedTypes === null) {
        $allowedTypes = explode(',', getenv('ALLOWED_FILE_TYPES'));
    }
    
    if ($maxSize === null) {
        $maxSize = getenv('UPLOAD_MAX_SIZE');
    }
    
    // Check if file was uploaded properly
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['status' => false, 'message' => 'Invalid file parameters'];
    }
    
    // Check upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['status' => false, 'message' => 'File is too large'];
        case UPLOAD_ERR_PARTIAL:
            return ['status' => false, 'message' => 'File was only partially uploaded'];
        case UPLOAD_ERR_NO_FILE:
            return ['status' => false, 'message' => 'No file was uploaded'];
        case UPLOAD_ERR_NO_TMP_DIR:
            return ['status' => false, 'message' => 'Missing temporary folder'];
        case UPLOAD_ERR_CANT_WRITE:
            return ['status' => false, 'message' => 'Failed to write file to disk'];
        case UPLOAD_ERR_EXTENSION:
            return ['status' => false, 'message' => 'A PHP extension stopped the file upload'];
        default:
            return ['status' => false, 'message' => 'Unknown upload error'];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'File is too large (max ' . formatFileSize($maxSize) . ')'];
    }
    
    // Check file extension
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedTypes)) {
        return ['status' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes)];
    }
    
    return ['status' => true, 'message' => 'File is valid'];
}
/*
function validateFileUpload(array $file, array $allowedExtensions, int $maxSize): array {
    // Ensure integer for comparison
    $maxSize = (int) $maxSize;

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['status' => false, 'message' => 'Invalid file parameters.'];
    }

    // Check upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'File upload error code: ' . $file['error']];
    }

    // Validate file size
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'File exceeds maximum size of ' . $maxSize . ' bytes.'];
    }

    // Validate file extension
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedExtensions, true)) {
        return ['status' => false, 'message' => 'Invalid file extension. Allowed: ' . implode(', ', $allowedExtensions)];
    }

    // ✅ Return success with useful data
    return [
        'status' => true,
        'message' => 'File is valid.',
        'fileExtension' => $fileExtension
    ];
}
*/

/**
 * Format file size to human-readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Log error to file
 * 
 * @param string $message Error message
 * @param string $level Error level (error, warning, info)
 * @return void
 */
function logError($message, $level = 'error') {
    $logFile = ROOT_PATH . '/logs/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Create logs directory if it doesn't exist
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function log_error($message) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);

    $logFile = $logDir . '/error-' . date('Y-m-d') . '.log'; // daily rotation

    $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    error_log($entry, 3, $logFile);
}

// ===============================
// CSRF Protection Helpers
// ===============================
/**
 * Generate (or retrieve existing) CSRF token for the session.
 * @return string
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (empty($_SESSION['__csrf_token'])) {
        $_SESSION['__csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['__csrf_token'];
}

/**
 * Validate a supplied CSRF token value.
 * @param string|null $token
 * @return bool
 */
function csrf_validate(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!$token || empty($_SESSION['__csrf_token'])) { return false; }
    // constant-time comparison
    return hash_equals($_SESSION['__csrf_token'], $token);
}

/**
 * Require a valid CSRF token or abort request (sets flash and redirects if referer available)
 */
function csrf_require(?string $token, string $redirect = null): void {
    if (!csrf_validate($token)) {
        setFlashMessage('error', 'Security token mismatch. Please try again.');
        if ($redirect) { redirect($redirect); }
        http_response_code(400);
        exit('Bad Request');
    }
}

function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function verifyCsrfToken(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}
function requireValidCsrfOrFail(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        setFlashMessage('error','Security token expired or invalid. Please retry.');
        redirect($_SERVER['REQUEST_URI']);
    }
}

/**
 * Get the current user's wallet balance and information
 * 
 * @param int $userId Optional user ID (defaults to current logged-in user)
 * @return array Wallet information containing balances and other details
 */
function getUserWallet($userId = null) {
    try {
        // If no user ID provided, get the current logged in user ID
        if ($userId === null) {
            if (!isLoggedIn()) {
                return [
                    'balance' => 0,
                    'balance_htg' => 0,
                    'balance_usd' => 0
                ];
            }
            $userId = $_SESSION['user_id'];
        }
        
        // Connect to the database
        $db = getDbConnection();
        
        // Get the user's wallet
        $stmt = $db->prepare("SELECT * FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If wallet doesn't exist, create a new one
        if (!$wallet) {
            // Create a new wallet for the user
            $stmt = $db->prepare("INSERT INTO wallets (user_id, balance_htg, balance_usd, created_at) 
                                 VALUES (?, 0, 0, NOW())");
            $stmt->execute([$userId]);
            
            // Return default wallet values
            return [
                'id' => $db->lastInsertId(),
                'user_id' => $userId,
                'balance_htg' => 0,
                'balance_usd' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // Create a new HTG wallet for the user
            $stmt = $db->prepare("INSERT INTO wallets (user_id, currency, balance, created_at) 
                                 VALUES (?, 'HTG', 0, NOW())");
            $stmt->execute([$userId]);

            // Create a new USD wallet for the user
            $stmt = $db->prepare("INSERT INTO wallets (user_id, currency, balance, created_at) 
                                 VALUES (?, 'USD', 0, NOW())");
            $stmt->execute([$userId]);
        }
        
        // Return the wallet data
        return $wallet;
    } catch (PDOException $e) {
        logError('Error fetching wallet: ' . $e->getMessage());
        
        // Return default values in case of error
        return [
            'balance' => 0,
            'balance_htg' => 0,
            'balance_usd' => 0
        ];
    }
}

/**
 * Get the current user's wallet balance
 * 
 */
function getWalletBalance($userId = null) {
    $wallet = getUserWallet($userId);
    return [
        'balance' => $wallet['balance'],
        'balance_htg' => $wallet['balance_htg'],
        'balance_usd' => $wallet['balance_usd']
    ];
}

/**
 * Create SOL group
 * 
 * @param PDO $db Database connection
 */
function createSolGroup($db) {
    try {
        $stmt = $db->prepare("
            INSERT INTO sol_groups (id, admin_id, name, description, contribution, frequency, start_date, created_at, updated_at)
            VALUES (:id, :admin_id, :name, :description, :contribution, :frequency, :start_date, NOW(), NOW())
        ");

        $stmt->bindParam(':id', generateUuid());
        $stmt->bindParam(':admin_id', $_SESSION['user_id']);
        $stmt->bindParam(':name', $_POST['name']);
        $stmt->bindParam(':description', $_POST['description']);
        $stmt->bindParam(':contribution', $_POST['contribution']);
        $stmt->bindParam(':frequency', $_POST['frequency']);
        $stmt->bindParam(':start_date', $_POST['start_date']);

        $stmt->execute();

        // Add creator as first participant
        $solGroupId = $db->lastInsertId(); // Get the ID of the newly created SOL group
        $stmt = $db->prepare("
            INSERT INTO sol_participants (id, sol_group_id, user_id, created_at, updated_at)
            VALUES (:id, :sol_group_id, :user_id, NOW(), NOW())
        ");
        
        $stmt->bindParam(':id', generateUuid());
        $stmt->bindParam(':sol_group_id', $solGroupId);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();

        // Generate initial payout schedule
        $frequency[$_POST['frequency']] = $_POST['frequency'];
        $groupId = $solGroupId;
        // TODO: 
        
    
    } catch (Exception $e) {
        logError('Error creating SOL group: ' . $e->getMessage());
    }
}

/**
 *  SOL Group Payout Manager 
 * 
 */ /*
class SolGroup
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Regenerate payout schedule for a group
     * - Rebuilds sol_payouts
     * - Updates participants' payout_position
     */ /*
    public function regeneratePayoutSchedule(string $groupId): bool
    {
        try {
            $this->db->beginTransaction();

            // Fetch group details
            $stmt = $this->db->prepare("
                SELECT frequency, start_date 
                FROM sol_groups 
                WHERE id = :group_id
            ");
            $stmt->execute(['group_id' => $groupId]);
            $group = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$group) {
                throw new RuntimeException("Group not found: $groupId");
            }

            $frequency = $group['frequency']; // weekly | monthly
            $startDate = new DateTimeImmutable($group['start_date']);

            // Fetch all active participants
            $stmt = $this->db->prepare("
                SELECT id, user_id 
                FROM sol_participants 
                WHERE group_id = :group_id 
                ORDER BY created_at ASC
            ");
            $stmt->execute(['group_id' => $groupId]);
            $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$participants) {
                throw new RuntimeException("No participants found for group: $groupId");
            }

            $numParticipants = count($participants);

            // Clear existing payouts
            $this->db->prepare("DELETE FROM sol_payouts WHERE group_id = :group_id")
                ->execute(['group_id' => $groupId]);

            // Generate new payouts
            $payoutDate = $startDate;
            $insertStmt = $this->db->prepare("
                INSERT INTO sol_payouts (id, group_id, participant_id, payout_date, payout_order, status, created_at, updated_at)
                VALUES (:id, :group_id, :participant_id, :payout_date, :payout_order, 'pending', NOW(), NOW())
            ");

            $updateParticipantStmt = $this->db->prepare("
                UPDATE sol_participants 
                SET payout_position = :payout_position 
                WHERE id = :participant_id
            ");

            foreach ($participants as $index => $participant) {
                $payoutOrder = $index + 1;

                // Insert payout row
                $insertStmt->execute([
                    'id'            => $this->uuid(),
                    'group_id'      => $groupId,
                    'participant_id'=> $participant['id'],
                    'payout_date'   => $payoutDate->format('Y-m-d'),
                    'payout_order'  => $payoutOrder,
                ]);

                // Update payout_position in sol_participants
                $updateParticipantStmt->execute([
                    'payout_position' => $payoutOrder,
                    'participant_id'  => $participant['id'],
                ]);

                // Move date forward
                $payoutDate = match ($frequency) {
                    'weekly'  => $payoutDate->modify('+1 week'),
                    'monthly' => $payoutDate->modify('+1 month'),
                    default   => throw new RuntimeException("Unknown frequency: $frequency"),
                };
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log("Payout schedule regeneration failed: " . $e->getMessage());
            return false;
        }
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
*/

/**
 *  Get the SOL group frequency
 *
 * @param string $group_id SOL group ID
 * @return string Frequency of the SOL group
 */
function getGroupFrequency($group_id) {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT frequency FROM sol_groups WHERE id = ? LIMIT 1");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    return $group['frequency'];
}

/**
 *  Get the next payout position for a SOL group
 *
 * @param string $group_id SOL group ID
 * @return int Next payout position for the SOL group
 */
function getNextPayoutPosition($group_id) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT position 
        FROM sol_payouts 
        WHERE sol_group_id = ? 
        ORDER BY position DESC 
        LIMIT 1
    ");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    return $group['position'];
}

/**
 *  Get the payout position for a SOL group
 *
 * @param string $group_id SOL group ID
 * @return int payout position for the SOL group
 */
function getPayoutPosition($group_id) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT position 
        FROM sol_payouts 
        WHERE sol_group_id = ? 
        ORDER BY position ASC 
        LIMIT 1
    ");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    return $group['position'];
}

/**
 *  Get the payout status for a SOL group
 *
 * @param string $group_id SOL group ID
 * @return string payout status for the SOL group
 */
function getPayoutStatus($group_id) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT status 
        FROM sol_payouts 
        WHERE sol_group_id = ? 
        ORDER BY position ASC 
        LIMIT 1
    ");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    return $group['status'];
}

/**
 *  Generate payouts for a SOL group
 *  PHP logic that will auto-generate the sol_payouts schedule when a new SOL group is created (based on frequency + number of participants)
 * 
 * @param string $sol_group_id SOL group ID
 * @return string Success message or error message
 */
function generateSolPayouts($sol_group_id) {
    global $pdo;

    // 1. Fetch group details
    $stmt = $pdo->prepare("SELECT start_date, frequency, contribution, currency 
                           FROM sol_groups WHERE id = ?");
    $stmt->execute([$sol_group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        throw new Exception("SOL group not found");
    }

    $start_date = new DateTime($group['start_date']);
    $frequency  = $group['frequency'];
    $amount     = $group['contribution_amount'];
    $currency   = $group['currency'];

    // 2. Fetch participants ordered by payout_position
    $stmt = $pdo->prepare("SELECT id, payout_position 
                           FROM sol_participants 
                           WHERE sol_group_id = ? 
                           ORDER BY payout_position ASC");
    $stmt->execute([$sol_group_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Loop through participants to calculate scheduled payouts
    foreach ($participants as $p) {
        $payout_date = clone $start_date;

        // Calculate date based on payout_position
        $steps = $p['payout_position'] - 1; // 0-based offset
        switch ($frequency) {
            case 'weekly':
                $payout_date->modify("+{$steps} week");
                break;
            case 'biweekly':
                $payout_date->modify("+".($steps * 2)." week");
                break;
            case 'monthly':
                $payout_date->modify("+{$steps} month");
                break;
            default:
                throw new Exception("Unsupported frequency: $frequency");
        }

        // 4. Insert into sol_payouts
        $stmtInsert = $pdo->prepare("INSERT INTO sol_payouts 
            (id, sol_group_id, participant_id, amount, currency, scheduled_date, status) 
            VALUES (UUID(), ?, ?, ?, ?, ?, 'pending')");

        $stmtInsert->execute([
            $sol_group_id,
            $p['id'],
            $amount,
            $currency,
            $payout_date->format('Y-m-d')
        ]);
    }

    return count($participants) . " payouts scheduled.";
}


/**
 *  Calculate contribution due date for a group
 *
 * @param string $frequency Contribution frequency
 * @return string Contribution due date 
 */
function calculateContributionDueDate($frequency) {
    switch ($frequency) {
        case 'weekly':
            return date('Y-m-d', time() + 7 * 86400);
        case 'monthly':
            return date('Y-m-d', time() + 30 * 86400);
        case 'quarterly':
            return date('Y-m-d', time() + 90 * 86400);
        case 'yearly':
            return date('Y-m-d', time() + 365 * 86400);
        default:
            return date('Y-m-d', time() + 7 * 86400);
    }
}
/**
 *  Calcualte contribution amount for a group
 *
 * @param int $group_id Group ID
 * @return float Contribution amount    
 */
function calculateContributionAmount($group_id) {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT contribution 
        FROM sol_groups 
        WHERE id = ? 
        LIMIT 1
    ");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    return $group['contribution'] ?? 0;
}

    /**
     * Relative time helper (e.g., '5m ago', '2h ago', 'Mar 3')
     */
    // (Existing timeAgo() defined earlier; lightweight variant removed to avoid duplicate definition.)

    /**
     * Fetch unified feed items (public visibility)
     * Types: sol_group, loan_request (loans pending), campaign, investment
     * Returns array of associative items with keys: type, id, title, amount, status, created_at, extra
     * @param int $limit overall limit
     */
    function fetchUnifiedFeed(PDO $db, int $limit = 40): array {
        $items = [];
        try {
            // Public SOL groups (active / pending) limited
            $solStmt = $db->query("SELECT id, name AS title, contribution AS amount, status, created_at FROM sol_groups ORDER BY created_at DESC LIMIT 15");
            foreach ($solStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'type' => 'sol_group',
                    'id' => $r['id'],
                    'title' => $r['title'],
                    'amount' => (float)$r['amount'],
                    'status' => $r['status'],
                    'created_at' => $r['created_at'],
                    'url' => '?page=sol-details&id=' . urlencode($r['id']),
                    'badge' => 'SOL Group',
                ];
            }
        } catch (Exception $e) { error_log('Feed SOL error: '.$e->getMessage()); }
        try {
            // Loan requests (loans where status pending) show principal amount
            $loanStmt = $db->query("SELECT id, amount, status, created_at, borrower_id FROM loans ORDER BY created_at DESC LIMIT 15");
            foreach ($loanStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'type' => 'loan_request',
                    'id' => $r['id'],
                    'title' => 'Loan Request',
                    'amount' => (float)$r['amount'],
                    'status' => $r['status'],
                    'created_at' => $r['created_at'],
                    'url' => '?page=loan-details&id=' . urlencode($r['id']),
                    'badge' => 'Loan',
                ];
            }
        } catch (Exception $e) { error_log('Feed loans error: '.$e->getMessage()); }
        try {
            // Campaigns (public) - show goal
            $campStmt = $db->query("SELECT id, title, goal_amount, status, created_at FROM campaigns ORDER BY created_at DESC LIMIT 15");
            foreach ($campStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'type' => 'campaign',
                    'id' => $r['id'],
                    'title' => $r['title'],
                    'amount' => (float)$r['goal_amount'],
                    'status' => $r['status'],
                    'created_at' => $r['created_at'],
                    'url' => '?page=campaign&id=' . urlencode($r['id']),
                    'badge' => 'Campaign',
                ];
            }
        } catch (Exception $e) { error_log('Feed campaigns error: '.$e->getMessage()); }
        try {
            // Investments (public)
            $invStmt = $db->query("SELECT id, title, funding_goal, status, created_at FROM investments WHERE visibility='public' ORDER BY created_at DESC LIMIT 15");
            foreach ($invStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items[] = [
                    'type' => 'investment',
                    'id' => $r['id'],
                    'title' => $r['title'],
                    'amount' => (float)$r['funding_goal'],
                    'status' => $r['status'],
                    'created_at' => $r['created_at'],
                    'url' => '?page=investment-details&id=' . urlencode($r['id']),
                    'badge' => 'Investment',
                ];
            }
        } catch (Exception $e) { error_log('Feed investments error: '.$e->getMessage()); }

        // Sort combined list by created_at desc
        usort($items, function($a,$b){ return strcmp($b['created_at'],$a['created_at']); });
        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }
        return $items;
    }
