<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$database = new Database();
$pdo = $database->getConnection();

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($projectId === 0) {
    redirect('projects.php');
}

// Marquer les notifications liées à ce projet (commentaires, dons, etc.) comme lues
if (isLoggedIn()) {
    $stmtMarkRead = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND link LIKE ? AND is_read = 0");
    // On utilise LIKE pour capturer les liens avec ancres comme "project-detail.php?id=XX#comment-YY"
    $stmtMarkRead->execute([$_SESSION['user_id'], "project-detail.php?id=$projectId%"]);
}

$pageTitle = "Détail du Projet";
require_once 'includes/header.php';

// Récupérer le projet
$stmt = $pdo->prepare("
    SELECT p.*, u.first_name, u.last_name, u.email as teacher_email, u.school_name
    FROM projects p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = :id
");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();

if (!$project) {
    redirect('projects.php');
}

// Récupérer les dons
$stmt = $pdo->prepare("
    SELECT d.*, u.first_name, u.last_name, u.profile_image 
    FROM donations d
    LEFT JOIN users u ON d.user_id = u.id
    WHERE d.project_id = :project_id AND d.payment_status = 'completed'
    ORDER BY d.created_at DESC
    LIMIT 10
");
$stmt->execute([':project_id' => $projectId]);
$donations = $stmt->fetchAll();

// Récupérer les commentaires
$stmt = $pdo->prepare("
    SELECT c.*, u.first_name, u.last_name, u.profile_image 
    FROM project_comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.project_id = :project_id
    ORDER BY c.created_at ASC
");
$stmt->execute([':project_id' => $projectId]);
$allComments = $stmt->fetchAll();

// Récupérer les actualités du projet
$stmt = $pdo->prepare("SELECT * FROM project_updates WHERE project_id = ? ORDER BY created_at DESC");
$stmt->execute([$projectId]);
$updates = $stmt->fetchAll();

// Récupérer les récompenses du projet
$stmt = $pdo->prepare("SELECT * FROM project_rewards WHERE project_id = ? ORDER BY min_amount ASC");
$stmt->execute([$projectId]);
$rewards = $stmt->fetchAll();

/**
 * Helper pour afficher un sélecteur d'émojis organisé par catégories
 */
function renderEmojiBar($textareaId) {
    $categories = [
        'Visages'  => ['😊', '😂', '😍', '🤔', '😎', '😮', '😢', '😡', '🥳', '😴', '😅', '🙄'],
        'Gestes'   => ['👍', '👎', '👏', '🙌', '🙏', '👋', '💪', '👊', '❤️', '✨', '✔️', '❌'],
        'Études'   => ['🚀', '🔥', '💡', '⭐', '🎓', '📚', '🏆', '🎁', '🌱', '🌍', '📝', '🎨'],
        'Divers'   => ['📣', '💬', '📌', '🔗', '⚠️', '🚩', '💰', '🏗️', '🧪', '⚽', '🎭', '🎹']
    ];
    
    $pickerId = 'picker-' . $textareaId;
    
    $html = '<div class="emoji-picker-wrapper" style="position: relative; margin-bottom: 8px;">';
    // Bouton d'ouverture
    $html .= '<button type="button" onclick="toggleElement(\'' . $pickerId . '\')" style="background: #f0f2f5; border: 1px solid #ddd; border-radius: 20px; padding: 5px 15px; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s;">';
    $html .= '😊 <span style="font-weight: 600; color: #555;">Émojis</span>';
    $html .= '</button>';
    
    // Conteneur du sélecteur (caché par défaut)
    $html .= '<div id="' . $pickerId . '" style="display: none; position: absolute; top: 40px; left: 0; z-index: 100; background: white; border: 1px solid #ddd; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); width: 280px; padding: 12px; max-height: 300px; overflow-y: auto;">';
    
    foreach ($categories as $name => $list) {
        $html .= '<div style="font-size: 0.7rem; font-weight: 800; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin: 10px 0 5px; border-bottom: 1px solid #f0f0f0; padding-bottom: 3px;">' . $name . '</div>';
        $html .= '<div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px;">';
        foreach ($list as $emoji) {
            $html .= '<span onclick="insertEmoji(\'' . $textareaId . '\', \'' . $emoji . '\'); toggleElement(\'' . $pickerId . '\');" 
                      style="cursor: pointer; font-size: 1.4rem; padding: 5px; border-radius: 8px; text-align: center; transition: background 0.2s;" 
                      onmouseover="this.style.background=\'#f0f2f5\'" onmouseout="this.style.background=\'transparent\'">' . $emoji . '</span>';
        }
        $html .= '</div>';
    }
    $html .= '</div></div>';
    return $html;
}

/**
 * Fonction récursive pour afficher les commentaires et leurs réponses
 * Permet de répondre à n'importe quel niveau de commentaire (sous-sous-commentaires)
 */
function renderComments($allComments, $parentId = null, $level = 0) {
    global $projectId;
    
    $filtered = array_filter($allComments, function($c) use ($parentId) {
        return $parentId === null ? $c['parent_id'] === null : (int)$c['parent_id'] === (int)$parentId;
    });

    foreach ($filtered as $comment) {
        // Limitation de l'indentation : 
        // Niveau 0 : Commentaire principal (pas de marge)
        // Niveau 1 : Sous-commentaire (marge de 2.5rem)
        // Niveau 2+ : Réponses au sous-commentaire (alignées sur le Niveau 1)
        if ($level === 0) {
            $indent = 'border-bottom: 1px solid #eee;';
        } elseif ($level === 1) {
            $indent = 'margin-left: 2.5rem; border-left: 2px solid #f0f0f0; padding-left: 1rem;';
        } else {
            // Aligné sur le sous-commentaire, avec une ligne de séparation discrète
            $indent = 'margin-left: 0; border-left: none; padding-left: 0; margin-top: 0.75rem; border-top: 1px dashed #eee; padding-top: 0.75rem;';
        }

        $imgSize = $level === 0 ? '40px' : '32px';
        $fontSize = $level === 0 ? '1rem' : '0.95rem';
        $itemStyle = $level === 0 ? 'padding: 1.5rem 0;' : 'padding: 0.75rem 0;';
        
        echo '<div class="comment-item" id="comment-' . $comment['id'] . '" style="' . $indent . ' ' . $itemStyle . '">';
        
        // En-tête du commentaire (Photo + Nom + Date)
        echo '<div class="comment-header" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">';
        $avatar = !empty($comment['profile_image']) ? SITE_URL . '/' . $comment['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($comment['first_name'].'+'.$comment['last_name']).'&background=667eea&color=fff';
        echo '<img src="' . $avatar . '" style="width: ' . $imgSize . '; height: ' . $imgSize . '; border-radius: 50%; object-fit: cover;">';
        echo '<div>';
        echo '<div style="font-weight: 700; color: var(--dark-color); font-size: ' . $fontSize . ';">';
        echo '<a href="user-profile.php?id=' . $comment['user_id'] . '" style="text-decoration: none; color: inherit; border-bottom: 1px dashed transparent; transition: border 0.2s;" onmouseover="this.style.borderBottomColor=\'var(--primary-color)\'" onmouseout="this.style.borderBottomColor=\'transparent\'">';
        echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']);
        echo '</a>';
        echo '</div>';
        echo '<div style="font-size: 0.8rem; color: #999;"><i class="fas fa-clock" style="font-size: 0.7rem; margin-right: 4px;"></i>' . timeAgo($comment['created_at']) . '</div>';
        echo '</div>';
        echo '</div>';

        // Contenu du commentaire
        echo '<div class="comment-content" id="content-' . $comment['id'] . '" style="color: #444; margin: 0.5rem 0; white-space: pre-line; font-size: ' . $fontSize . ';">' . nl2br(htmlspecialchars($comment['content'])) . '</div>';

        // Actions (Répondre, Modifier, Supprimer)
        echo '<div class="comment-actions" style="display: flex; gap: 1rem; font-size: 0.8rem;">';
        if (isLoggedIn()) {
            echo '<button onclick="toggleElement(\'reply-form-' . $comment['id'] . '\')" style="color: var(--primary-color); background: none; border: none; cursor: pointer; padding: 0; font-weight: 600;"><i class="fas fa-reply"></i> Répondre</button>';
            
            if ($_SESSION['user_id'] == $comment['user_id'] || isAdmin()) {
                echo '<button onclick="toggleEdit(\'' . $comment['id'] . '\')" style="color: #7f8c8d; background: none; border: none; cursor: pointer; padding: 0; font-weight: 600;"><i class="fas fa-edit"></i> Modifier</button>';
                echo '<a href="comment-actions.php?action=delete&id=' . $comment['id'] . '&project_id=' . $projectId . '" style="color: var(--danger-color); text-decoration: none; font-weight: 600;" onclick="return confirm(\'Supprimer ce commentaire ?\')"><i class="fas fa-trash"></i> Supprimer</a>';
            }
        }
        echo '</div>';

        // Formulaire de réponse (caché par défaut)
        if (isLoggedIn()) {
            echo '<div id="reply-form-' . $comment['id'] . '" style="display: none; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">';
            echo '<form action="comment-actions.php?action=add" method="POST">';
            echo '<input type="hidden" name="project_id" value="' . $projectId . '">';
            echo '<input type="hidden" name="parent_id" value="' . $comment['id'] . '">';
            echo renderEmojiBar('reply-textarea-' . $comment['id']);
            echo '<textarea id="reply-textarea-' . $comment['id'] . '" name="content" class="form-control" rows="2" placeholder="Votre réponse à ' . htmlspecialchars($comment['first_name']) . '..." required></textarea>';
            echo '<div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">';
            echo '<button type="submit" class="btn btn-primary" style="padding: 0.4rem 1rem; font-size: 0.85rem;">Répondre</button>';
            echo '<button type="button" onclick="toggleElement(\'reply-form-' . $comment['id'] . '\')" class="btn btn-secondary" style="padding: 0.4rem 1rem; font-size: 0.85rem; border: none; background: #eee; color: #666;">Annuler</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
        }

        // Formulaire de modification (caché par défaut)
        if (isLoggedIn() && ($_SESSION['user_id'] == $comment['user_id'] || isAdmin())) {
            echo '<div id="edit-form-' . $comment['id'] . '" style="display: none; margin-top: 1rem;">';
            echo '<form action="comment-actions.php?action=edit" method="POST">';
            echo '<input type="hidden" name="comment_id" value="' . $comment['id'] . '">';
            echo '<input type="hidden" name="project_id" value="' . $projectId . '">';
            echo renderEmojiBar('edit-textarea-' . $comment['id']);
            echo '<textarea id="edit-textarea-' . $comment['id'] . '" name="content" class="form-control" rows="2" required>' . htmlspecialchars($comment['content']) . '</textarea>';
            echo '<div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">';
            echo '<button type="submit" class="btn btn-primary" style="padding: 0.4rem 1rem; font-size: 0.85rem;">Enregistrer</button>';
            echo '<button type="button" onclick="toggleEdit(\'' . $comment['id'] . '\')" class="btn btn-secondary" style="padding: 0.4rem 1rem; font-size: 0.85rem; border: none; background: #eee; color: #666;">Annuler</button>';
            echo '</div>';
            echo '</form>';
            echo '</div>';
        }

        // APPEL RÉCURSIF : Affiche les réponses imbriquées sous ce commentaire
        renderComments($allComments, $comment['id'], $level + 1);
        
        echo '</div>';
    }
}

$progress = ($project['current_amount'] / $project['target_amount']) * 100;
$daysRemaining = getDaysRemaining($project['end_date']);
?>

<style>
/* Amélioration de la navigation vers un commentaire spécifique (via notification) */
.comment-item {
    scroll-margin-top: 100px; /* Empêche le commentaire d'être caché par la barre de navigation fixe */
    transition: background-color 0.5s ease;
}

/* Animation de surbrillance "bleu transparent" qui s'estompe */
@keyframes highlightCommentFade {
    0% { background-color: rgba(102, 126, 234, 0.3); } /* Bleu transparent */
    70% { background-color: rgba(102, 126, 234, 0.3); }
    100% { background-color: transparent; }
}

/* S'active lorsque l'URL contient l'ancre du commentaire (ex: #comment-12) */
.comment-item:target {
    animation: highlightCommentFade 5s ease-out forwards;
    border-radius: 12px;
}

.reward-card:hover {
    transform: translateX(5px);
    background: #f0f7ff !important;
}
</style>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 1200px; margin: 0 auto; color: white;">
        <a href="projects.php" style="color: white; text-decoration: none; display: inline-block; margin-bottom: 1rem;">
            <i class="fas fa-arrow-left"></i> Retour aux projets
        </a>
        <h1 style="font-size: 2.5rem; margin-bottom: 1rem;"><?php echo htmlspecialchars($project['title']); ?></h1>
        <p style="font-size: 1.1rem; opacity: 0.9;">
            <i class="fas fa-school"></i> <?php echo htmlspecialchars($project['school_name']); ?> | 
            <a href="user-profile.php?id=<?php echo $project['user_id']; ?>" style="color: white; font-weight: bold; text-decoration: underline;">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($project['first_name'] . ' ' . $project['last_name']); ?>
            </a>
        </p>
    </div>
</section>

<section style="padding: 3rem 2rem;">
    <div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr; gap: 3rem;">
        <!-- Contenu principal -->
        <div>
            <img src="<?php echo !empty($project['image_url']) ? SITE_URL . '/' . $project['image_url'] : 'https://via.placeholder.com/800x400/667eea/ffffff?text=Mirindra+Funding'; ?>" 
                 alt="<?php echo htmlspecialchars($project['title']); ?>" 
                 style="width: 100%; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
            
            <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); margin-bottom: 2rem;">
                <h2 style="margin-bottom: 1rem; color: var(--dark-color);">Description du projet</h2>
                <p style="line-height: 1.8; color: #555; white-space: pre-line;"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
            </div>
            
            <!-- Section Actualités (Updates) -->
            <?php if (isLoggedIn() && ($_SESSION['user_id'] == $project['user_id'] || isAdmin())): ?>
                <div style="background: #f0f7ff; padding: 2rem; border-radius: 12px; border: 1px dashed var(--primary-color); margin-bottom: 2.5rem;">
                    <h3 style="margin-bottom: 1.2rem; color: var(--primary-color); display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-bullhorn"></i> Publier une actualité
                    </h3>
                    <form action="project-update-actions.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                        <div class="form-group">
                            <label>Titre de l'actualité *</label>
                            <input type="text" name="title" class="form-control" required placeholder="Ex: Livraison des kits de robotique">
                        </div>
                        <div class="form-group">
                            <label>Contenu *</label>
                            <textarea name="content" class="form-control" rows="4" required placeholder="Détaillez l'avancement..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Photo d'illustration (optionnel)</label>
                            <input type="file" name="update_image" class="form-control" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Publier l'actualité</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (count($updates) > 0): ?>
                <div style="margin-bottom: 3rem;">
                    <h2 style="margin-bottom: 1.5rem; color: var(--dark-color);"><i class="fas fa-newspaper" style="color: var(--primary-color);"></i> Actualités du projet (<?php echo count($updates); ?>)</h2>
                    <div style="display: flex; flex-direction: column; gap: 2rem;">
                        <?php foreach ($updates as $upd): ?>
                            <div style="background: white; border-radius: 12px; border: 1px solid #eee; overflow: hidden; box-shadow: var(--shadow-sm);">
                                <?php if (!empty($upd['image_url'])): ?>
                                    <img src="<?php echo SITE_URL . '/' . $upd['image_url']; ?>" style="width: 100%; max-height: 350px; object-fit: cover;">
                                <?php endif; ?>
                                <div style="padding: 1.5rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                        <h3 style="margin: 0; color: var(--dark-color); font-size: 1.25rem;"><?php echo htmlspecialchars($upd['title']); ?></h3>
                                        <span style="font-size: 0.8rem; color: #999; font-weight: 600;"><?php echo formatDate($upd['created_at']); ?></span>
                                    </div>
                                    <p style="color: #555; line-height: 1.6; margin: 0; white-space: pre-line;"><?php echo nl2br(htmlspecialchars($upd['content'])); ?></p>

                                    <!-- Actions pour l'actualité (Propriétaire ou Admin uniquement) -->
                                    <?php if (isLoggedIn() && ($_SESSION['user_id'] == $project['user_id'] || isAdmin())): ?>
                                        <div style="margin-top: 1rem; display: flex; gap: 15px; font-size: 0.85rem;">
                                            <button onclick="toggleElement('edit-update-<?php echo $upd['id']; ?>')" style="background: none; border: none; color: #7f8c8d; cursor: pointer; padding: 0; font-weight: 600;">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                            <a href="project-update-actions.php?action=delete&id=<?php echo $upd['id']; ?>&project_id=<?php echo $projectId; ?>" 
                                               style="color: var(--danger-color); text-decoration: none; font-weight: 600;" 
                                               onclick="return confirm('Supprimer cette actualité ?')">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </a>
                                        </div>
                                        
                                        <!-- Formulaire de modification (caché par défaut) -->
                                        <div id="edit-update-<?php echo $upd['id']; ?>" style="display: none; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #eee;">
                                            <form action="project-update-actions.php?action=edit" method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="update_id" value="<?php echo $upd['id']; ?>">
                                                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                                <div class="form-group"><label>Titre</label><input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($upd['title']); ?>" required></div>
                                                <div class="form-group"><label>Contenu</label><textarea name="content" class="form-control" rows="3" required><?php echo htmlspecialchars($upd['content']); ?></textarea></div>
                                                <div class="form-group"><label>Changer la photo (optionnel)</label><input type="file" name="update_image" class="form-control" accept="image/*"></div>
                                                <div style="display: flex; gap: 10px; margin-top: 10px;">
                                                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">Enregistrer</button>
                                                    <button type="button" onclick="toggleElement('edit-update-<?php echo $upd['id']; ?>')" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; background: #eee; color: #666; border: none;">Annuler</button>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Liste des donateurs -->
            <?php if (count($donations) > 0): ?>
                <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                    <h2 style="margin-bottom: 1.5rem; color: var(--dark-color);">
                        <i class="fas fa-heart"></i> Les donateurs (<?php echo count($donations); ?>)
                    </h2>
                    
                    <div style="display: flex; flex-direction: column; gap: 1.2rem;">
                        <?php foreach ($donations as $donation): ?>
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1.2rem; background: white; border: 1px solid #f0f0f0; border-radius: 12px; transition: var(--transition); box-shadow: 0 2px 5px rgba(0,0,0,0.02);" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='#f0f0f0'">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <?php 
                                        // Définition de l'avatar : Photo de profil > Avatar initiales > Avatar anonyme
                                        $donorName = $donation['is_anonymous'] ? 'Anonyme' : (!empty($donation['first_name']) ? $donation['first_name'] . ' ' . $donation['last_name'] : $donation['donor_name']);
                                        
                                        $donorPic = 'https://ui-avatars.com/api/?name=' . urlencode($donorName) . '&background=f0f2f5&color=667eea';
                                        if (!$donation['is_anonymous'] && !empty($donation['profile_image'])) {
                                            $donorPic = SITE_URL . '/' . $donation['profile_image'];
                                        } elseif ($donation['is_anonymous']) {
                                            $donorPic = 'https://ui-avatars.com/api/?name=A&background=f0f2f5&color=999';
                                        }
                                    ?>
                                    <img src="<?php echo $donorPic; ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                    <div>
                                        <strong style="color: var(--dark-color); display: block; font-size: 1rem;">
                                            <?php echo htmlspecialchars($donorName); ?>
                                        </strong>
                                        <div style="font-size: 0.85rem; color: #999;">
                                            <i class="fas fa-clock" style="font-size: 0.75rem;"></i> <?php echo timeAgo($donation['created_at']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="font-weight: 800; color: var(--success-color); font-size: 1.1rem; background: #eefaf6; padding: 6px 14px; border-radius: 20px;">
                                    + <?php echo number_format($donation['amount'], 0, ',', ' '); ?> Ar
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Section Commentaires -->
            <div id="comments-section" style="margin-top: 3rem; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                <h2 style="margin-bottom: 1.5rem; color: var(--dark-color);">
                    <i class="fas fa-comments"></i> Commentaires (<?php echo count($allComments); ?>)
                </h2>

                <?php if (isLoggedIn()): ?>
                    <div class="comment-form-container" style="margin-bottom: 2rem;">
                        <form action="comment-actions.php?action=add" method="POST">
                            <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                            <div class="form-group">
                                <?php echo renderEmojiBar('main-comment-textarea'); ?>
                                <textarea id="main-comment-textarea" name="content" class="form-control" rows="3" placeholder="Qu'en pensez-vous ?" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Publier le commentaire</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p style="margin-bottom: 2rem; background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                        <i class="fas fa-info-circle"></i> <a href="login.php" style="color: var(--primary-color); font-weight: 600;">Connectez-vous</a> pour participer à la discussion.
                    </p>
                <?php endif; ?>

                <div class="comments-list">
                    <?php 
                    renderComments($allComments); 
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div>
            <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); position: sticky; top: 100px;">
                <div style="margin-bottom: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.5rem;">
                        <span style="font-size: 2rem; font-weight: 800; color: var(--success-color);">
                            <?php echo number_format($project['current_amount'], 0, ',', ' '); ?> Ar
                        </span>
                        <span style="color: #666;">
                            sur <?php echo number_format($project['target_amount'], 0, ',', ' '); ?> Ar
                        </span>
                    </div>
                    
                    <div class="progress-bar" style="height: 15px; margin-bottom: 1rem;">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; color: #666; font-size: 0.9rem;">
                        <span><i class="fas fa-percentage"></i> <?php echo round($progress, 1); ?>% financé</span>
                        <span><i class="fas fa-clock"></i> <?php echo $daysRemaining; ?> jours restants</span>
                    </div>
                </div>
                
                <?php if ($project['status'] === 'active' && isLoggedIn()): ?>
                    <button class="btn btn-primary donate-btn" 
                            style="width: 100%; margin-bottom: 1rem; padding: 1.2rem;"
                            data-project-id="<?php echo $project['id']; ?>">
                        <i class="fas fa-heart"></i> Faire un don
                    </button>
                <?php elseif ($project['status'] === 'active' && !isLoggedIn()): ?>
                    <a href="login.php" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem; padding: 1.2rem; text-align: center;">
                        <i class="fas fa-sign-in-alt"></i> Connectez-vous pour donner
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled style="width: 100%; margin-bottom: 1rem;">
                        Projet terminé
                    </button>
                <?php endif; ?>
                
                <!-- Interface d'ajout de récompense pour le propriétaire -->
                <?php if (isLoggedIn() && ($_SESSION['user_id'] == $project['user_id'] || isAdmin())): ?>
                    <div id="add-reward-box" style="margin-top: 1.5rem; padding: 1rem; background: #fff9eb; border: 1px dashed #f39c12; border-radius: 12px;">
                        <h4 style="color: #d35400; margin-bottom: 0.8rem; font-size: 0.9rem;">
                            <i class="fas fa-plus-circle"></i> Ajouter une contrepartie
                        </h4>
                        <form action="reward-actions.php?action=add" method="POST">
                            <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <input type="number" name="min_amount" class="form-control" placeholder="Montant min (Ar)" style="font-size: 0.85rem; padding: 5px 10px;" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <input type="text" name="title" class="form-control" placeholder="Titre (ex: Photo)" style="font-size: 0.85rem; padding: 5px 10px;" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0.8rem;">
                                <textarea name="description" class="form-control" placeholder="Description..." rows="2" style="font-size: 0.85rem; padding: 5px 10px;" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.5rem; font-size: 0.8rem; background: #f39c12; border: none;">
                                Enregistrer
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Section Récompenses / Contreparties -->
                <?php if (count($rewards) > 0): ?>
                    <div style="margin-top: 1.5rem; border-top: 1px solid #e0e0e0; padding-top: 1.5rem; margin-bottom: 1.5rem;">
                        <h3 style="margin-bottom: 1rem; font-size: 1.1rem;"><i class="fas fa-gift" style="color: var(--accent-color);"></i> Contreparties</h3>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($rewards as $reward): ?>
                                <div class="reward-card" style="padding: 1rem; background: #f8f9fa; border-radius: 8px; border-left: 4px solid var(--primary-color); transition: all 0.2s; cursor: pointer;">
                                    <div style="font-weight: 700; color: var(--primary-color); font-size: 0.9rem; margin-bottom: 4px;">
                                        Dès <?php echo number_format($reward['min_amount'], 0, ',', ' '); ?> Ar
                                    </div>
                                    <div style="font-weight: 600; font-size: 1rem; color: var(--dark-color); margin-bottom: 4px;">
                                        <?php echo htmlspecialchars($reward['title']); ?>
                                    </div>
                                    <p style="font-size: 0.85rem; color: #666; margin: 0 0 10px 0;"><?php echo htmlspecialchars($reward['description']); ?></p>
                                    <?php if ($project['status'] === 'active'): ?>
                                        <?php if (isLoggedIn()): ?>
                                            <button class="btn btn-primary donate-btn" style="width: 100%; padding: 0.5rem; font-size: 0.85rem; min-width: auto;" data-project-id="<?php echo $project['id']; ?>" data-amount="<?php echo $reward['min_amount']; ?>">Choisir</button>
                                        <?php else: ?>
                                            <a href="login.php" class="btn btn-primary" style="width: 100%; padding: 0.5rem; font-size: 0.85rem; min-width: auto; text-align: center;">Connectez-vous pour choisir</a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if (isLoggedIn() && ($_SESSION['user_id'] == $project['user_id'] || isAdmin())): ?>
                                        <a href="reward-actions.php?action=delete&id=<?php echo $reward['id']; ?>&project_id=<?php echo $projectId; ?>" 
                                           style="display: block; text-align: center; color: var(--danger-color); font-size: 0.75rem; margin-top: 8px; text-decoration: none;"
                                           onclick="return confirm('Supprimer cette récompense ?')">Supprimer</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isAdmin()): ?>
                    <div style="margin-top: 1.5rem; padding: 1.2rem; background: #fff5f5; border-radius: 12px; border: 1px solid #feb2b2;">
                        <h4 style="color: #c53030; margin-bottom: 0.8rem; font-size: 1rem;"><i class="fas fa-user-shield"></i> Administration</h4>
                        <a href="admin-actions.php?action=delete_project&id=<?php echo $project['id']; ?>" 
                           class="btn btn-secondary" 
                           onclick="return confirm('Supprimer définitivement ce projet ? Cette action supprimera également tous les dons associés.')"
                           style="width: 100%; color: #c53030; border-color: #c53030; text-align: center;">
                            <i class="fas fa-trash-alt"></i> Supprimer le projet
                        </a>
                    </div>
                <?php endif; ?>

                <div style="border-top: 1px solid #e0e0e0; padding-top: 1.5rem; margin-top: 1.5rem;">
                    <h3 style="margin-bottom: 1rem; font-size: 1.1rem;">Informations</h3>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: var(--dark-color);">Catégorie:</strong>
                        <div style="color: #666;"><?php echo ucfirst($project['category']); ?></div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: var(--dark-color);">Niveau:</strong>
                        <div style="color: #666;"><?php echo htmlspecialchars($project['class_level'] ?? 'Non spécifié'); ?></div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: var(--dark-color);">Nombre d'élèves:</strong>
                        <div style="color: #666;"><?php echo $project['number_of_students'] ?? 'Non spécifié'; ?></div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: var(--dark-color);">Date de fin:</strong>
                        <div style="color: #666;"><?php echo formatDate($project['end_date']); ?></div>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem; padding: 1rem; background: #e8f5e9; border-radius: 8px; border-left: 4px solid var(--success-color);">
                    <p style="font-size: 0.9rem; color: #2e7d32; margin: 0;">
                        <i class="fas fa-shield-alt"></i> Paiement 100% sécurisé
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Modal de Paiement Mobile -->
<div id="payment-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content payment-modal-box">
        <div class="modal-header">
            <h3>Faire un don</h3>
            <button class="close-modal">&times;</button>
        </div>
        
        <div class="modal-body">
            <div class="form-group">
                <label for="modal-amount">Montant (Ariary)</label>
                <input type="number" id="modal-amount" class="form-control" placeholder="Ex: 5000" min="100">
            </div>
            
            <div class="form-group">
                <label for="modal-phone">Numéro de téléphone</label>
                <input type="text" id="modal-phone" class="form-control" placeholder="034 / 032 / 033 ...">
            </div>

            <p style="margin-bottom: 1rem; font-weight: 600; font-size: 0.9rem;">Choisissez votre opérateur :</p>
            <div class="payment-methods-grid">
                <button class="payment-method-card mvola" data-method="mvola">
                    <div class="operator-logo logo-mvola"></div>
                    <span>Mvola</span>
                </button>
                <button class="payment-method-card orange" data-method="orange_money">
                    <div class="operator-logo logo-orange"></div>
                    <span>Orange Money</span>
                </button>
                <button class="payment-method-card airtel" data-method="airtel_money">
                    <div class="operator-logo logo-airtel"></div>
                    <span>Airtel Money</span>
                </button>
            </div>
        </div>
        
        <div id="payment-status-msg" style="margin-top: 1rem; font-size: 0.85rem; display: none;"></div>
    </div>
</div>

<script>
function toggleElement(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = (el.style.display === 'block') ? 'none' : 'block';
}
function toggleEdit(id) {
    toggleElement('edit-form-' + id);
    toggleElement('content-' + id);
}

/**
 * Insère un émoji à la position du curseur dans le textarea ciblé
 */
function insertEmoji(textareaId, emoji) {
    const textarea = document.getElementById(textareaId);
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    textarea.value = text.substring(0, start) + emoji + text.substring(end);
    textarea.focus();
    // Repositionne le curseur après l'émoji
    textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
}

/**
 * Gestion de l'ouverture du modal de don avec montant pré-rempli pour les récompenses
 */
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.donate-btn');
    if (btn) {
        const amount = btn.getAttribute('data-amount');
        if (amount) {
            const amountInput = document.getElementById('modal-amount');
            if (amountInput) amountInput.value = amount;
        }
        
        // Ouvrir le modal si l'action n'est pas déjà déclenchée par main.js
        const modal = document.getElementById('payment-modal');
        if (modal && modal.style.display !== 'flex') modal.style.display = 'flex';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>