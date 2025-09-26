<?php 
// Set page title
$pageTitle = "Reset Password Request"; 
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5 text-center">
                <i class="fas fa-envelope-open-text text-primary mb-4" style="font-size: 3rem;"></i>
                
                <h3 class="fw-bold text-primary mb-3">Check Your Email</h3>
                <p class="text-muted mb-4">
                    If an account exists with the email you provided, we've sent password reset instructions.
                    Please check your inbox and spam folder.
                </p>
                
                <?php if (isset($_SESSION['reset_link'])): ?>
                    <div class="alert alert-info mb-4">
                        <p><strong>DEVELOPMENT MODE:</strong> Password reset link:</p>
                        <p class="mb-2">Click the link below to reset your password:</p>
                        <a href="<?php echo $_SESSION['reset_link']; ?>" class="btn btn-sm btn-info">
                            Reset Password Link
                        </a>
                        <p class="mt-2 mb-0 small text-muted">
                            (This is displayed only in development environment)
                        </p>
                    </div>
                    <?php unset($_SESSION['reset_link']); ?>
                <?php endif; ?>
                
                <div class="d-grid gap-3">
                    <a href="?page=login" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i> Return to Login
                    </a>
                    <a href="?page=forgot-password" class="text-decoration-none">
                        Didn't receive the email? Try again
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>