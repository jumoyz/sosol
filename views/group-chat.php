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
$pageTitle = "Group Chat";

// Get current user data
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    redirect('?page=login');
}

// Retrieve validated group ID
$groupId = requireValidGroupId('?page=dashboard');

try {
    $pdo = getDbConnection();
    
    // Get group details
    $groupStmt = $pdo->prepare("
        SELECT sg.*, u.full_name as admin_name
        FROM sol_groups sg
        INNER JOIN users u ON sg.admin_id = u.id
        WHERE sg.id = ?
    ");
    $groupStmt->execute([$groupId]);
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        setFlashMessage('error', 'SOL group not found.');
        redirect('?page=dashboard');
    }
    
    // Check if user is a member
    $memberStmt = $pdo->prepare("
        SELECT sp.*, u.full_name, u.profile_photo 
        FROM sol_participants sp
        INNER JOIN users u ON sp.user_id = u.id
        WHERE sp.sol_group_id = ? AND sp.user_id = ?
    ");
    $memberStmt->execute([$groupId, $userId]);
    $userMember = $memberStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userMember) {
        setFlashMessage('error', 'You must be a member of this group to access the chat.');
        redirect('?page=sol-details&id=' . $groupId);
    }
    
    // Handle message submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
        $message = trim($_POST['message'] ?? '');
        
        if (empty($message)) {
            setFlashMessage('error', 'Message cannot be empty.');
        } elseif (strlen($message) > 1000) {
            setFlashMessage('error', 'Message is too long. Maximum 1000 characters allowed.');
        } else {
            try {
                $messageId = generateUuid();
                $insertMessageStmt = $pdo->prepare("
                    INSERT INTO group_messages (id, sol_group_id, user_id, message, message_type, created_at)
                    VALUES (?, ?, ?, ?, 'text', NOW())
                ");
                
                if ($insertMessageStmt->execute([$messageId, $groupId, $userId, $message])) {
                    setFlashMessage('success', 'Message sent successfully!');
                } else {
                    setFlashMessage('error', 'Failed to send message. Please try again.');
                }
            } catch (PDOException $e) {
                error_log('Group message error: ' . $e->getMessage());
                setFlashMessage('error', 'An error occurred while sending the message.');
            }
        }
        
        redirect('?page=group-chat&id=' . $groupId);
    }
    
    // Mark messages as read for current user
    $markReadStmt = $pdo->prepare("
        UPDATE group_messages 
        SET is_read = TRUE 
        WHERE sol_group_id = ? AND user_id != ? AND is_read = FALSE
    ");
    $markReadStmt->execute([$groupId, $userId]);
    
    // Fetch all messages
    $messagesStmt = $pdo->prepare("
        SELECT gm.*,
               u.full_name,
               u.profile_photo
        FROM group_messages gm
        INNER JOIN users u ON gm.user_id = u.id
        WHERE gm.sol_group_id = ?
        ORDER BY gm.created_at ASC
    ");
    $messagesStmt->execute([$groupId]);
    $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Group chat error: ' . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading the group chat.');
    redirect('?page=dashboard');
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
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-1">
                                <i class="fas fa-comments text-primary me-2"></i>
                                <?= htmlspecialchars($group['name']) ?> - Group Chat
                            </h4>
                            <p class="text-muted mb-0">
                                <?= count($messages) ?> messages • Group created by <?= htmlspecialchars($group['admin_name']) ?>
                            </p>
                        </div>
                        <a href="?page=sol-details&id=<?= $groupId ?>" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Group
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Flash Messages -->
            <div id="alertPlaceholder"></div>
            
            <!-- Chat Container -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    
                    <!-- Messages Area -->
                    <div id="messagesContainer" class="messages-container p-4">
                        <?php if (!empty($messages)): ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="message-bubble <?= $message['user_id'] === $userId ? 'own-message' : 'other-message' ?> mb-3">
                                    <div class="d-flex align-items-start <?= $message['user_id'] === $userId ? 'flex-row-reverse' : '' ?>">
                                        <div class="message-avatar <?= $message['user_id'] === $userId ? 'ms-2' : 'me-2' ?>">
                                            <?php if (!empty($message['profile_photo'])): ?>
                                                <img src="<?= htmlspecialchars($message['profile_photo']) ?>" 
                                                     alt="<?= htmlspecialchars($message['full_name']) ?>" 
                                                     class="rounded-circle" 
                                                     width="40" height="40">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px; font-size: 16px;">
                                                    <?= strtoupper(substr($message['full_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-content">
                                            <div class="message-bubble-content <?= $message['user_id'] === $userId ? 'bg-primary text-white' : 'bg-light' ?> p-3 rounded-3">
                                                <?php if ($message['message_type'] === 'system'): ?>
                                                    <div class="message-text fst-italic <?= $message['user_id'] === $userId ? 'text-light' : 'text-info' ?>">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        <?= htmlspecialchars($message['message']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="message-text">
                                                        <?= nl2br(htmlspecialchars($message['message'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="message-meta mt-1 <?= $message['user_id'] === $userId ? 'text-end' : '' ?>">
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($message['full_name']) ?> • 
                                                    <?= date('M j, Y \a\t g:i A', strtotime($message['created_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comments fa-3x mb-3"></i>
                                <h5>No messages yet</h5>
                                <p>Be the first to start a conversation in this group!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Message Input -->
                    <div class="border-top p-4 bg-light">
                        <form method="POST" action="" id="messageForm">
                            <div class="input-group">
                                <textarea class="form-control" 
                                          name="message" 
                                          id="messageInput"
                                          placeholder="Type your message..." 
                                          rows="1"
                                          maxlength="1000"
                                          required
                                          style="resize: none;"></textarea>
                                <button type="submit" 
                                        name="send_message" 
                                        class="btn btn-primary px-4">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                            <small class="text-muted">Press Enter to send • Maximum 1000 characters</small>
                        </form>
                    </div>
                    
                </div>
            </div>
            
        </div>
    </div>
</div>

<style>
    .messages-container {
        max-height: 600px;
        overflow-y: auto;
        scroll-behavior: smooth;
    }
    
    .message-bubble {
        max-width: 100%;
    }
    
    .message-bubble.own-message {
        margin-left: auto;
    }
    
    .message-bubble.other-message {
        margin-right: auto;
    }
    
    .message-content {
        max-width: 70%;
    }
    
    .message-bubble-content {
        word-wrap: break-word;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .message-text {
        font-size: 14px;
        line-height: 1.4;
    }
    
    .message-meta {
        font-size: 12px;
    }
    
    .message-avatar {
        flex-shrink: 0;
    }
    
    #messageInput {
        border-radius: 25px;
        border: 1px solid #ddd;
        padding: 12px 20px;
    }
    
    #messageInput:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }
    
    .btn[type="submit"] {
        border-radius: 25px;
        min-width: 60px;
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
        const messageInput = document.getElementById('messageInput');
        const messageForm = document.getElementById('messageForm');
        const messagesContainer = document.getElementById('messagesContainer');
        
        // Handle flash messages if they exist
        <?php if (isset($_SESSION['flash_type']) && isset($_SESSION['flash_message'])): ?>
            showAlert('<?= $_SESSION['flash_type'] ?>', '<?= $_SESSION['flash_message'] ?>');
            <?php 
            unset($_SESSION['flash_type']);
            unset($_SESSION['flash_message']);
            ?>
        <?php endif; ?>
        
        // Auto-scroll to bottom of messages
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        // Auto-expand textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            
            // Limit height
            if (this.scrollHeight > 120) {
                this.style.height = '120px';
                this.style.overflowY = 'auto';
            } else {
                this.style.overflowY = 'hidden';
            }
        });
        
        // Handle Enter key for sending messages
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() !== '') {
                    messageForm.submit();
                }
            }
        });
        
        // Character counter
        let charCounter;
        messageInput.addEventListener('input', function() {
            if (!charCounter) {
                charCounter = document.createElement('small');
                charCounter.className = 'text-muted position-absolute';
                charCounter.style.right = '10px';
                charCounter.style.bottom = '10px';
                this.parentElement.style.position = 'relative';
                this.parentElement.appendChild(charCounter);
            }
            
            const remaining = 1000 - this.value.length;
            charCounter.textContent = remaining + ' characters left';
            
            if (remaining < 50) {
                charCounter.className = 'text-warning position-absolute';
            } else if (remaining < 0) {
                charCounter.className = 'text-danger position-absolute';
            } else {
                charCounter.className = 'text-muted position-absolute';
            }
        });
        
        // Focus on message input
        messageInput.focus();
    });
</script>

<?php include_once __DIR__ .'/../includes/footer.php'; ?>
