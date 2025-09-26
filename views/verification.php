<?php
// Set page title
$pageTitle = "Account Verification";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$user = null;
$verificationStatus = null;
$documentUploadError = null;
$documentUploadSuccess = null;

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_verification'])) {
    $idType = filter_input(INPUT_POST, 'id_type', FILTER_UNSAFE_RAW);
    $idNumber = filter_input(INPUT_POST, 'id_number', FILTER_UNSAFE_RAW);
    $address = filter_input(INPUT_POST, 'address', FILTER_UNSAFE_RAW);
    $dateOfBirth = filter_input(INPUT_POST, 'date_of_birth', FILTER_UNSAFE_RAW);
    
    // Sanitize inputs
    $idType = htmlspecialchars(trim($idType), ENT_QUOTES, 'UTF-8');
    $idNumber = htmlspecialchars(trim($idNumber), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars(trim($address), ENT_QUOTES, 'UTF-8');
    $dateOfBirth = htmlspecialchars(trim($dateOfBirth), ENT_QUOTES, 'UTF-8');
    
    // Validation
    $errors = [];
    
    if (empty($idType)) {
        $errors[] = 'Please select an ID type.';
    }
    
    if (empty($idNumber)) {
        $errors[] = 'Please enter your ID number.';
    }
    
    if (empty($address)) {
        $errors[] = 'Please enter your address.';
    }
    
    if (empty($dateOfBirth)) {
        $errors[] = 'Please enter your date of birth.';
    } elseif (strtotime($dateOfBirth) > strtotime('-18 years')) {
        $errors[] = 'You must be at least 18 years old to use this service.';
    }
    
    // File validation
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    // Front ID document
    if (!isset($_FILES['id_front']) || $_FILES['id_front']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please upload the front of your ID document.';
    } elseif ($_FILES['id_front']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Front ID document upload failed. Please try again.';
    } elseif ($_FILES['id_front']['size'] > $maxFileSize) {
        $errors[] = 'Front ID document is too large. Maximum size is 5MB.';
    } elseif (!in_array($_FILES['id_front']['type'], $allowedTypes)) {
        $errors[] = 'Invalid file type for front ID document. Only JPG, PNG and PDF files are allowed.';
    }
    
    // Back ID document
    if (!isset($_FILES['id_back']) || $_FILES['id_back']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please upload the back of your ID document.';
    } elseif ($_FILES['id_back']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Back ID document upload failed. Please try again.';
    } elseif ($_FILES['id_back']['size'] > $maxFileSize) {
        $errors[] = 'Back ID document is too large. Maximum size is 5MB.';
    } elseif (!in_array($_FILES['id_back']['type'], $allowedTypes)) {
        $errors[] = 'Invalid file type for back ID document. Only JPG, PNG and PDF files are allowed.';
    }
    
    // Selfie with ID
    if (!isset($_FILES['selfie']) || $_FILES['selfie']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please upload a selfie with your ID document.';
    } elseif ($_FILES['selfie']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Selfie upload failed. Please try again.';
    } elseif ($_FILES['selfie']['size'] > $maxFileSize) {
        $errors[] = 'Selfie is too large. Maximum size is 5MB.';
    } elseif (!in_array($_FILES['selfie']['type'], $allowedTypes)) {
        $errors[] = 'Invalid file type for selfie. Only JPG and PNG files are allowed.';
    }
    
    // If there are no errors, proceed with upload and database update
    if (empty($errors)) {
        try {
            $db = getDbConnection();
            
            // Create upload directory if it doesn't exist
            $uploadDir = '../public/uploads/verification/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Upload front ID document
            $frontIdName = $userId . '_front_' . time() . '_' . basename($_FILES['id_front']['name']);
            $frontIdPath = $uploadDir . $frontIdName;
            move_uploaded_file($_FILES['id_front']['tmp_name'], $frontIdPath);
            $frontIdUrl = '/public/uploads/verification/' . $frontIdName;
            
            // Upload back ID document
            $backIdName = $userId . '_back_' . time() . '_' . basename($_FILES['id_back']['name']);
            $backIdPath = $uploadDir . $backIdName;
            move_uploaded_file($_FILES['id_back']['tmp_name'], $backIdPath);
            $backIdUrl = '/public/uploads/verification/' . $backIdName;
            
            // Upload selfie
            $selfieName = $userId . '_selfie_' . time() . '_' . basename($_FILES['selfie']['name']);
            $selfiePath = $uploadDir . $selfieName;
            move_uploaded_file($_FILES['selfie']['tmp_name'], $selfiePath);
            $selfieUrl = '/public/uploads/verification/' . $selfieName;
            
            // Check if verification record already exists
            $checkStmt = $db->prepare("SELECT id FROM kyc_verifications WHERE user_id = ?");
            $checkStmt->execute([$userId]);
            
            if ($checkStmt->rowCount() > 0) {
                // Update existing verification record
                $updateStmt = $db->prepare("
                    UPDATE kyc_verifications 
                    SET id_type = ?, id_number = ?, address = ?, date_of_birth = ?,
                        id_front_url = ?, id_back_url = ?, selfie_url = ?,
                        status = 'pending', updated_at = NOW()
                    WHERE user_id = ?
                ");
                $updateStmt->execute([
                    $idType, $idNumber, $address, $dateOfBirth,
                    $frontIdUrl, $backIdUrl, $selfieUrl,
                    $userId
                ]);
            } else {
                // Create new verification record
                $insertStmt = $db->prepare("
                    INSERT INTO kyc_verifications 
                    (user_id, id_type, id_number, address, date_of_birth, id_front_url, id_back_url, selfie_url, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
                ");
                $insertStmt->execute([
                    $userId, $idType, $idNumber, $address, $dateOfBirth,
                    $frontIdUrl, $backIdUrl, $selfieUrl
                ]);
            }
            
            // Update user's verification status
            $userUpdateStmt = $db->prepare("UPDATE users SET verification_status = 'pending', updated_at = NOW() WHERE id = ?");
            $userUpdateStmt->execute([$userId]);
            
            setFlashMessage('success', 'Your verification documents have been submitted successfully. We will review them shortly.');
            redirect('?page=verification');
        } catch (PDOException $e) {
            error_log('Verification submission error: ' . $e->getMessage());
            setFlashMessage('error', 'An error occurred while processing your verification. Please try again later.');
        }
    } else {
        // Set error message
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
        }
    }
}

// Fetch user data and verification status
try {
    $db = getDbConnection();
    
    // Get user data
    $userStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get verification status
    $verificationStmt = $db->prepare("SELECT * FROM kyc_verifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $verificationStmt->execute([$userId]);
    $verification = $verificationStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($verification) {
        $verificationStatus = $verification['status'];
    } else {
        $verificationStatus = 'not_submitted';
    }
    
} catch (PDOException $e) {
    error_log('Verification data fetch error: ' . $e->getMessage());
    setFlashMessage('error', 'Unable to load verification data. Please try again later.');
}
?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-4 pb-0">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-id-card text-primary me-2"></i> Verification Status
                </h5>
            </div>
            <div class="card-body">
                <?php if ($verificationStatus === 'approved'): ?>
                    <div class="verification-status approved text-center py-4">
                        <div class="verification-icon mb-3">
                            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="mb-2">Verification Approved</h5>
                        <p class="text-muted mb-0">Your account has been fully verified.</p>
                    </div>
                <?php elseif ($verificationStatus === 'pending'): ?>
                    <div class="verification-status pending text-center py-4">
                        <div class="verification-icon mb-3">
                            <i class="fas fa-clock text-warning" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="mb-2">Verification In Progress</h5>
                        <p class="text-muted mb-0">We are currently reviewing your documents.</p>
                        <p class="text-muted">This usually takes 1-2 business days.</p>
                    </div>
                <?php elseif ($verificationStatus === 'rejected'): ?>
                    <div class="verification-status rejected text-center py-4">
                        <div class="verification-icon mb-3">
                            <i class="fas fa-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="mb-2">Verification Rejected</h5>
                        <p class="text-muted mb-0">Please submit new documents.</p>
                        <?php if (isset($verification['rejection_reason']) && !empty($verification['rejection_reason'])): ?>
                            <div class="alert alert-danger mt-3">
                                <strong>Reason:</strong> <?= htmlspecialchars($verification['rejection_reason']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="verification-status not-submitted text-center py-4">
                        <div class="verification-icon mb-3">
                            <i class="fas fa-user-shield text-muted" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="mb-2">Not Verified</h5>
                        <p class="text-muted mb-0">Please submit your verification documents.</p>
                    </div>
                <?php endif; ?>

                <hr class="my-4">
                
                <div class="verification-benefits">
                    <h6 class="fw-bold mb-3">Benefits of Verification</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i> Higher transaction limits
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i> Access to all lending features
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i> Ability to create SOL groups
                        </li>
                        <li>
                            <i class="fas fa-check-circle text-success me-2"></i> Increased security
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Verification Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 pb-0">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-user-check text-primary me-2"></i> Account Verification
                </h5>
                <p class="text-muted small">Submit your documents for KYC verification</p>
            </div>
            <div class="card-body">
                <?php if ($verificationStatus === 'approved'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i> Your account is already fully verified. You have access to all features.
                    </div>
                <?php elseif ($verificationStatus === 'pending'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Your verification is currently being processed. We'll notify you once it's complete.
                    </div>
                <?php else: ?>
                    <div class="alert alert-primary mb-4">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-info-circle fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="alert-heading mb-1">Verification Requirements</h6>
                                <p class="mb-0">To complete verification, please provide:</p>
                                <ul class="mb-0">
                                    <li>A valid government-issued ID (front and back)</li>
                                    <li>A selfie of you holding your ID</li>
                                    <li>Your current address and personal information</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="?page=verification" enctype="multipart/form-data">
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Personal Information</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="fullName" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="fullName" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="dateOfBirth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dateOfBirth" name="date_of_birth" max="<?= date('Y-m-d', strtotime('-18 years')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phoneNumber" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phoneNumber" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="idType" class="form-label">ID Type</label>
                                <select class="form-select" id="idType" name="id_type" required>
                                    <option value="" selected disabled>Select ID Type</option>
                                    <option value="national_id">National ID Card</option>
                                    <option value="passport">Passport</option>
                                    <option value="drivers_license">Driver's License</option>
                                    <option value="voter_id">Voter ID</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="idNumber" class="form-label">ID Number</label>
                                <input type="text" class="form-control" id="idNumber" name="id_number" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="address" class="form-label">Residential Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                        </div>
                        
                        <h6 class="fw-bold mb-3 border-bottom pb-2">Identity Documents</h6>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="idFront" class="form-label">ID Document (Front)</label>
                                <input class="form-control" type="file" id="idFront" name="id_front" accept="image/jpeg,image/png,application/pdf" required>
                                <div class="form-text">JPG, PNG, or PDF. Max 5MB.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="idBack" class="form-label">ID Document (Back)</label>
                                <input class="form-control" type="file" id="idBack" name="id_back" accept="image/jpeg,image/png,application/pdf" required>
                                <div class="form-text">JPG, PNG, or PDF. Max 5MB.</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="selfie" class="form-label">Selfie with ID</label>
                            <input class="form-control" type="file" id="selfie" name="selfie" accept="image/jpeg,image/png" required>
                            <div class="form-text">Take a photo of yourself holding your ID document. JPG or PNG. Max 5MB.</div>
                        </div>
                        
                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input" id="verificationConsent" required>
                            <label class="form-check-label" for="verificationConsent">
                                I certify that all information provided is accurate, and I consent to SoSol processing my personal data for verification purposes in accordance with the <a href="?page=privacy" target="_blank">Privacy Policy</a>.
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="submit_verification" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Submit Verification
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.verification-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    background-color: #f8f9fa;
}

.card-header h5 {
    margin-bottom: 0;
}

.list-group-item-action {
    padding-left: 0;
    padding-right: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preview image uploads
    const idFront = document.getElementById('idFront');
    const idBack = document.getElementById('idBack');
    const selfie = document.getElementById('selfie');
    
    if (idFront) {
        idFront.addEventListener('change', function() {
            const file = this.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                alert('File is too large. Maximum size is 5MB.');
                this.value = '';
            }
        });
    }
    
    if (idBack) {
        idBack.addEventListener('change', function() {
            const file = this.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                alert('File is too large. Maximum size is 5MB.');
                this.value = '';
            }
        });
    }
    
    if (selfie) {
        selfie.addEventListener('change', function() {
            const file = this.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                alert('File is too large. Maximum size is 5MB.');
                this.value = '';
            }
        });
    }
});
</script>