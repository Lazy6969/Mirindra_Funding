<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();

$action = $_GET['action'] ?? '';
$notif_id = intval($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($action === 'read' && $notif_id > 0) {
    $stmt = $pdo->prepare("SELECT link FROM notifications WHERE id = :id AND user_id = :u_id");
    $stmt->execute([':id' => $notif_id, ':u_id' => $user_id]);
    $notif = $stmt->fetch();

    if ($notif) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
        $stmt->execute([':id' => $notif_id]);
        header("Location: " . $notif['link']);
        exit();
    }
}

if ($action === 'delete' && $notif_id > 0) {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :u_id");
    $stmt->execute([':id' => $notif_id, ':u_id' => $user_id]);
    $_SESSION['success_message'] = "Notification supprimée.";
    $redirectPage = $_GET['redirect'] ?? 'notifications.php';
    header("Location: " . $redirectPage);
    exit();
}

if ($action === 'delete_all') {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = :u_id");
    $stmt->execute([':u_id' => $user_id]);
    $_SESSION['success_message'] = "Toutes les notifications ont été supprimées.";
    $redirectPage = $_GET['redirect'] ?? 'notifications.php';
    header("Location: " . $redirectPage);
    exit();
}

if ($action === 'mark_all_read') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u_id");
    $stmt->execute([':u_id' => $user_id]);
    $_SESSION['success_message'] = "Toutes les notifications ont été marquées comme lues.";
    $redirectPage = $_GET['redirect'] ?? 'dashboard.php';
    header("Location: " . $redirectPage);
    exit();
}

header("Location: dashboard.php");
exit();
?>