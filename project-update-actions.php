<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $pdo = $database->getConnection();

    $action = $_GET['action'] ?? 'add';
    $project_id = intval($_POST['project_id']);
    
    // Vérifier les droits (auteur ou admin)
    $stmt = $pdo->prepare("SELECT user_id, title FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();

    if (!$project || ($_SESSION['user_id'] != $project['user_id'] && !isAdmin())) {
        $_SESSION['error_message'] = "Action non autorisée.";
        redirect("project-detail.php?id=$project_id");
    }

    if ($action === 'add') {
        $title = sanitizeInput($_POST['title']);
        $content = sanitizeInput($_POST['content']);

    $image_url = null;
    if (isset($_FILES['update_image']) && $_FILES['update_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadImage($_FILES['update_image'], 'update-' . $project_id);
        if ($uploadResult['success']) {
            $image_url = $uploadResult['filepath'];
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO project_updates (project_id, title, content, image_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$project_id, $title, $content, $image_url]);

        // Notifier les donateurs du projet qui sont des utilisateurs enregistrés
        $notifMsg = "Nouvelle actualité pour le projet : " . $project['title'];
        $link = "project-detail.php?id=" . $project_id;
        
        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, message, link) 
            SELECT DISTINCT user_id, :msg, :link 
            FROM donations 
            WHERE project_id = :pid AND user_id IS NOT NULL AND payment_status = 'completed'
        ");
        $stmtNotif->execute([':msg' => $notifMsg, ':link' => $link, ':pid' => $project_id]);

        $_SESSION['success_message'] = "Actualité publiée avec succès et donateurs notifiés !";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur technique lors de la publication.";
    }
    } elseif ($action === 'edit') {
        $update_id = intval($_POST['update_id']);
        $title = sanitizeInput($_POST['title']);
        $content = sanitizeInput($_POST['content']);

        $image_url = null;
        if (isset($_FILES['update_image']) && $_FILES['update_image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImage($_FILES['update_image'], 'update-' . $project_id);
            if ($uploadResult['success']) {
                $image_url = $uploadResult['filepath'];
            }
        }

        try {
            if ($image_url) {
                $stmt = $pdo->prepare("UPDATE project_updates SET title = ?, content = ?, image_url = ? WHERE id = ? AND project_id = ?");
                $stmt->execute([$title, $content, $image_url, $update_id, $project_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE project_updates SET title = ?, content = ? WHERE id = ? AND project_id = ?");
                $stmt->execute([$title, $content, $update_id, $project_id]);
            }

            $_SESSION['success_message'] = "Actualité mise à jour avec succès !";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erreur technique lors de la modification.";
        }
    }

    redirect("project-detail.php?id=$project_id");
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $database = new Database();
    $pdo = $database->getConnection();
    $id = intval($_GET['id']);
    $project_id = intval($_GET['project_id']);

    $stmt = $pdo->prepare("SELECT user_id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();

    if ($project && ($_SESSION['user_id'] == $project['user_id'] || isAdmin())) {
        $stmt = $pdo->prepare("DELETE FROM project_updates WHERE id = ? AND project_id = ?");
        $stmt->execute([$id, $project_id]);
        $_SESSION['success_message'] = "Actualité supprimée.";
    } else {
        $_SESSION['error_message'] = "Action non autorisée.";
    }

    redirect("project-detail.php?id=$project_id");
}