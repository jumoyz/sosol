<?php
// /views/403.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default message if no custom error set
$errorMessage = $_SESSION['error'] ?? "You do not have permission to access this page.";
unset($_SESSION['error']); // Clear error after displaying
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>403 Forbidden</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            max-width: 480px;
            width: 100%;
            border-radius: 1rem;
            box-shadow: 0 6px 18px rgba(0,0,0,.1);
        }
        .error-code {
            font-size: 4rem;
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
<div class="error-container">
    <div class="card text-center p-4">
        <div class="card-body">
            <div class="error-code">403</div>
            <h4 class="card-title mb-3">Access Denied</h4>
            <p class="card-text text-muted"><?= htmlspecialchars($errorMessage) ?></p>
            <a href="/" class="btn btn-primary mt-3">Go Home</a>
            <a href="/login" class="btn btn-outline-secondary mt-3">Login</a>
        </div>
    </div>
</div>
</body>
</html>
