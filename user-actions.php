<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$action = $_GET['action'] ?? '';

if ($action === 'send_invitation') {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("INSERT INTO user_connections (sender_id, receiver_id) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $id]);
    addNotification($pdo, $id, $_SESSION['user_name'] . " vous a envoyé une invitation.", "user-profile.php?id=" . $_SESSION['user_id']);
    $_SESSION['success_message'] = "Invitation envoyée !";
    header("Location: user-profile.php?id=$id");

} elseif ($action === 'accept_invitation') {
    $conn_id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE user_connections SET status = 'accepted' WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$conn_id, $_SESSION['user_id']]);
    
    // Notifier l'expéditeur
    $stmt = $pdo->prepare("SELECT sender_id FROM user_connections WHERE id = ?");
    $stmt->execute([$conn_id]);
    $senderId = $stmt->fetchColumn();
    addNotification($pdo, $senderId, $_SESSION['user_name'] . " a accepté votre invitation.", "user-profile.php?id=" . $_SESSION['user_id']);
    
    header("Location: dashboard.php");

} elseif ($action === 'refuse_invitation') {
    $conn_id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE user_connections SET status = 'rejected' WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$conn_id, $_SESSION['user_id']]);
    header("Location: dashboard.php");

} elseif ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = intval($_POST['receiver_id']);
    $content = sanitizeInput($_POST['content']);
    $image_url = null;
    
    // Gérer l'upload d'image si présente
    if (isset($_FILES['message_image']) && $_FILES['message_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadMessageImage($_FILES['message_image']);
        if ($uploadResult['success']) {
            $image_url = $uploadResult['filepath'];
        } else {
            $_SESSION['error_message'] = $uploadResult['message'];
            header("Location: messages.php?with=$receiver_id");
            exit();
        }
    }
    
    // Un message doit avoir du contenu ou une image
    if (empty($content) && empty($image_url)) {
        $_SESSION['error_message'] = "Impossible d'envoyer un message vide.";
        header("Location: messages.php?with=$receiver_id");
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, image_url) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $receiver_id, $content, $image_url]);
    
    header("Location: messages.php?with=$receiver_id");
}
exit();