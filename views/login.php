<?php 
/**
 * Login page
 */
$pageTitle = "Login"; 
?>
<div class="login-bg position-fixed top-0 start-0 w-100 h-100" style="z-index:-1;"></div>
<div class="row justify-content-center align-items-center min-vh-100">
    <div class="col-md-6 col-lg-5">
        <div class="glass-card shadow-lg border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <img src="/public/images/sosol-logo.jpg" alt="SOSOL Logo" height="48" class="mb-3 rounded-circle shadow-sm">
                    <h3 class="fw-bold text-gradient mb-1 animate__animated animate__fadeInDown">Welcome Back</h3>
                    <p class="text-muted">Sign in to continue to <span class="fw-bold text-primary"><?= APP_NAME ?></span></p>
                </div>
                <form method="POST" action="actions/login.php">
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-gradient"></i>
                            </span>
                            <input type="email" class="form-control border-start-0 login-input" id="email" name="email" 
                                placeholder="Enter your email" required autocomplete="username">
                        </div>
                    </div>
                    <!-- Password -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <label for="password" class="form-label">Password</label>
                            <a href="?page=forgot-password" class="small text-decoration-none">Forgot password?</a>
                        </div>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-gradient"></i>
                            </span>
                            <input type="password" class="form-control border-start-0 login-input" id="password" name="password" 
                                placeholder="Enter your password" required autocomplete="current-password">
                        </div>
                    </div>
                    <!-- Remember me -->
                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <!-- Submit button -->
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-gradient py-2 fw-bold shadow-sm">
                            <i class="fas fa-sign-in-alt me-2"></i> Sign In
                        </button>
                    </div>
                    <!-- Separator -->
                    <div class="position-relative mb-4">
                        <hr>
                        <span class="position-absolute top-50 start-50 translate-middle px-3 bg-white text-muted small">or</span>
                    </div>
                    <!-- Google login button -->
                    <div class="d-grid mb-3">
                        <a href="actions/google-login.php" class="btn btn-light border py-2 d-flex align-items-center justify-content-center shadow-sm">
                            <img src="https://accounts.google.com/favicon.ico" alt="Google" width="18" height="18" class="me-2">
                            Sign in with Google
                        </a>
                    </div>
                    <!-- Register link -->
                    <div class="text-center">
                        <p class="text-muted mb-0">Don't have an account? 
                            <a href="?page=register" class="text-gradient fw-bold text-decoration-none">Sign up</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<style>
.login-bg {
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
.login-input:focus {
    border-color: #6f42c1;
    box-shadow: 0 0 0 0.15rem rgba(111,66,193,.15);
}
@media (max-width: 991px) {
    .glass-card { margin-top: 2rem; }
}
</style>