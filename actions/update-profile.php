<?php
require_once "../includes/config.php";
require_once "../includes/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    $result = $stmt->execute([$name, $email, $phone, $user_id]);

    if ($result) {
        $_SESSION['flash_success'] = "Profile updated successfully.";
        header("Location: ../views/profile.php");
        exit;
    } else {
        $_SESSION['flash_error'] = "Error updating profile.";
        header("Location: ../views/settings.php");
        exit;
    }
}
?>
