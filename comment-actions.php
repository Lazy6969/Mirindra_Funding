<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $project_id = intval($_POST['project_id']);
        $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        $content = sanitizeInput($_POST['content']);

        if (!empty($content) && $project_id > 0) {
            $stmt = $pdo->prepare("INSERT INTO project_comments (project_id, user_id, parent_id, content) VALUES (:p_id, :u_id, :pa_id, :cont)");
            $stmt->execute([':p_id' => $project_id, ':u_id' => $user_id, ':pa_id' => $parent_id, ':cont' => $content]);
            $newCommentId = $pdo->lastInsertId();

            // Notification pour le propriétaire du projet
            $stmt = $pdo->prepare("SELECT user_id, title FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            $project = $stmt->fetch();
            if ($project && $project['user_id'] != $user_id) {
                addNotification($pdo, $project['user_id'], "Nouveau commentaire sur votre projet : " . $project['title'], "project-detail.php?id=$project_id#comment-$newCommentId");
            }

            // Notification pour l'auteur du commentaire parent (si c'est une réponse)
            if ($parent_id) {
                $stmt = $pdo->prepare("SELECT user_id FROM project_comments WHERE id = ?");
                $stmt->execute([$parent_id]);
                $parentOwnerId = $stmt->fetchColumn();
                if ($parentOwnerId && $parentOwnerId != $user_id) {
                    addNotification($pdo, $parentOwnerId, "Quelqu'un a répondu à votre commentaire.", "project-detail.php?id=$project_id#comment-$newCommentId");
                }
            }

            $_SESSION['success_message'] = "Commentaire publié.";
        }
        header("Location: project-detail.php?id=$project_id#comments-section");

    } elseif ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $comment_id = intval($_POST['comment_id']);
        $project_id = intval($_POST['project_id']);
        $content = sanitizeInput($_POST['content']);

        $stmt = $pdo->prepare("SELECT user_id FROM project_comments WHERE id = :id");
        $stmt->execute([':id' => $comment_id]);
        $comment = $stmt->fetch();

        if ($comment && ($comment['user_id'] == $user_id || isAdmin())) {
            $stmt = $pdo->prepare("UPDATE project_comments SET content = :cont WHERE id = :id");
            $stmt->execute([':cont' => $content, ':id' => $comment_id]);
            $_SESSION['success_message'] = "Commentaire mis à jour.";
        }
        header("Location: project-detail.php?id=$project_id#comment-$comment_id");

    } elseif ($action === 'delete') {
        $comment_id = intval($_GET['id']);
        $project_id = intval($_GET['project_id']);

        $stmt = $pdo->prepare("SELECT user_id FROM project_comments WHERE id = :id");
        $stmt->execute([':id' => $comment_id]);
        $comment = $stmt->fetch();

        if ($comment && ($comment['user_id'] == $user_id || isAdmin())) {
            $stmt = $pdo->prepare("DELETE FROM project_comments WHERE id = :id");
            $stmt->execute([':id' => $comment_id]);
            $_SESSION['success_message'] = "Commentaire supprimé.";
        }
        header("Location: project-detail.php?id=$project_id");
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur : " . $e->getMessage();
    $project_id = intval($_POST['project_id'] ?? $_GET['project_id'] ?? 0);
    header("Location: " . ($project_id > 0 ? "project-detail.php?id=$project_id" : "projects.php"));
}
exit();