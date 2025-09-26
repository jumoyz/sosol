<?php
// Set page title
$pageTitle = "Create Campaign";

// Require authentication
if (!isLoggedIn()) {
    setFlashMessage('error', 'You must be logged in to create a campaign.');
    redirect('?page=login');
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_full_name'] ?? '';

// Initialize variables
$categories = ['Business', 'Education', 'Community', 'Emergency', 'Environment', 'Health', 'Technology', 'Other'];
$errors = [];
$formData = [
    'title' => '',
    'category' => '',
    'goal_amount' => '',
    'description' => '',
    'end_date' => '',
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $formData = [
        'title' => filter_input(INPUT_POST, 'title', FILTER_UNSAFE_RAW),
        'category' => filter_input(INPUT_POST, 'category', FILTER_UNSAFE_RAW),
        'goal_amount' => filter_input(INPUT_POST, 'goal_amount', FILTER_VALIDATE_FLOAT),
        'description' => filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW),
        'end_date' => filter_input(INPUT_POST, 'end_date', FILTER_UNSAFE_RAW),
    ];

    // Basic validation
    if (empty($formData['title'])) {
        $errors[] = 'Campaign title is required';
    }
    
    if (empty($formData['category']) || !in_array($formData['category'], $categories)) {
        $errors[] = 'Please select a valid category';
    }
    
    if (empty($formData['goal_amount']) || $formData['goal_amount'] < 100) {
        $errors[] = 'Goal amount must be at least 100 HTG';
    }
    
    if (empty($formData['description'])) {
        $errors[] = 'Campaign description is required';
    }
    
    if (empty($formData['end_date'])) {
        $errors[] = 'End date is required';
    } else {
        $endDate = new DateTime($formData['end_date']);
        $today = new DateTime();
        if ($endDate <= $today) {
            $errors[] = 'End date must be in the future';
        }
    }
    
    // Image handling
    $imagePath = null;
    if (isset($_FILES['campaign_image']) && $_FILES['campaign_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['campaign_image'];
        
        // Check for errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was an error uploading your image. Please try again.';
        } else {
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $uploadedFileType = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);
            
            if (!in_array($uploadedFileType, $allowedTypes)) {
                $errors[] = 'Only JPG, PNG and GIF images are allowed';
            } else {
                // Create uploads directory if it doesn't exist
                $uploadDir = 'uploads/campaigns/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate a unique filename
                $fileName = generateUuid() . '-' . time() . '-' . sanitizeFileName($file['name']);
                $imagePath = $uploadDir . $fileName;
                
                // Save file
                if (!move_uploaded_file($file['tmp_name'], $imagePath)) {
                    $errors[] = 'Failed to save the image. Please try again.';
                    $imagePath = null;
                }
            }
        }
    }
    
    // If no errors, save the campaign
    if (empty($errors)) {
        try {
            $db = getDbConnection();
            $db->beginTransaction();
            
            // Generate UUID for campaign
            $campaignId = generateUuid();
            
            // Prepare campaign data
            $campaignStmt = $db->prepare("
                INSERT INTO campaigns (
                    id, creator_id, title, description, goal_amount,
                    category, image_url, start_date, end_date, is_featured,
                    status, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, CURRENT_DATE, ?, 0,
                    'pending', NOW(), NOW()
                )
            ");
            
            $campaignStmt->execute([
                $campaignId,
                $userId,
                htmlspecialchars($formData['title'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($formData['description'], ENT_QUOTES, 'UTF-8'),
                $formData['goal_amount'],
                htmlspecialchars($formData['category'], ENT_QUOTES, 'UTF-8'),
                $imagePath,
                $formData['end_date']
            ]);
            
            // Record user activity
            $activityStmt = $db->prepare("
                INSERT INTO activities (
                    user_id, activity_type, reference_id, details, created_at
                ) VALUES (
                    ?, 'campaign_created', ?, ?, NOW()
                )
            ");
            
            $activityDetails = json_encode([
                'campaign_title' => $formData['title'],
                'goal_amount' => $formData['goal_amount']
            ]);
            
            $activityStmt->execute([
                $userId,
                $campaignId,
                $activityDetails
            ]);
            
            $db->commit();
            
            setFlashMessage('success', 'Your campaign has been submitted for approval and will be visible once approved.');
            redirect('?page=my-campaigns');
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Campaign creation error: ' . $e->getMessage());
            $errors[] = 'An error occurred while saving your campaign. Please try again later.';
            
            // If image was uploaded but DB operation failed, remove the image
            if ($imagePath && file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 fw-bold">Create a Crowdfunding Campaign</h1>
            <p class="text-muted">Share your project with the community and raise funds</p>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Please correct the following errors:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label for="title" class="form-label fw-bold">Campaign Title</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                value="<?= htmlspecialchars($formData['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                placeholder="Enter a clear, attention-grabbing title" required>
                            <div class="form-text">Choose a title that quickly explains what you're raising funds for.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="category" class="form-label fw-bold">Category</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category ?>" 
                                        <?= ($formData['category'] === $category) ? 'selected' : '' ?>>
                                        <?= $category ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Choose the category that best fits your campaign.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="goal_amount" class="form-label fw-bold">Goal Amount (HTG)</label>
                            <div class="input-group">
                                <span class="input-group-text">HTG</span>
                                <input type="number" class="form-control" id="goal_amount" name="goal_amount" 
                                    value="<?= htmlspecialchars($formData['goal_amount'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                    placeholder="5000" min="100" step="100" required>
                            </div>
                            <div class="form-text">Set a realistic funding goal for your project (minimum 100 HTG).</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="end_date" class="form-label fw-bold">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                value="<?= htmlspecialchars($formData['end_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                            <div class="form-text">Choose when your fundraising campaign will end.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="campaign_image" class="form-label fw-bold">Campaign Image</label>
                            <input type="file" class="form-control" id="campaign_image" name="campaign_image" accept="image/*">
                            <div class="form-text">Upload a compelling image that represents your campaign (JPEG, PNG or GIF, max 2MB).</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="description" class="form-label fw-bold">Campaign Description</label>
                            <textarea class="form-control" id="description" name="description" rows="8" 
                                placeholder="Explain your project in detail..." required><?= htmlspecialchars($formData['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="form-text">
                                Provide a detailed description of your project. Explain why it matters, how the funds will be used, and why people should support it.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Create Campaign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold">Campaign Guidelines</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0">All campaigns are reviewed before being published</li>
                        <li class="list-group-item px-0">You must provide accurate and truthful information</li>
                        <li class="list-group-item px-0">Campaigns must align with our community guidelines</li>
                        <li class="list-group-item px-0">A 5% platform fee applies to successful campaigns</li>
                        <li class="list-group-item px-0">Funds will be transferred to your wallet once the campaign ends</li>
                    </ul>
                </div>
            </div>
            
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold">Tips for Success</h5>
                    <ol class="mb-0">
                        <li class="mb-2">Be clear and specific about what the funds will be used for</li>
                        <li class="mb-2">Use a high-quality image that represents your campaign</li>
                        <li class="mb-2">Share your campaign with friends and on social media</li>
                        <li class="mb-2">Post regular updates to keep donors engaged</li>
                        <li class="mb-2">Thank your supporters and keep them informed of progress</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Campaign Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>1. Campaign Creation and Approval</h6>
                <p>All campaigns must be approved by SoSol administrators before being published. We reserve the right to reject or remove campaigns that violate our guidelines or terms of service.</p>
                
                <h6>2. Fees</h6>
                <p>SoSol charges a 5% platform fee on all funds raised through successful campaigns. This fee helps us maintain and improve the platform.</p>
                
                <h6>3. Fund Distribution</h6>
                <p>Once a campaign ends, funds will be transferred to your SoSol wallet, minus applicable fees.</p>
                
                <h6>4. Campaign Responsibility</h6>
                <p>Campaign creators are responsible for delivering on promises made to supporters. Failure to use funds as described may result in account suspension and legal consequences.</p>
                
                <h6>5. Prohibited Campaigns</h6>
                <p>Campaigns promoting illegal activities, hate speech, violence, or fraudulent purposes are strictly prohibited.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to sanitize file names
function sanitizeFileName($fileName) {
    // Remove any characters that aren't alphanumeric, dots, dashes, or underscores
    $fileName = preg_replace('/[^\w\.-]/', '_', $fileName);
    // Ensure filename isn't too long
    return substr($fileName, -100);
}
?>