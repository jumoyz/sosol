<?php 
// Set page title
$pageTitle = "Reset Password"; 

// Get token and email from URL
$token = isset($_GET['token']) ? $_GET['token'] : '';
$email = isset($_GET['email']) ? $_GET['email'] : '';

// Validate token and email existence
$validRequest = !empty($token) && !empty($email);
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <h3 class="fw-bold text-primary mb-1">Reset Your Password</h3>
                    <p class="text-muted">Create a new secure password</p>
                </div>
                
                <?php if ($validRequest): ?>
                <form method="POST" action="actions/reset-password.php">
                    <!-- Hidden fields -->
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                    
                    <!-- New Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" class="form-control border-start-0" id="password" name="password" 
                                placeholder="Create a new password" required minlength="8">
                        </div>
                        <div class="password-strength mt-1" id="passwordStrength"></div>
                    </div>
                    
                    <!-- Confirm New Password -->
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" 
                                placeholder="Confirm your new password" required minlength="8">
                        </div>
                    </div>
                    
                    <!-- Submit button -->
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary py-2">
                            <i class="fas fa-key me-2"></i> Reset Password
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-center">
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Invalid or expired password reset link.
                    </div>
                    <p>Please request a new password reset link.</p>
                    <a href="?page=forgot-password" class="btn btn-primary mt-3">
                        <i class="fas fa-redo me-2"></i> Request New Reset Link
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <p class="text-muted mb-0">
                        <a href="?page=login" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i> Back to Login
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password strength checker
    const passwordInput = document.getElementById('password');
    const strengthIndicator = document.getElementById('passwordStrength');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (passwordInput && strengthIndicator && confirmPassword) {
        passwordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 1;
            
            // Contains lowercase
            if (/[a-z]/.test(password)) strength += 1;
            
            // Contains uppercase
            if (/[A-Z]/.test(password)) strength += 1;
            
            // Contains number
            if (/[0-9]/.test(password)) strength += 1;
            
            // Contains special character
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update indicator
            strengthIndicator.className = 'password-strength mt-1';
            
            switch(strength) {
                case 0:
                case 1:
                    strengthIndicator.textContent = 'Weak password';
                    strengthIndicator.classList.add('text-danger');
                    break;
                case 2:
                case 3:
                    strengthIndicator.textContent = 'Medium password';
                    strengthIndicator.classList.add('text-warning');
                    break;
                case 4:
                case 5:
                    strengthIndicator.textContent = 'Strong password';
                    strengthIndicator.classList.add('text-success');
                    break;
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(event) {
            // Check if passwords match
            if (passwordInput.value !== confirmPassword.value) {
                event.preventDefault();
                alert('Passwords do not match');
                return false;
            }
            
            // Check password strength
            if (passwordInput.value.length < 8) {
                event.preventDefault();
                alert('Password must be at least 8 characters long');
                return false;
            }
            
            return true;
        });
    }
});
</script>