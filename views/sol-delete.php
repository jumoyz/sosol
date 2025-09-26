<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once __DIR__ .'/../includes/config.php';
require_once __DIR__ .'/../includes/functions.php';
require_once __DIR__ .'/../includes/flash-messages.php';

// Set page title
$pageTitle = "Delete SOL Group";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Retrieve validated group ID
$groupId = requireValidGroupId('?page=dashboard');

// Initialize variables
$group = null;
$error = null;
$canDelete = false;

try {
    $db = getDbConnection();
    
    // Get group details and verify admin permissions
    $groupStmt = $db->prepare("
        SELECT sg.*, 
               u.full_name as admin_name,
               COUNT(sp.user_id) as member_count,
               COUNT(CASE WHEN sc.id IS NOT NULL THEN 1 END) as total_contributions
        FROM sol_groups sg
        INNER JOIN users u ON sg.admin_id = u.id
        LEFT JOIN sol_participants sp ON sg.id = sp.sol_group_id
        LEFT JOIN sol_contributions sc ON sp.id = sc.participant_id
        WHERE sg.id = ?
        GROUP BY sg.id
    ");
    $groupStmt->execute([$groupId]);
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        setFlashMessage('error', 'SOL group not found.');
        redirect('?page=dashboard');
    }
    
    // Check if current user is the admin
    if ($group['admin_id'] !== $userId) {
        setFlashMessage('error', 'You do not have permission to delete this group.');
        redirect('?page=sol-details&id=' . $groupId);
    }
    
    // Check if group can be deleted
    if ($group['status'] === 'active' && $group['total_contributions'] > 0) {
        $canDelete = false;
        $error = 'Cannot delete an active group with contributions. Please complete all cycles or contact support.';
    } elseif ($group['status'] === 'completed') {
        $canDelete = true;
    } elseif ($group['status'] === 'pending' || $group['total_contributions'] == 0) {
        $canDelete = true;
    }
    
} catch (PDOException $e) {
    error_log('SOL group delete error: ' . $e->getMessage());
    $error = 'An error occurred while loading group information.';
    redirect('?page=dashboard');
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if (!$canDelete) {
        setFlashMessage('error', 'This group cannot be deleted at this time.');
        redirect('?page=sol-details&id=' . $groupId);
    }
    
    $confirmText = trim($_POST['confirm_text'] ?? '');
    $expectedText = 'DELETE ' . strtoupper($group['name']);
    
    if ($confirmText !== $expectedText) {
        setFlashMessage('error', 'Confirmation text does not match. Please type exactly: ' . $expectedText);
        redirect('?page=sol-delete&id=' . $groupId);
    }
    
    try {
        $db->beginTransaction();
        
        // Delete related data in correct order to respect foreign key constraints
        
        // 1. Delete group messages
        $deleteMessagesStmt = $db->prepare("DELETE FROM group_messages WHERE sol_group_id = ?");
        $deleteMessagesStmt->execute([$groupId]);
        
        // 2. Delete contributions
        $deleteContribStmt = $db->prepare("
            DELETE sc FROM sol_contributions sc
            INNER JOIN sol_participants sp ON sc.participant_id = sp.id
            WHERE sp.sol_group_id = ?
        ");
        $deleteContribStmt->execute([$groupId]);
        
        // 3. Delete invitations
        $deleteInvitesStmt = $db->prepare("DELETE FROM sol_invitations WHERE sol_group_id = ?");
        $deleteInvitesStmt->execute([$groupId]);
        
        // 4. Delete participants
        $deleteParticipantsStmt = $db->prepare("DELETE FROM sol_participants WHERE sol_group_id = ?");
        $deleteParticipantsStmt->execute([$groupId]);
        
        // 5. Finally delete the group itself
        $deleteGroupStmt = $db->prepare("DELETE FROM sol_groups WHERE id = ?");
        $deleteGroupStmt->execute([$groupId]);
        
        // Log the deletion
        error_log("SOL Group deleted: {$group['name']} (ID: {$groupId}) by user {$userId}");
        
        $db->commit();
        
        setFlashMessage('success', 'SOL group "' . $group['name'] . '" has been permanently deleted.');
        redirect('?page=dashboard');
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('SOL group deletion error: ' . $e->getMessage());
        setFlashMessage('error', 'An error occurred while deleting the group. Please try again.');
        redirect('?page=sol-delete&id=' . $groupId);
    }
}

// Include header
include_once __DIR__ .'/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            
            <!-- Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div class="alert-icon bg-danger-subtle rounded-circle p-3 d-flex align-items-center justify-content-center">
                                <i class="fas fa-exclamation-triangle text-danger" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div>
                            <h2 class="fw-bold text-danger mb-1">Delete SOL Group</h2>
                            <p class="text-muted mb-0">This action cannot be undone. Please review carefully.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Flash Messages -->
            <div id="alertPlaceholder"></div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- Group Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Group Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="text-muted small">Group Name</div>
                                <p class="fw-medium mb-0"><?= htmlspecialchars($group['name']) ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <div class="text-muted small">Status</div>
                                <p class="mb-0">
                                    <span class="badge bg-<?= $group['status'] === 'active' ? 'success' : ($group['status'] === 'completed' ? 'secondary' : 'warning') ?>">
                                        <?= ucfirst($group['status']) ?>
                                    </span>
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <div class="text-muted small">Created</div>
                                <p class="mb-0"><?= date('F j, Y \a\t g:i A', strtotime($group['created_at'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="text-muted small">Members</div>
                                <p class="mb-0"><?= $group['member_count'] ?> members</p>
                            </div>
                            
                            <div class="mb-3">
                                <div class="text-muted small">Total Contributions</div>
                                <p class="mb-0"><?= $group['total_contributions'] ?> contributions</p>
                            </div>
                            
                            <div class="mb-3">
                                <div class="text-muted small">Contribution Amount</div>
                                <p class="mb-0"><?= number_format($group['contribution']) ?> HTG</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($group['description'])): ?>
                        <div class="mb-3">
                            <div class="text-muted small">Description</div>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($group['description'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Deletion Status -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Deletion Status</h5>
                    
                    <?php if ($canDelete): ?>
                        <div class="alert alert-warning">
                            <h6 class="alert-heading">
                                <i class="fas fa-check-circle me-2"></i>This group can be deleted
                            </h6>
                            <p class="mb-2">The following will be permanently removed:</p>
                            <ul class="mb-0">
                                <li>Group information and settings</li>
                                <li>All member participations</li>
                                <li>Group chat messages</li>
                                <li>Contribution records</li>
                                <li>Pending invitations</li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <h6 class="alert-heading">
                                <i class="fas fa-times-circle me-2"></i>This group cannot be deleted
                            </h6>
                            <p class="mb-0">
                                Active groups with contributions cannot be deleted to protect member investments. 
                                Please wait for all cycles to complete or contact support for assistance.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($canDelete): ?>
                <!-- Deletion Form -->
                <div class="card border-danger shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action is permanent and cannot be undone. All group data will be lost forever.
                        </div>
                        
                        <form method="POST" action="" id="deleteForm">
                            <div class="mb-4">
                                <label for="confirmText" class="form-label fw-bold">
                                    To confirm deletion, please type: 
                                    <code class="text-danger">DELETE <?= strtoupper($group['name']) ?></code>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="confirmText" 
                                       name="confirm_text"
                                       placeholder="Type the confirmation text exactly"
                                       required
                                       autocomplete="off">
                                <div class="form-text">
                                    This must match exactly (case-sensitive).
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="?page=sol-details&id=<?= $groupId ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                                
                                <button type="submit" 
                                        name="confirm_delete" 
                                        class="btn btn-danger"
                                        id="deleteButton"
                                        disabled>
                                    <i class="fas fa-trash-alt me-2"></i>Delete Group Permanently
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Alternative Actions -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 text-center">
                        <h5 class="fw-bold mb-3">Alternative Actions</h5>
                        <p class="text-muted mb-4">
                            Since this group cannot be deleted, here are some other options:
                        </p>
                        
                        <div class="d-grid gap-2 d-md-block">
                            <a href="?page=sol-details&id=<?= $groupId ?>" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Return to Group
                            </a>
                            
                            <a href="?page=sol-manage&id=<?= $groupId ?>" class="btn btn-outline-primary">
                                <i class="fas fa-cog me-2"></i>Manage Group
                            </a>
                            
                            <a href="?page=contact" class="btn btn-outline-secondary">
                                <i class="fas fa-envelope me-2"></i>Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<style>
    .alert-icon {
        width: 60px;
        height: 60px;
    }
    
    .card.border-danger {
        border-color: #dc3545 !important;
    }
    
    .text-danger {
        color: #dc3545 !important;
    }
    
    .bg-danger-subtle {
        background-color: rgba(220, 53, 69, 0.1) !important;
    }
    
    code {
        background-color: #f8f9fa;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 0.9em;
    }
    
    .form-control:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }
</style>

<script>
    function showAlert(type, message) {
        const alertPlaceholder = document.getElementById('alertPlaceholder');
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `<div class="alert alert-${type} alert-dismissible" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        alertPlaceholder.append(wrapper);
        setTimeout(() => {
            wrapper.remove();
        }, 5000);   
    }
    
    document.addEventListener('DOMContentLoaded', function () {
        const confirmText = document.getElementById('confirmText');
        const deleteButton = document.getElementById('deleteButton');
        const deleteForm = document.getElementById('deleteForm');
        const expectedText = 'DELETE <?= strtoupper($group['name']) ?>';
        
        // Handle flash messages if they exist
        <?php if (isset($_SESSION['flash_type']) && isset($_SESSION['flash_message'])): ?>
            showAlert('<?= $_SESSION['flash_type'] ?>', '<?= $_SESSION['flash_message'] ?>');
            <?php 
            unset($_SESSION['flash_type']);
            unset($_SESSION['flash_message']);
            ?>
        <?php endif; ?>
        
        <?php if ($canDelete): ?>
            // Enable/disable delete button based on confirmation text
            if (confirmText && deleteButton) {
                confirmText.addEventListener('input', function() {
                    const isMatch = this.value === expectedText;
                    deleteButton.disabled = !isMatch;
                    
                    if (isMatch) {
                        deleteButton.classList.remove('btn-secondary');
                        deleteButton.classList.add('btn-danger');
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else {
                        deleteButton.classList.remove('btn-danger');
                        deleteButton.classList.add('btn-secondary');
                        this.classList.remove('is-valid');
                        if (this.value.length > 0) {
                            this.classList.add('is-invalid');
                        } else {
                            this.classList.remove('is-invalid');
                        }
                    }
                });
                
                // Final confirmation before submission
                deleteForm.addEventListener('submit', function(e) {
                    if (confirmText.value !== expectedText) {
                        e.preventDefault();
                        showAlert('danger', 'Confirmation text does not match exactly.');
                        return false;
                    }
                    
                    const finalConfirm = confirm(
                        'Are you absolutely sure you want to delete this SOL group? ' +
                        'This action cannot be undone and all data will be permanently lost.'
                    );
                    
                    if (!finalConfirm) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Show loading state
                    deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
                    deleteButton.disabled = true;
                });
            }
        <?php endif; ?>
    });
</script>

<?php include_once __DIR__ .'/../includes/footer.php'; ?>
