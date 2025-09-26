<?php
// Set page title
$pageTitle = "My Profile";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$user = null;
$error = null;
$photoUploadError = null;
$photoUploadSuccess = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = filter_input(INPUT_POST, 'full_name', FILTER_UNSAFE_RAW);
    $phoneNumber = filter_input(INPUT_POST, 'phone_number', FILTER_UNSAFE_RAW);
    $bio = filter_input(INPUT_POST, 'bio', FILTER_UNSAFE_RAW);
    $language = filter_input(INPUT_POST, 'language', FILTER_UNSAFE_RAW);
    
    // Sanitize inputs
    $fullName = htmlspecialchars(trim($fullName), ENT_QUOTES, 'UTF-8');
    $phoneNumber = htmlspecialchars(trim($phoneNumber), ENT_QUOTES, 'UTF-8');
    $bio = htmlspecialchars(trim($bio), ENT_QUOTES, 'UTF-8');
    $language = htmlspecialchars(trim($language), ENT_QUOTES, 'UTF-8');
    
    try {
        $db = getDbConnection();
        
        // Check if phone number is already taken by another user
        $checkStmt = $db->prepare("SELECT id FROM users WHERE phone_number = ? AND id != ?");
        $checkStmt->execute([$phoneNumber, $userId]);
        
        if ($checkStmt->rowCount() > 0) {
            setFlashMessage('error', 'Phone number is already registered to another account.');
        } else {
            // Update user profile
            $updateStmt = $db->prepare("UPDATE users SET full_name = ?, phone_number = ?, bio=?, language=?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$fullName, $phoneNumber, $bio, $language, $userId]);
            
            // Update session name
            $_SESSION['user_name'] = $fullName;
            
            setFlashMessage('success', 'Profile updated successfully!');
            redirect('?page=profile');
        }
    } catch (PDOException $e) {
        error_log('Profile update error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred while updating your profile.');
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        setFlashMessage('error', 'All password fields are required.');
    } elseif (strlen($newPassword) < 8) {
        setFlashMessage('error', 'New password must be at least 8 characters long.');
    } elseif ($newPassword !== $confirmPassword) {
        setFlashMessage('error', 'New passwords do not match.');
    } else {
        try {
            $db = getDbConnection();
            
            // Get current password hash
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password_hash'])) {
                setFlashMessage('error', 'Current password is incorrect.');
            } else {
                // Update password
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$passwordHash, $userId]);
                
                setFlashMessage('success', 'Password changed successfully!');
                redirect('?page=profile');
            }
        } catch (PDOException $e) {
            error_log('Password change error: ' . $e->getMessage());
            setFlashMessage('error', 'An error occurred while changing your password.');
        }
    }
}

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE) {
        $photoUploadError = 'No file selected.';
    } elseif ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $photoUploadError = 'File upload failed. Please try again.';
    } elseif ($_FILES['profile_photo']['size'] > $maxFileSize) {
        $photoUploadError = 'File is too large. Maximum size is 5MB.';
    } elseif (!in_array($_FILES['profile_photo']['type'], $allowedTypes)) {
        $photoUploadError = 'Invalid file type. Only JPG and PNG files are allowed.';
    } else {
        try {
            // Create upload directory if it doesn't exist
            $uploadDir = '../public/uploads/profile_photos/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $fileName = $userId . '_' . time() . '_' . basename($_FILES['profile_photo']['name']);
            $targetFile = $uploadDir . $fileName;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetFile)) {
                $db = getDbConnection();
                
                // Update profile photo in database
                $photoUrl = '/public/uploads/profile_photos/' . $fileName;
                $updateStmt = $db->prepare("UPDATE users SET profile_photo = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$photoUrl, $userId]);
                
                $photoUploadSuccess = 'Profile photo updated successfully!';
            } else {
                $photoUploadError = 'Failed to upload file. Please try again.';
            }
        } catch (PDOException $e) {
            error_log('Profile photo update error: ' . $e->getMessage());
            $photoUploadError = 'An error occurred while updating your profile photo.';
        }
    }
}

// Fetch user data
try {
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Profile data fetch error: ' . $e->getMessage());
    $error = 'Unable to load profile data. Please try again later.';
}
?>

