<?php
/**
 * Create Campaign Action Handler
 * 
 * Processes user create campaign requests
 */
require_once "../includes/config.php";
require_once "../includes/auth.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $creator_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $goal_amount = floatval($_POST['goal_amount']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // Image upload
    $image_url = null;
    if (!empty($_FILES['image']['name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . "." . $ext;
        $target = "../public/uploads/campaigns/" . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $image_url = "/public/uploads/campaigns/" . $filename;
        }
    }

    $id = uniqid('', true);

    $stmt = $pdo->prepare("INSERT INTO campaigns (id, creator_id, title, description, category, goal_amount, image_url, status, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
    $result = $stmt->execute([$id, $creator_id, $title, $description, $category, $goal_amount, $image_url, $start_date, $end_date]);

    if ($result) {
        $_SESSION['flash_success'] = "Campaign created successfully.";
        header("Location: ../views/my-campaigns.php");
        exit;
    } else {
        $_SESSION['flash_error'] = "Error creating campaign.";
        header("Location: ../views/create-campaign.php");
        exit;
    }
}
?>
