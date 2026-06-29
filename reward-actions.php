<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();

$action = $_GET['action'] ?? '';
$projectId = intval($_REQUEST['project_id'] ?? 0);

// Vérifier si l'utilisateur est le propriétaire ou admin
$stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project || ($project['user_id'] != $_SESSION['user_id'] && !isAdmin())) {
    $_SESSION['error_message'] = "Action non autorisée.";
    redirect("project-detail.php?id=$projectId");
}

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['min_amount']);
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);

    if ($amount > 0 && !empty($title)) {
        $stmt = $pdo->prepare("INSERT INTO project_rewards (project_id, min_amount, title, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$projectId, $amount, $title, $description]);
        $_SESSION['success_message'] = "Récompense ajoutée !";
    } else {
        $_SESSION['error_message'] = "Veuillez remplir correctement tous les champs.";
    }
}

if ($action === 'delete') {
    $rewardId = intval($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM project_rewards WHERE id = ? AND project_id = ?");
    $stmt->execute([$rewardId, $projectId]);
    $_SESSION['success_message'] = "Récompense supprimée.";
}

redirect("project-detail.php?id=$projectId");