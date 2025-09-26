<?php
// Set page title
$pageTitle = "Page Not Found";

// Remove any previous flash messages to keep the UI clean
if (isset($_SESSION['flash_messages'])) {
    unset($_SESSION['flash_messages']);
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 text-center">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5">
                    <div class="mb-4">
                        <i class="fas fa-map-signs text-primary" style="font-size: 5rem;"></i>
                    </div>
                    
                    <h2 class="fw-bold text-dark mb-3">Oops! Page Not Found</h2>
                    <p class="text-muted mb-4">
                        The page you're looking for doesn't exist or has been moved.
                        Let's get you back on track!
                    </p>
                    
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-8">
                            <div class="list-group">
                                <a href="?page=dashboard" class="list-group-item list-group-item-action">
                                    <i class="fas fa-home me-2"></i> Return to Dashboard
                                </a>
                                <a href="?page=wallet" class="list-group-item list-group-item-action">
                                    <i class="fas fa-wallet me-2"></i> Go to My Wallet
                                </a>
                                <a href="?page=sol-groups" class="list-group-item list-group-item-action">
                                    <i class="fas fa-users me-2"></i> View SOL Groups
                                </a>
                                <a href="?page=loan-center" class="list-group-item list-group-item-action">
                                    <i class="fas fa-hand-holding-usd me-2"></i> Loan Center
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <button onclick="goBack()" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i> Go Back
                        </button>
                        <a href="?page=home" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i> Home Page
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 text-muted">
                <p class="mb-0 small">
                    If you believe this is an error, please contact 
                    <a href="?page=contact" class="text-decoration-none">customer support</a>.
                </p>
                <p class="small">
                    Error Code: 404
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function goBack() {
    window.history.back();
}
</script>

<style>
.list-group-item {
    border-radius: 0;
    transition: all 0.2s ease;
    border-left: 0;
    border-right: 0;
}

.list-group-item:first-child {
    border-top-left-radius: 0.25rem;
    border-top-right-radius: 0.25rem;
    border-top: 1px solid rgba(0,0,0,.125);
}

.list-group-item:last-child {
    border-bottom-left-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}

.list-group-item:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.05);
    transform: translateY(-1px);
}

.card {
    border-radius: 1rem;
}

.fas.fa-map-signs {
    opacity: 0.8;
}
</style>