<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set page title
$pageTitle = "SOL Groups";

// Include admin header (which now includes sidebar)
require_once 'header.php';




// Your page content here

// Include admin footer
require_once 'footer.php';
?>