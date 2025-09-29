<?php 
// Set page title
$pageTitle = "Create an Account"; 
?>
<div class="register-bg position-fixed top-0 start-0 w-100 h-100" style="z-index:-1;"></div>
<div class="row justify-content-center align-items-center min-vh-100">
    <div class="col-md-7 col-lg-6">
        <div class="glass-card shadow-lg border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <img src="/public/images/sosol-logo.jpg" alt="SOSOL Logo" height="48" class="mb-3 rounded-circle shadow-sm">
                    <h3 class="fw-bold text-gradient mb-1 animate__animated animate__fadeInDown">Create an Account</h3>
                    <p class="text-muted">Join the <span class="fw-bold text-primary">SOSOL</span> community</p>
                </div>
                <form method="POST" action="actions/register.php" id="registerForm">
                    <!-- Full Name -->
                    <div class="mb-3">
                        <label for="fullName" class="form-label">Full Name</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user text-gradient"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 register-input" id="fullName" name="full_name" 
                                placeholder="Enter your full name" required autocomplete="name">
                        </div>
                    </div>
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-gradient"></i>
                            </span>
                            <input type="email" class="form-control border-start-0 register-input" id="email" name="email" 
                                placeholder="Enter your email" required autocomplete="email">
                        </div>
                    </div>
                    <!-- Phone Number -->
                    <div class="mb-3">
                        <label for="phoneNumber" class="form-label">Phone Number</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-phone text-gradient"></i>
                            </span>
                            <input type="tel" class="form-control border-start-0 register-input" id="phoneNumber" name="phone_number" 
                                placeholder="e.g. +509 37123456" required autocomplete="tel">
                        </div>
                    </div>
                    <div class="row">
                        <!-- Password -->
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-lock text-gradient"></i>
                                </span>
                                <input type="password" class="form-control border-start-0 register-input" id="password" name="password" 
                                    placeholder="Create a password" required autocomplete="new-password">
                            </div>
                            <div class="password-strength mt-1" id="passwordStrength"></div>
                        </div>
                        <!-- Confirm Password -->
                        <div class="col-md-6 mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-lock text-gradient"></i>
                                </span>
                                <input type="password" class="form-control border-start-0 register-input" id="confirmPassword" name="confirm_password" 
                                    placeholder="Confirm password" required autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                    <!-- Terms & Conditions -->
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="agree" name="agree" required>
                        <label class="form-check-label" for="agree">
                            I agree to the <a href="?page=terms" class="text-gradient text-decoration-none">Terms of Service</a> and 
                            <a href="?page=privacy" class="text-gradient text-decoration-none">Privacy Policy</a>
                        </label>
                    </div>
                    <!-- Submit button -->
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-gradient py-2 fw-bold shadow-sm">
                            <i class="fas fa-user-plus me-2"></i> Create Account
                        </button>
                    </div>
                    <!-- Separator -->
                    <div class="position-relative mb-4">
                        <hr>
                        <span class="position-absolute top-50 start-50 translate-middle px-3 bg-white text-muted small">or</span>
                    </div>
                    <!-- Google sign up button -->
                    <div class="d-grid mb-3">
                        <a href="actions/google-login.php" class="btn btn-light border py-2 d-flex align-items-center justify-content-center shadow-sm">
                            <img src="https://accounts.google.com/favicon.ico" alt="Google" width="18" height="18" class="me-2">
                            Sign up with Google
                        </a>
                    </div>
                    <!-- Login link -->
                    <div class="text-center">
                        <p class="text-muted mb-0">Already have an account? 
                            <a href="?page=login" class="text-gradient fw-bold text-decoration-none">Sign in</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<style>
.register-bg {
    background: linear-gradient(120deg, #007bff 60%, #6f42c1 100%);
    opacity: 0.12;
}
.glass-card {
    background: rgba(255,255,255,0.18);
    border-radius: 1.2rem;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.18);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.18);
}
.text-gradient {
    background: linear-gradient(90deg, #007bff 40%, #6f42c1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: #007bff;
}
.btn-gradient {
    background: linear-gradient(90deg, #007bff 40%, #6f42c1 100%);
    color: #fff;
    border: none;
    transition: background 0.2s, box-shadow 0.2s;
}
.btn-gradient:hover, .btn-gradient:focus {
    background: linear-gradient(90deg, #0056b3 40%, #4b2e83 100%);
    color: #fff;
    box-shadow: 0 4px 16px rgba(0,0,0,0.10);
}
.register-input:focus {
    border-color: #6f42c1;
    box-shadow: 0 0 0 0.15rem rgba(111,66,193,.15);
}
.password-strength {
    font-size: 0.95rem;
    font-weight: 500;
    letter-spacing: 0.5px;
}
@media (max-width: 991px) {
    .glass-card { margin-top: 2rem; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password strength checker
    const passwordInput = document.getElementById('password');
    const strengthIndicator = document.getElementById('passwordStrength');
    const confirmPassword = document.getElementById('confirmPassword');
    const form = document.getElementById('registerForm');
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
    form.addEventListener('submit', function(event) {
        // Check if passwords match
        if (passwordInput.value !== confirmPassword.value) {
            event.preventDefault();
            alert('Passwords do not match');
            confirmPassword.classList.add('is-invalid');
            return false;
        } else {
            confirmPassword.classList.remove('is-invalid');
        }
        // Check password strength (optional, already checked server-side)
        if (passwordInput.value.length < 8) {
            event.preventDefault();
            alert('Password must be at least 8 characters long');
            passwordInput.classList.add('is-invalid');
            return false;
        } else {
            passwordInput.classList.remove('is-invalid');
        }
        return true;
    });
});
</script>
