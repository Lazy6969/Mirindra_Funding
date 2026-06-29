<?php
$pageTitle = "Mes Notifications";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();

$user_id = $_SESSION['user_id'];
$notifications = getAllNotifications($pdo, $user_id);
?>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 1200px; margin: 0 auto; text-align: center; color: white;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;"><i class="fas fa-bell"></i> Mes Notifications</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">Toutes les informations importantes vous concernant.</p>
    </div>
</section>

<div style="max-width: 900px; margin: 3rem auto; padding: 0 2rem;">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" style="margin-bottom: 2rem;">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error" style="margin-bottom: 2rem;">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee;">
            <h2 style="font-size: 1.8rem; color: var(--dark-color);">Historique des notifications</h2>
            <div style="display: flex; gap: 10px;">
                <a href="notification-actions.php?action=mark_all_read&redirect=notifications.php" class="btn btn-secondary" style="padding: 0.6rem 1.2rem; font-size: 0.9rem; min-width: auto;">
                    <i class="fas fa-check-double"></i> Tout marquer comme lu
                </a>
                <a href="notification-actions.php?action=delete_all&redirect=notifications.php" class="btn btn-secondary" style="padding: 0.6rem 1.2rem; font-size: 0.9rem; min-width: auto; color: var(--danger-color); border-color: var(--danger-color);" onclick="return confirm('Voulez-vous supprimer TOUTES vos notifications ?')">
                    <i class="fas fa-trash-alt"></i> Tout supprimer
                </a>
            </div>
        </div>

        <div class="notifications-list">
            <?php if (empty($notifications)): ?>
                <p style="text-align: center; color: #999; padding: 3rem;">Vous n'avez aucune notification pour le moment.</p>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <?php
                    $notifClass = $notif['is_read'] ? 'notification-read' : 'notification-unread';
                    $icon = 'fas fa-info-circle'; // Icône par défaut
                    if (strpos($notif['message'], 'commentaire') !== false || strpos($notif['message'], 'répondu') !== false) {
                        $icon = 'fas fa-comment-dots';
                    } elseif (strpos($notif['message'], 'don') !== false || strpos($notif['message'], 'validé') !== false) {
                        $icon = 'fas fa-hand-holding-heart';
                    }
                    ?>
                    <div class="notification-row" style="display: flex; align-items: center; border-bottom: 1px solid #f9f9f9; transition: background 0.2s; <?php echo $notif['is_read'] ? 'opacity: 0.7;' : 'background: #f0f7ff;'; ?>">
                        <a href="notification-actions.php?action=read&id=<?php echo $notif['id']; ?>" 
                           class="notification-item <?php echo $notifClass; ?>"
                           style="display: flex; align-items: center; gap: 1rem; padding: 1rem; text-decoration: none; color: #333; flex-grow: 1;">
                            <i class="<?php echo $icon; ?>" style="font-size: 1.5rem; color: var(--primary-color);"></i>
                            <div style="flex-grow: 1;">
                                <div style="font-weight: <?php echo $notif['is_read'] ? 'normal' : 'bold'; ?>; font-size: 0.95rem;"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <small style="color: #999; font-size: 0.8rem;"><i class="fas fa-clock"></i> <?php echo timeAgo($notif['created_at']); ?></small>
                            </div>
                            <?php if (!$notif['is_read']): ?>
                                <span style="width: 8px; height: 8px; background: var(--danger-color); border-radius: 50%; flex-shrink: 0;"></span>
                            <?php endif; ?>
                        </a>
                        <a href="notification-actions.php?action=delete&id=<?php echo $notif['id']; ?>&redirect=notifications.php" 
                           style="padding: 1rem; color: var(--danger-color); font-size: 1.1rem; transition: transform 0.2s;" 
                           onclick="return confirm('Supprimer cette notification ?')"
                           onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'"
                           title="Supprimer">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>