<?php
/**
 * Create SOL Group Action Handler
 * 
 * Processes user create SOL Group requests
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash-messages.php';

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Invalid CSRF token');
}
// Initialize variables
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    setFlashMessage('error', 'You must be logged in to create a SOL group.');
    redirect('?page=login');
}
$csrfToken = $_SESSION['csrf_token'];
$errors = [];
$success = '';

// Database connection
try {
    $db = getDbConnection();
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    die('Database connection error');
}

// Handle group creation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW);
    $description = filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW);
    $contribution = filter_input(INPUT_POST, 'contribution', FILTER_VALIDATE_FLOAT);
    $frequency = filter_input(INPUT_POST, 'frequency', FILTER_UNSAFE_RAW);
    $totalCycles = filter_input(INPUT_POST, 'total_cycles', FILTER_VALIDATE_INT);
    $currentCycle = filter_input(INPUT_POST, 'current_cycle', FILTER_VALIDATE_INT);
    $memberLimit = filter_input(INPUT_POST, 'member_limit', FILTER_VALIDATE_INT);
    $visibility = filter_input(INPUT_POST, 'visibility', FILTER_UNSAFE_RAW);
    $status = filter_input(INPUT_POST, 'status', FILTER_UNSAFE_RAW);
    
    // Sanitize inputs
    $name = htmlspecialchars(trim($name), ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars(trim($description), ENT_QUOTES, 'UTF-8');
    $frequency = htmlspecialchars(trim($frequency), ENT_QUOTES, 'UTF-8');
    $visibility = htmlspecialchars(trim($visibility), ENT_QUOTES, 'UTF-8');
    
    // Validation
    $errors = [];

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errors[] = 'Invalid CSRF token';
}
    
    if (empty($name) || strlen($name) < 3) {
        $errors[] = 'Group name must be at least 3 characters.';
    }
    
    if (!$contribution || $contribution <= 0) {
        $errors[] = 'Please enter a valid contribution amount.';
    }
    
    if (!in_array($frequency, ['daily', 'every3days','weekly', 'biweekly', 'monthly'])) {
        $errors[] = 'Please select a valid contribution frequency.';
    }
    
    //if (!in_array($frequency, ['daily', 'every3days', 'weekly', 'biweekly', 'monthly'])) {
     //   $errors[] = 'Please select a valid contribution frequency.';
    //}
    
    if (!$memberLimit || $memberLimit < 3 || $memberLimit > 20) {
        $errors[] = 'Member limit must be between 3 and 20.';
    }
    
    // Check if the user is verified (KYC approved)
    $userStmt = $db->prepare("SELECT kyc_verified FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user['kyc_verified']) {
        $errors[] = 'You must complete KYC verification to create a SOL group.';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate group ID
            $groupId = generateUuid();
            
            // Create new SOL group
            $createStmt = $db->prepare("
                INSERT INTO sol_groups (
                    id, admin_id, name, description, contribution, frequency, 
                    total_cycles, current_cycle, member_limit, visibility, 
                    status, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 
                    ?, 1, ?, ?, 
                    'active', NOW(), NOW()
                )
            ");
            $createStmt->execute([
                $groupId, $userId, $name, $description, $contribution, $frequency,
                $totalCycles, $memberLimit, $visibility
            ]);
            
            $participantId = generateUuid();
            // Add creator as admin
            $participantStmt = $db->prepare("
                INSERT INTO sol_participants (
                    id, sol_group_id, user_id, role, join_date, 
                    contribution_due_date, payout_position, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, 'admin', NOW(),
                    DATE_ADD(NOW(), INTERVAL CASE ? 
                        WHEN 'daily' THEN 1 
                        WHEN 'every3days' THEN 3
                        WHEN 'weekly' THEN 7 
                        WHEN 'biweekly' THEN 14 
                        ELSE 30 END DAY),
                    1, NOW(), NOW()
                )
            ");
            $participantStmt->execute([$participantId, $groupId, $userId, $frequency]);
            
            $db->commit();
            // Flash message
            setFlashMessage('success', 'SOL group created successfully!');
            redirect('?page=sol-details&id=' . $groupId);
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('SOL group creation error: ' . $e->getMessage());
            error_log('SQL Error Info: ' . print_r($db->errorInfo(), true));
            setFlashMessage('error', 'An error occurred while creating the SOL group.');
        }
    } else {
        // Set error messages
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
        }
    }
}