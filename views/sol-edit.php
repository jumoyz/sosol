<?php
// Set page title
$pageTitle = "Edit SOL Group";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Get validated group ID
$groupId = requireValidGroupId('?page=sol-groups');

// Initialize variables
$group = null;
$error = null;
$success = null;

try {
    $db = getDbConnection();
    
    // Get group data and verify user has permission to edit
    $groupStmt = $db->prepare("
        SELECT sg.*, sp.role
        FROM sol_groups sg
        INNER JOIN sol_participants sp ON sg.id = sp.sol_group_id
        WHERE sg.id = ? AND sp.user_id = ? AND (sp.role = 'admin' OR sp.role = 'manager')
    ");
    $groupStmt->execute([$groupId, $userId]);
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        setFlashMessage('error', 'SOL group not found or you do not have permission to edit it.');
        redirect('?page=sol-groups');
    }
    
} catch (PDOException $e) {
    error_log('SOL group edit fetch error: ' . $e->getMessage());
    $error = 'An error occurred while loading the SOL group.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_group'])) {
    // Validate and sanitize input
    $name = trim($_POST['group_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $memberLimit = intval($_POST['member_limit'] ?? 0);
    $contribution = floatval($_POST['contribution'] ?? 0);
    $frequency = $_POST['frequency'] ?? '';
    $totalCycles = intval($_POST['total_cycles'] ?? 0);
    $visibility = $_POST['visibility'] ?? 'public';
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Group name is required.';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Group name must be less than 100 characters.';
    }
    
    if (strlen($description) > 500) {
        $errors[] = 'Description must be less than 500 characters.';
    }
    
    if ($memberLimit < 2 || $memberLimit > 50) {
        $errors[] = 'Member limit must be between 2 and 50.';
    }
    
    if ($contribution <= 0 || $contribution > 100000) {
        $errors[] = 'Contribution amount must be between 1 and 100,000 HTG.';
    }
    
    if (!in_array($frequency, ['daily', 'every3days', 'weekly', 'biweekly', 'monthly'])) {
        $errors[] = 'Invalid frequency selected.';
    }
    
    if ($totalCycles < 2 || $totalCycles > 100) {
        $errors[] = 'Total cycles must be between 2 and 100.';
    }
    
    if (!in_array($visibility, ['public', 'private'])) {
        $errors[] = 'Invalid visibility setting.';
    }
    
    // Check if group has already started (cannot change certain fields)
    if ($group['status'] !== 'pending') {
        // For active groups, restrict changes to critical fields
        if ($memberLimit != $group['member_limit']) {
            $errors[] = 'Cannot change member limit for active groups.';
        }
        if ($contribution != $group['contribution']) {
            $errors[] = 'Cannot change contribution amount for active groups.';
        }
        if ($frequency != $group['frequency']) {
            $errors[] = 'Cannot change frequency for active groups.';
        }
        if ($totalCycles != $group['total_cycles']) {
            $errors[] = 'Cannot change total cycles for active groups.';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update group
            $updateStmt = $db->prepare("
                UPDATE sol_groups 
                SET name = ?, description = ?, member_limit = ?, contribution = ?, 
                    frequency = ?, total_cycles = ?, visibility = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                $name, $description, $memberLimit, $contribution,
                $frequency, $totalCycles, $visibility, $groupId
            ]);
            
            $db->commit();
            
            setFlashMessage('success', 'SOL group updated successfully!');
            redirect('?page=sol-details&id=' . $groupId);
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('SOL group update error: ' . $e->getMessage());
            $error = 'An error occurred while updating the SOL group.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

?>

<!-- Alert Placeholder -->
<div id="alertPlaceholder"></div>

<div class="row">
    <div class="col-12">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="?page=sol-groups">SOL Groups</a></li>
                <li class="breadcrumb-item"><a href="?page=sol-details&id=<?= $groupId ?>">Group Details</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Group</li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Edit SOL Group</h1>
                <p class="text-muted">Update your SOL group settings and information</p>
            </div>
            <a href="?page=sol-details&id=<?= $groupId ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Group
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8 col-xl-9">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-edit text-primary me-2"></i>Group Information
                </h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="" id="editGroupForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="groupName" class="form-label">Group Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="groupName" name="group_name" 
                                       value="<?= htmlspecialchars($group['name']) ?>" required maxlength="100">
                                <small class="form-text text-muted">Choose a descriptive name for your SOL group</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="visibility" class="form-label">Visibility</label>
                                <select class="form-select" id="visibility" name="visibility">
                                    <option value="public" <?= $group['visibility'] === 'public' ? 'selected' : '' ?>>Public</option>
                                    <option value="private" <?= $group['visibility'] === 'private' ? 'selected' : '' ?>>Private</option>
                                </select>
                                <small class="form-text text-muted">Public groups can be discovered by anyone</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  maxlength="500" placeholder="Describe the purpose and goals of your SOL group"><?= htmlspecialchars($group['description']) ?></textarea>
                        <small class="form-text text-muted">Optional - Help members understand what this group is about</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="memberLimit" class="form-label">Member Limit <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="memberLimit" name="member_limit" 
                                       value="<?= $group['member_limit'] ?>" min="2" max="50" required
                                       <?= $group['status'] !== 'pending' ? 'disabled' : '' ?>>
                                <small class="form-text text-muted">Maximum number of members (2-50)
                                    <?= $group['status'] !== 'pending' ? ' - Cannot change for active groups' : '' ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="totalCycles" class="form-label">Total Cycles <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="totalCycles" name="total_cycles" 
                                       value="<?= $group['total_cycles'] ?>" min="2" max="100" required
                                       <?= $group['status'] !== 'pending' ? 'disabled' : '' ?>>
                                <small class="form-text text-muted">Number of payout rounds (2-100)
                                    <?= $group['status'] !== 'pending' ? ' - Cannot change for active groups' : '' ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contribution" class="form-label">Contribution Amount (HTG) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="contribution" name="contribution" 
                                       value="<?= $group['contribution'] ?>" min="1" max="100000" step="0.01" required
                                       <?= $group['status'] !== 'pending' ? 'disabled' : '' ?>>
                                <small class="form-text text-muted">Amount each member contributes per cycle
                                    <?= $group['status'] !== 'pending' ? ' - Cannot change for active groups' : '' ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="frequency" class="form-label">Contribution Frequency <span class="text-danger">*</span></label>
                                <select class="form-select" id="frequency" name="frequency" required
                                        <?= $group['status'] !== 'pending' ? 'disabled' : '' ?>>
                                    <option value="daily" <?= $group['frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option>
                                    <option value="every3days" <?= $group['frequency'] === 'every3days' ? 'selected' : '' ?>>Every 3 Days</option>
                                    <option value="weekly" <?= $group['frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                    <option value="biweekly" <?= $group['frequency'] === 'biweekly' ? 'selected' : '' ?>>Bi-weekly</option>
                                    <option value="monthly" <?= $group['frequency'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                </select>
                                <small class="form-text text-muted">How often members contribute
                                    <?= $group['status'] !== 'pending' ? ' - Cannot change for active groups' : '' ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Fields marked with <span class="text-danger">*</span> are required
                            </small>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary me-2" onclick="history.back()">Cancel</button>
                            <button type="submit" name="update_group" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Group
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="card-title mb-0">
                    <i class="fas fa-info-circle text-info me-2"></i>Group Status
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted">Current Status</small>
                    <div>
                        <span class="badge bg-<?= $group['status'] === 'active' ? 'success' : ($group['status'] === 'completed' ? 'secondary' : 'warning') ?> fs-6">
                            <?= ucfirst($group['status']) ?>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted">Current Cycle</small>
                    <div class="fw-bold"><?= $group['current_cycle'] ?> of <?= $group['total_cycles'] ?></div>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted">Created</small>
                    <div><?= date('M j, Y', strtotime($group['created_at'])) ?></div>
                </div>
                
                <?php if ($group['status'] !== 'pending'): ?>
                    <div class="alert alert-warning alert-sm">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <small>Some settings cannot be changed for active groups to maintain fairness among members.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white">
                <h6 class="card-title mb-0">
                    <i class="fas fa-calculator text-success me-2"></i>Payout Preview
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted">Payout per cycle</small>
                    <div class="fw-bold text-success">
                        <?= number_format($group['contribution'] * $group['member_limit'], 2) ?> HTG
                    </div>
                </div>
                <div class="mb-2">
                    <small class="text-muted">Total pool value</small>
                    <div class="fw-bold">
                        <?= number_format($group['contribution'] * $group['member_limit'] * $group['total_cycles'], 2) ?> HTG
                    </div>
                </div>
                <div>
                    <small class="text-muted">Individual contribution</small>
                    <div class="fw-bold">
                        <?= number_format($group['contribution'] * $group['total_cycles'], 2) ?> HTG
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editGroupForm');
    const memberLimitInput = document.getElementById('memberLimit');
    const contributionInput = document.getElementById('contribution');
    const totalCyclesInput = document.getElementById('totalCycles');
    
    // Real-time payout calculation
    function updatePayoutPreview() {
        const memberLimit = parseInt(memberLimitInput.value) || 0;
        const contribution = parseFloat(contributionInput.value) || 0;
        const totalCycles = parseInt(totalCyclesInput.value) || 0;
        
        const payoutPerCycle = contribution * memberLimit;
        const totalPoolValue = payoutPerCycle * totalCycles;
        const individualContribution = contribution * totalCycles;
        
        // Update preview if elements exist
        const payoutElement = document.querySelector('.text-success');
        const totalPoolElement = payoutElement?.parentElement?.nextElementSibling?.querySelector('.fw-bold');
        const individualElement = totalPoolElement?.parentElement?.nextElementSibling?.querySelector('.fw-bold');
        
        if (payoutElement) {
            payoutElement.textContent = payoutPerCycle.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' HTG';
        }
        if (totalPoolElement) {
            totalPoolElement.textContent = totalPoolValue.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' HTG';
        }
        if (individualElement) {
            individualElement.textContent = individualContribution.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' HTG';
        }
    }
    
    // Add event listeners for real-time updates (only if fields are not disabled)
    if (!memberLimitInput.disabled) {
        memberLimitInput.addEventListener('input', updatePayoutPreview);
    }
    if (!contributionInput.disabled) {
        contributionInput.addEventListener('input', updatePayoutPreview);
    }
    if (!totalCyclesInput.disabled) {
        totalCyclesInput.addEventListener('input', updatePayoutPreview);
    }
    
    // Form validation
    form.addEventListener('submit', function(e) {
        const name = document.getElementById('groupName').value.trim();
        const memberLimit = parseInt(memberLimitInput.value);
        const contribution = parseFloat(contributionInput.value);
        const totalCycles = parseInt(totalCyclesInput.value);
        
        let errors = [];
        
        if (!name) {
            errors.push('Group name is required.');
        }
        
        if (memberLimit < 2 || memberLimit > 50) {
            errors.push('Member limit must be between 2 and 50.');
        }
        
        if (contribution <= 0 || contribution > 100000) {
            errors.push('Contribution amount must be between 1 and 100,000 HTG.');
        }
        
        if (totalCycles < 2 || totalCycles > 100) {
            errors.push('Total cycles must be between 2 and 100.');
        }
        
        if (errors.length > 0) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n' + errors.join('\n'));
            return false;
        }
    });
    
    // Handle flash messages
    <?php if (isset($_SESSION['flash_type']) && isset($_SESSION['flash_message'])): ?>
        showAlert('<?= $_SESSION['flash_type'] ?>', '<?= $_SESSION['flash_message'] ?>');
        <?php 
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_message']);
        ?>
    <?php endif; ?>
});

function showAlert(type, message) {
    const alertPlaceholder = document.getElementById('alertPlaceholder');
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
    alertPlaceholder.append(wrapper);
    setTimeout(() => {
        wrapper.remove();
    }, 5000);
}
</script>
