<?php
// Investment Create Page
$pageTitle = 'Create Investment Opportunity';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/flash-messages.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { redirect('?page=login'); }

$errors = [];
$success = false;

// Default form values
$data = [
    'title' => '',
    'sector' => '',
    'funding_goal' => '',
    'equity_offered' => '',
    'pitch_deck' => '',
    'video_url' => '',
    'description' => '',
    'end_date' => '',
    'status' => 'open',
    'visibility' => 'public'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect & sanitize
    foreach ($data as $k => $v) {
        if (isset($_POST[$k])) {
            $data[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k];
        }
    }

    // Validation
    if ($data['title'] === '' || mb_strlen($data['title']) < 3) {
        $errors[] = 'Title is required (min 3 chars).';
    }
    if ($data['sector'] === '') { $errors[] = 'Sector is required.'; }

    $goal = (float)$data['funding_goal'];
    if ($goal <= 0) { $errors[] = 'Funding goal must be greater than 0.'; }

    if ($data['equity_offered'] !== '' && !is_numeric($data['equity_offered'])) {
        $errors[] = 'Equity offered must be numeric or blank.';
    }
    $equity = $data['equity_offered'] === '' ? null : (float)$data['equity_offered'];
    if ($equity !== null && ($equity < 0 || $equity > 100)) {
        $errors[] = 'Equity offered must be between 0 and 100.';
    }

    if ($data['end_date'] !== '') {
        $today = new DateTime('today');
        try {
            $end = new DateTime($data['end_date']);
            if ($end < $today) { $errors[] = 'End date cannot be in the past.'; }
        } catch (Exception $e) {
            $errors[] = 'Invalid end date provided.';
        }
    }

    if (!in_array($data['status'], ['draft','open'], true)) { $errors[] = 'Invalid status.'; }
    if (!in_array($data['visibility'], ['public','private'], true)) { $errors[] = 'Invalid visibility.'; }

    // Basic URL validation (optional fields)
    foreach (['pitch_deck','video_url'] as $urlField) {
        if ($data[$urlField] !== '' && !filter_var($data[$urlField], FILTER_VALIDATE_URL)) {
            $errors[] = ucfirst(str_replace('_',' ', $urlField)) . ' must be a valid URL or left blank.';
        }
    }

    if (empty($errors)) {
        try {
            $db = getDbConnection();
            $id = generateUuid();
            $stmt = $db->prepare("INSERT INTO investments (id,user_id,title,sector,funding_goal,amount_raised,equity_offered,pitch_deck,video_url,description,end_date,status,visibility,archived,created_at,updated_at) VALUES (?,?,?,?,?,0.00,?,?,?,?,?,?,?,0,NOW(),NOW())");
            $stmt->execute([
                $id,
                $userId,
                $data['title'],
                $data['sector'],
                $goal,
                $equity,
                $data['pitch_deck'] ?: null,
                $data['video_url'] ?: null,
                $data['description'] ?: null,
                $data['end_date'] ?: null,
                $data['status'],
                $data['visibility']
            ]);

            setFlashMessage('success', 'Investment opportunity created.');
            $success = true;
            redirect('?page=investment-details&id=' . urlencode($id));
        } catch (PDOException $e) {
            error_log('Investment create error: ' . $e->getMessage());
            $errors[] = 'Database error creating investment.';
        }
    }
}
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0"><i class="fas fa-lightbulb me-2 text-warning"></i>Create Investment</h2>
    <a href="?page=investments" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0">
      <?php foreach ($errors as $er): ?><li><?= htmlspecialchars($er) ?></li><?php endforeach; ?>
    </ul></div>
  <?php endif; ?>

  <form method="POST" class="card shadow-sm border-0">
    <div class="card-body p-4">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Title *</label>
          <input type="text" name="title" value="<?= htmlspecialchars($data['title']) ?>" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Sector *</label>
          <input type="text" name="sector" value="<?= htmlspecialchars($data['sector']) ?>" class="form-control" placeholder="e.g. fintech" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Funding Goal (HTG)*</label>
          <input type="number" step="0.01" name="funding_goal" value="<?= htmlspecialchars($data['funding_goal']) ?>" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Equity Offered (%)</label>
          <input type="number" step="0.01" name="equity_offered" value="<?= htmlspecialchars($data['equity_offered']) ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">Pitch Deck URL</label>
          <input type="url" name="pitch_deck" value="<?= htmlspecialchars($data['pitch_deck']) ?>" class="form-control" placeholder="https://...">
        </div>
        <div class="col-md-3">
          <label class="form-label">Video URL</label>
          <input type="url" name="video_url" value="<?= htmlspecialchars($data['video_url']) ?>" class="form-control" placeholder="https://...">
        </div>
        <div class="col-md-3">
          <label class="form-label">End Date</label>
          <input type="date" name="end_date" value="<?= htmlspecialchars($data['end_date']) ?>" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="open" <?= $data['status']==='open'?'selected':'' ?>>Open</option>
            <option value="draft" <?= $data['status']==='draft'?'selected':'' ?>>Draft</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Visibility</label>
          <select name="visibility" class="form-select">
            <option value="public" <?= $data['visibility']==='public'?'selected':'' ?>>Public</option>
            <option value="private" <?= $data['visibility']==='private'?'selected':'' ?>>Private</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea name="description" rows="5" class="form-control" placeholder="Describe the business, model, traction..."><?= htmlspecialchars($data['description']) ?></textarea>
        </div>
      </div>
      <hr class="my-4">
      <div class="d-flex justify-content-end gap-2">
        <a href="?page=investments" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Create</button>
      </div>
    </div>
  </form>
  <!-- TODO: Future enhancement - allow file upload for pitch deck and secure storage -->
</div>