<div class="row">
    <div class="col-lg-3 mb-4">
        <!-- Profile Sidebar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center pt-4">
                <div class="avatar-xl mx-auto mb-3">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile Photo" class="img-fluid rounded-circle">
                    <?php else: ?>
                        <div class="avatar-placeholder bg-light rounded-circle d-flex align-items-center justify-content-center">
                            <i class="fas fa-user text-primary" style="font-size: 3rem;"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h5 class="mb-1"><?= htmlspecialchars($user['full_name'] ?? '') ?></h5>
                <p class="text-muted small mb-3">
                    <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($user['email'] ?? '') ?>
                </p>
                
                <div class="mb-3">
                    <?php if (isset($user['kyc_verified']) && $user['kyc_verified']): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i> Verified Account
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">
                            <i class="fas fa-exclamation-circle me-1"></i> Verification Pending
                        </span>
                    <?php endif; ?>
                </div>
                
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
                    <i class="fas fa-camera me-1"></i> Change Photo
                </button>
                
                <hr class="my-3">
                
                <div class="text-start">
                    <p class="mb-1 d-flex justify-content-between">
                        <span class="text-muted">Member since:</span>
                        <span><?= isset($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : 'N/A' ?></span>
                    </p>
                    <p class="mb-0 d-flex justify-content-between">
                        <span class="text-muted">Last updated:</span>
                        <span><?= isset($user['updated_at']) ? date('M j, Y', strtotime($user['updated_at'])) : 'N/A' ?></span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Quick Links</h6>
                <div class="list-group list-group-flush">
                    <a href="?page=wallet" class="list-group-item list-group-item-action border-0">
                        <i class="fas fa-wallet text-primary me-2"></i> My Wallet
                    </a>
                    <a href="?page=accounts" class="list-group-item list-group-item-action border-0">
                        <i class="fas fa-wallet text-primary me-2"></i> My Accounts
                    </a>                    
                    <a href="?page=sol-groups" class="list-group-item list-group-item-action border-0">
                        <i class="fas fa-users text-primary me-2"></i> My SOL Groups
                    </a>
                    <a href="?page=loan-center" class="list-group-item list-group-item-action border-0">
                        <i class="fas fa-hand-holding-usd text-primary me-2"></i> My Loans
                    </a>
                    <?php if (!isset($user['kyc_verified']) || !$user['kyc_verified']): ?>
                    <a href="?page=verification" class="list-group-item list-group-item-action border-0 text-success">
                        <i class="fas fa-id-card text-success me-2"></i> Complete Verification
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-9">
        <!-- Profile Information -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-0">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-user-circle text-primary me-2"></i> Personal Information
                </h5>
                <p class="text-muted small">Update your basic profile information</p>
            </div>
            <div class="card-body pb-4">
                <form method="POST" action="?page=profile">
                    <div class="row mb-3">
                        <label for="fullName" class="col-md-3 col-form-label">Full Name</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" id="fullName" name="full_name" 
                                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="email" class="col-md-3 col-form-label">Email Address</label>
                        <div class="col-md-9">
                            <input type="email" class="form-control" id="email" 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                            <div class="form-text">Email cannot be changed. Contact support if needed.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="phoneNumber" class="col-md-3 col-form-label">Phone Number</label>
                        <div class="col-md-9">
                            <input type="tel" class="form-control" id="phoneNumber" name="phone_number" 
                                   value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="bio" class="col-md-3 col-form-label">Bio</label>
                        <div class="col-md-9">
                            <textarea type="text" class="form-control" id="bio" name="bio" 
                                   value="<?= htmlspecialchars($user['bio'] ?? '') ?>" required><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="language" class="col-md-3 col-form-label">Preferred Language</label>
                        <div class="col-md-9">
                            <select class="form-control" id="language" name="language" required>
                                <option value="en" <?= ($user['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="fr" <?= ($user['language'] ?? '') === 'fr' ? 'selected' : '' ?>>French</option>
                                <option value="ht" <?= ($user['language'] ?? '') === 'ht' ? 'selected' : '' ?>>Haitian Creole</option>
                                <option value="es" <?= ($user['language'] ?? '') === 'es' ? 'selected' : '' ?>>Spanish</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-9 offset-md-3">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Security Settings -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-0">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-shield-alt text-primary me-2"></i> Security
                </h5>
                <p class="text-muted small">Manage your account security settings</p>
            </div>
            <div class="card-body pb-4">
                <h6 class="fw-bold mb-3">Change Password</h6>
                <form method="POST" action="?page=profile">
                    <div class="row mb-3">
                        <label for="currentPassword" class="col-md-3 col-form-label">Current Password</label>
                        <div class="col-md-9">
                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="newPassword" class="col-md-3 col-form-label">New Password</label>
                        <div class="col-md-9">
                            <input type="password" class="form-control" id="newPassword" name="new_password" 
                                   minlength="8" required>
                            <div class="form-text">Password must be at least 8 characters long</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="confirmPassword" class="col-md-3 col-form-label">Confirm New Password</label>
                        <div class="col-md-9">
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" 
                                   minlength="8" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-9 offset-md-3">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key me-1"></i> Change Password
                            </button>
                        </div>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <h6 class="fw-bold mb-3">Two-Factor Authentication</h6>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="mb-1">Enhance your account security by enabling 2FA</p>
                        <p class="text-muted small mb-0">Protect your account with an additional security layer</p>
                    </div>
                    <div>
                        <button class="btn btn-outline-primary" disabled>
                            <i class="fas fa-lock me-1"></i> Configure 2FA
                        </button>
                        <div class="form-text text-center">Coming Soon</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notification Preferences -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 pb-0">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-bell text-primary me-2"></i> Notification Preferences
                </h5>
                <p class="text-muted small">Manage how you receive notifications</p>
            </div>
            <div class="card-body pb-4">
                <form method="POST" action="?page=profile">
                    <div class="row mb-1">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="emailNotifications" checked disabled>
                                <label class="form-check-label" for="emailNotifications">
                                    Email Notifications
                                </label>
                                <div class="form-text">Important account notifications (cannot be disabled)</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="smsNotifications">
                                <label class="form-check-label" for="smsNotifications">
                                    SMS Notifications
                                </label>
                                <div class="form-text">Receive SMS alerts for account activity</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="WhatsAppNotifications">
                                <label class="form-check-label" for="WhatsAppNotifications">
                                    WhatsApp Notifications
                                </label>
                                <div class="form-text">Receive WhatsApp alerts for account activity</div>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="fw-bold mt-4 mb-3 border-bottom pb-2">Notification Types</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="loginAlerts" checked>
                                <label class="form-check-label" for="loginAlerts">
                                    Login Alerts
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="transactionAlerts" checked>
                                <label class="form-check-label" for="transactionAlerts">
                                    Transaction Alerts
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="solGroupUpdates" checked>
                                <label class="form-check-label" for="solGroupUpdates">
                                    SOL Group Updates
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="loanReminders" checked>
                                <label class="form-check-label" for="loanReminders">
                                    Loan Payment Reminders
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary" disabled>
                            <i class="fas fa-save me-1"></i> Save Preferences
                        </button>
                        <div class="form-text mt-2">Notification preferences will be available in a future update</div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Profile Photo Upload Modal -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1" aria-labelledby="uploadPhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadPhotoModalLabel">Update Profile Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($photoUploadError): ?>
                    <div class="alert alert-danger"><?= $photoUploadError ?></div>
                <?php endif; ?>
                <?php if ($photoUploadSuccess): ?>
                    <div class="alert alert-success"><?= $photoUploadSuccess ?></div>
                <?php endif; ?>
                
                <form method="POST" action="?page=profile" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="profilePhoto" class="form-label">Select Image</label>
                        <input class="form-control" type="file" id="profilePhoto" name="profile_photo" accept="image/jpeg, image/png">
                        <div class="form-text">Maximum file size: 5MB. Accepted formats: JPG, PNG</div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="upload_photo" class="btn btn-primary ms-2">
                            <i class="fas fa-upload me-1"></i> Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-xl {
    width: 120px;
    height: 120px;
    overflow: hidden;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
}

.list-group-item-action {
    padding-left: 0;
    padding-right: 0;
}

.list-group-item-action:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password validation
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    confirmPassword.addEventListener('input', function() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity("Passwords don't match");
        } else {
            confirmPassword.setCustomValidity('');
        }
    });
    
    newPassword.addEventListener('input', function() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity("Passwords don't match");
        } else {
            confirmPassword.setCustomValidity('');
        }
    });
});
</script>