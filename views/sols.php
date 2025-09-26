<?php
// Set page title
$pageTitle = "SOL Groups";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Initialize variables
$myGroups = [];
$availableGroups = [];
$error = null;

try {
    $db = getDbConnection();
    
    // Get groups the user participates in
    $myGroupsStmt = $db->prepare("
        SELECT sg.*, 
               COUNT(sp.user_id) as member_count,
               MAX(CASE WHEN sp.user_id = ? THEN sp.role ELSE NULL END) as user_role
        FROM sol_groups sg
        INNER JOIN sol_participants sp ON sg.id = sp.sol_group_id
        GROUP BY sg.id
        HAVING user_role IS NOT NULL
        ORDER BY sg.created_at DESC
    ");
    $myGroupsStmt->execute([$userId]);
    $myGroups = $myGroupsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available groups to join
    $availableGroupsStmt = $db->prepare("
        SELECT sg.*, 
               COUNT(sp.user_id) as member_count
        FROM sol_groups sg
        LEFT JOIN sol_participants sp ON sg.id = sp.sol_group_id
        WHERE sg.status = 'active' 
        AND sg.visibility = 'public'
        AND sg.id NOT IN (
            SELECT sol_group_id 
            FROM sol_participants 
            WHERE user_id = ?
        )
        GROUP BY sg.id
        LIMIT 10
    ");
    $availableGroupsStmt->execute([$userId]);
    $availableGroups = $availableGroupsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('SOL groups data error: ' . $e->getMessage());
    $error = 'An error occurred while loading SOL groups.';
}

// Handle group creation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_UNSAFE_RAW);
    $description = filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW);
    $visibility = filter_input(INPUT_POST, 'visibility', FILTER_UNSAFE_RAW);
    $contribution = filter_input(INPUT_POST, 'contribution', FILTER_VALIDATE_FLOAT);
    $frequency = filter_input(INPUT_POST, 'frequency', FILTER_UNSAFE_RAW);
    $totalCycles = filter_input(INPUT_POST, 'total_cycles', FILTER_VALIDATE_INT);
    
    // Sanitize inputs
    $name = htmlspecialchars(trim($name), ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars(trim($description), ENT_QUOTES, 'UTF-8');
    $visibility = htmlspecialchars(trim($visibility), ENT_QUOTES, 'UTF-8');
    $frequency = htmlspecialchars(trim($frequency), ENT_QUOTES, 'UTF-8');
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Please enter a group name.';
    }
    
    if (empty($description)) {
        $errors[] = 'Please enter a group description.';
    }
    
    if (!in_array($visibility, ['public', 'private'])) {
        $errors[] = 'Please select a valid visibility option.';
    }
    
    if (!$contribution || $contribution <= 0) {
        $errors[] = 'Please enter a valid contribution amount.';
    }
    
    if (!in_array($frequency, ['weekly', 'biweekly', 'monthly'])) {
        $errors[] = 'Please select a valid contribution frequency.';
    }
    
    if (!$totalCycles || $totalCycles <= 0) {
        $errors[] = 'Please enter a valid number of cycles.';
    }
    
    if (empty($errors)) {
        try {
            $db = getDbConnection();
            $db->beginTransaction();
            
            // Generate group ID
            $groupId = generateUuid();
            
            // Create group
            $createStmt = $db->prepare("
                INSERT INTO sol_groups 
                (id, name, description, visibility, contribution, frequency, total_cycles, current_cycle, 
                 status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'active', NOW(), NOW())
            ");
            $createStmt->execute([
                $groupId, $name, $description, $visibility, $contribution, $frequency, $totalCycles
            ]);
            
            // Add creator as admin
            $participantStmt = $db->prepare("
                INSERT INTO sol_participants 
                (sol_group_id, user_id, role, joined_at)
                VALUES (?, ?, 'admin', NOW())
            ");
            $participantStmt->execute([$groupId, $userId]);
            
            $db->commit();
            
            setFlashMessage('success', 'SOL group "' . $name . '" created successfully!');
            redirect('?page=sol-details&id=' . $groupId);
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('SOL group creation error: ' . $e->getMessage());
            setFlashMessage('error', 'An error occurred while creating the SOL group.');
        }
    } else {
        foreach ($errors as $error) {
            setFlashMessage('error', $error);
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-7">
        <h1 class="h3 fw-bold mb-2">SOL Groups</h1>
        <p class="text-muted">Manage and join SOL (Save Now, Opportunity Later) savings groups</p>
    </div>
    <div class="col-md-5 text-md-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
            <i class="fas fa-plus-circle me-1"></i> Create New Group
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- My Groups -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent pt-4 pb-3 border-0">
        <h4 class="fw-bold mb-0">
            <i class="fas fa-users text-primary me-2"></i> My SOL Groups
        </h4>
    </div>
    <div class="card-body p-4">
        <?php if (empty($myGroups)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-user-friends text-muted" style="font-size: 3rem;"></i>
                </div>
                <h5 class="fw-normal mb-3">You haven't joined any SOL groups yet</h5>
                <p class="text-muted mb-4">Join an existing group or create your own to start saving with others.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                    Create Your First Group
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Group Name</th>
                            <th>Contribution</th>
                            <th>Members</th>
                            <th>Cycle</th>
                            <th>Status</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myGroups as $group): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="group-icon rounded-circle bg-primary-subtle me-3 p-2 text-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-users text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($group['name']) ?></h6>
                                            <small class="text-muted"><?= ucfirst($group['frequency']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= number_format($group['contribution']) ?> HTG</td>
                                <td><?= $group['member_count'] ?></td>
                                <td><?= $group['current_cycle'] ?>/<?= $group['total_cycles'] ?></td>
                                <td>
                                    <?php if ($group['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($group['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($group['status'] === 'completed'): ?>
                                        <span class="badge bg-info">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($group['user_role'] === 'admin'): ?>
                                        <span class="badge bg-primary">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">Member</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=sol-details&id=<?= $group['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Available Groups -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent pt-4 pb-3 border-0">
        <h4 class="fw-bold mb-0">
            <i class="fas fa-search text-primary me-2"></i> Available SOL Groups
        </h4>
    </div>
    <div class="card-body p-4">
        <?php if (empty($availableGroups)): ?>
            <div class="text-center py-4">
                <p class="text-muted mb-0">No public SOL groups available to join at the moment.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($availableGroups as $group): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 border">
                            <div class="card-body">
                                <h5 class="fw-bold card-title"><?= htmlspecialchars($group['name']) ?></h5>
                                <p class="card-text small text-muted mb-3"><?= htmlspecialchars($group['description']) ?></p>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Contribution:</span>
                                    <span class="fw-medium"><?= number_format($group['contribution']) ?> HTG</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Frequency:</span>
                                    <span class="fw-medium"><?= ucfirst($group['frequency']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Members:</span>
                                    <span class="fw-medium"><?= $group['member_count'] ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted small">Cycles:</span>
                                    <span class="fw-medium"><?= $group['current_cycle'] ?>/<?= $group['total_cycles'] ?></span>
                                </div>
                                <div class="d-grid">
                                    <a href="?page=sol-details&id=<?= $group['id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-info-circle me-1"></i> Group Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New SOL Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="?page=sol-groups">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Group Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="visibility" class="form-label">Visibility</label>
                            <select class="form-select" id="visibility" name="visibility" required>
                                <option value="public">Public (Anyone can join)</option>
                                <option value="private">Private (By invitation only)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="contribution" class="form-label">Contribution Amount (HTG)</label>
                            <input type="number" class="form-control" id="contribution" name="contribution" min="100" step="100" required>
                        </div>
                        <div class="col-md-4">
                            <label for="frequency" class="form-label">Contribution Frequency</label>
                            <select class="form-select" id="frequency" name="frequency" required>
                                <option value="weekly">Weekly</option>
                                <option value="biweekly">Bi-weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="total_cycles" class="form-label">Number of Cycles</label>
                            <input type="number" class="form-control" id="total_cycles" name="total_cycles" min="3" max="52" value="10" required>
                            <div class="form-text">Equal to number of members</div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-info-circle fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="alert-heading mb-1">About SOL Groups</h6>
                                <p class="mb-0">SOL (Save Now, Opportunity Later) groups are community saving circles where members contribute regularly and take turns receiving the collected funds. Each cycle, one member receives the full contribution pool.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_group" class="btn btn-primary">Create Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s ease;
}

.card:hover {
    transform: translateY(-5px);
}

.table th {
    font-weight: 600;
    font-size: 0.825rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>