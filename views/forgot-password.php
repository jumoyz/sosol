<?php 
// Set page title
$pageTitle = "Forgot Password"; 
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <h3 class="fw-bold text-primary mb-1">Forgot Password</h3>
                    <p class="text-muted">Enter your email to reset your password</p>
                </div>
                
                <form method="POST" action="actions/forgot-password.php">
                    <!-- Email -->
                    <div class="mb-4">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email" class="form-control border-start-0" id="email" name="email" 
                                placeholder="Enter your email" required>
                        </div>
                    </div>
                    
                    <!-- Submit button -->
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary py-2">
                            <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                        </button>
                    </div>
                    
                    <!-- Back to login -->
                    <div class="text-center">
                        <p class="text-muted mb-0">
                            <a href="?page=login" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i> Back to Login
                            </a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>