<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

requireLogin();
if (!isAdmin()) {
    redirect('dashboard.php');
}

$database = new Database();
$pdo = $database->getConnection();

$action = $_GET['action'] ?? '';
$id = intval($_GET['id'] ?? 0);

try {
    if ($action === 'approve_project' && $id > 0) {
        // On n'approuve que si le projet est en attente
        $stmt = $pdo->prepare("UPDATE projects SET status = 'active' WHERE id = ? AND status = 'pending'");
        if ($stmt->execute([$id])) {
            // Récupérer les informations du projet pour les notifications
            $stmt = $pdo->prepare("SELECT title, user_id FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $project = $stmt->fetch();

            if ($project) {
                // 1. Notification au propriétaire : Son projet est approuvé
                addNotification($pdo, $project['user_id'], "Félicitations ! Votre projet '" . $project['title'] . "' a été approuvé par l'administrateur.", "project-detail.php?id=$id");

                // 2. Notification à TOUS les utilisateurs actifs : Nouveau projet disponible
                // On utilise une insertion groupée pour éviter une boucle PHP lourde
                $msg = "Un nouveau projet vient d'être publié : " . $project['title'];
                $link = "project-detail.php?id=" . $id;
                
                $stmtNotif = $pdo->prepare("
                    INSERT INTO notifications (user_id, message, link) 
                    SELECT id, ?, ? FROM users WHERE id != ? AND is_active = 1
                ");
                $stmtNotif->execute([$msg, $link, $project['user_id']]);
            }
            $_SESSION['success_message'] = "Projet approuvé et communauté notifiée.";
        }
    } elseif ($action === 'refuse_project' && $id > 0) {
        $stmt = $pdo->prepare("SELECT title, user_id FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        if ($project) {
            // On passe le statut en 'cancelled' pour signifier le refus
            $stmt = $pdo->prepare("UPDATE projects SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);
            
            // Notification au propriétaire : Son projet est refusé
            addNotification($pdo, $project['user_id'], "Votre projet '" . $project['title'] . "' a été refusé par l'administration.", "dashboard.php");
            $_SESSION['success_message'] = "Le projet a été refusé et l'auteur a été notifié.";
        }
    } elseif ($action === 'delete_project' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Projet supprimé définitivement.";
    } elseif ($action === 'toggle_user' && $id > 0) {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Statut de l'utilisateur mis à jour.";
    } elseif ($action === 'delete_user' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Utilisateur supprimé.";
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur : " . $e->getMessage();
}

redirect('dashboard.php');
exit();
?>