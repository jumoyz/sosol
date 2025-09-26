<?php 
// flash-messages.php - Handles display of flash messages (Bootstrap version)

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to set a flash message if not already set in functions.php   
function setFlashMessage($type, $text) {
    if (!isset($_SESSION['messages'])) {
        $_SESSION['messages'] = [];
    }
    $_SESSION['messages'][$type][] = $text;
}

// Check for messages in session
$messages = [];
if (!empty($_SESSION['messages'])) {
    $messages = $_SESSION['messages'];
    unset($_SESSION['messages']);
}

// Also handle single message vars (for backward compatibility)
foreach (['error', 'success', 'warning', 'danger', 'info'] as $type) {
    if (!empty($_SESSION[$type])) {
        $messages[$type][] = $_SESSION[$type];
        unset($_SESSION[$type]);
    }
}

// Only output if there are messages
if (!empty($messages)): 
?>
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080;">
    <?php foreach ($messages as $type => $typeMessages): ?>
        <?php foreach ($typeMessages as $message): ?>
            <?php
            // Map custom type to Bootstrap alert classes
            switch ($type) {
                case 'success': $class = 'alert-success'; break;
                case 'error': 
                case 'danger': $class = 'alert-danger'; break;
                case 'warning': $class = 'alert-warning'; break;
                default: $class = 'alert-info'; break;
            }
            ?>
            <div class="alert <?= $class ?> alert-dismissible fade show shadow" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<script>
// Auto-dismiss after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            let bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
<?php endif; ?>
