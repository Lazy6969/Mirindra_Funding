<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

// Initialisation de la connexion à la base de données si elle n'est pas déjà définie
if (!isset($pdo)) {
    $database = new Database();
    $pdo = $database->getConnection();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Mirindra Funding - Plateforme de crowdfunding pour projets éducatifs">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Mirindra Funding</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/images/favicon.png">
    
    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/animations.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/cursor-effects.css">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?php echo SITE_URL; ?>" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <span class="logo-text">Mirindra Funding</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="<?php echo SITE_URL; ?>">Accueil</a></li>
                <li><a href="<?php echo SITE_URL; ?>/projects.php">Projets</a></li>
                <?php if (isLoggedIn()): ?>
                    <?php $unreadMsgCount = getUnreadMessagesCount($pdo, $_SESSION['user_id']); ?>
                    <li>
                        <a href="<?php echo SITE_URL; ?>/conversations.php" title="Messagerie" style="padding: 10px; position: relative; color: var(--dark-color); text-decoration: none; display: inline-block;">
                            <i class="fas fa-envelope"></i>
                            <?php if ($unreadMsgCount > 0): ?>
                                <span style="position: absolute; top: 0; right: 0; background: var(--danger-color); color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; font-weight: bold;"><?php echo $unreadMsgCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li><a href="<?php echo SITE_URL; ?>/create-project.php">Créer un projet</a></li>
                <li><a href="<?php echo SITE_URL; ?>/#how-it-works">Comment ça marche</a></li>
                
                <?php if (isLoggedIn()): ?>
                    <?php 
                    $notifCount = getUnreadNotificationsCount($pdo, $_SESSION['user_id']); 
                    $recentNotifs = getNotifications($pdo, $_SESSION['user_id']);
                    ?>
                    <!-- Cloche de Notifications -->
                    <li class="nav-item dropdown" style="position: relative; list-style: none;">
                        <a href="#" class="nav-link" id="notif-bell" style="padding: 10px; position: relative; color: var(--dark-color); text-decoration: none;">
                            <i class="fas fa-bell"></i>
                            <?php if ($notifCount > 0): ?>
                                <span style="position: absolute; top: 0; right: 0; background: var(--danger-color); color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; font-weight: bold;"><?php echo $notifCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <div id="notif-dropdown" style="display: none; position: absolute; right: 0; top: 45px; background: white; border: 1px solid #ddd; width: 300px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); border-radius: 12px; z-index: 9999; overflow: hidden;">
                            <div style="padding: 12px; background: #f8f9fa; border-bottom: 1px solid #eee; font-weight: 700; display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #333;">Notifications</span>
                                <a href="notification-actions.php?action=mark_all_read&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" style="font-size: 0.75rem; color: var(--primary-color); text-decoration: none;">Tout lire</a>
                            </div>
                            <div style="max-height: 350px; overflow-y: auto;">
                                <?php if (empty($recentNotifs)): ?>
                                    <p style="padding: 20px; text-align: center; color: #999; font-size: 0.9rem;">Aucune notification</p>
                                <?php else: ?>
                                    <?php foreach ($recentNotifs as $notif): ?>
                                        <?php
                                        $icon = 'fas fa-info-circle';
                                        if (strpos($notif['message'], 'commentaire') !== false || strpos($notif['message'], 'répondu') !== false) $icon = 'fas fa-comment-dots';
                                        elseif (strpos($notif['message'], 'don') !== false) $icon = 'fas fa-hand-holding-heart';
                                        ?>
                                        <a href="notification-actions.php?action=read&id=<?php echo $notif['id']; ?>" 
                                           style="display: flex; align-items: center; gap: 10px; padding: 12px; text-decoration: none; border-bottom: 1px solid #f9f9f9; transition: background 0.2s; <?php echo $notif['is_read'] ? 'opacity: 0.6;' : 'background: #f0f7ff;'; ?>">
                                            <i class="<?php echo $icon; ?>" style="color: var(--primary-color); width: 20px;"></i>
                                            <div style="flex-grow: 1;">
                                                <div style="color: #333; font-size: 0.85rem; line-height: 1.3;"><?php echo htmlspecialchars($notif['message']); ?></div>
                                                <small style="color: #999; font-size: 0.75rem;"><?php echo timeAgo($notif['created_at']); ?></small>
                                            </div>
                                            <?php if (!$notif['is_read']): ?>
                                                <span style="width: 8px; height: 8px; background: var(--danger-color); border-radius: 50%; flex-shrink: 0;"></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div style="padding: 12px; text-align: center; border-top: 1px solid #eee; background: #fff;">
                                <a href="notifications.php" style="color: var(--primary-color); text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                                    Voir toutes les notifications <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </li>

                    <?php if (isAdmin()): ?>
                        <li>
                            <a href="<?php echo SITE_URL; ?>/dashboard.php" style="color: var(--accent-color); font-weight: 700;"><i class="fas fa-user-shield"></i> Administration</a>
                        </li>
                    <?php endif; ?>
                    <li><a href="<?php echo SITE_URL; ?>/dashboard.php">Mon compte</a></li>
                    
                    <a href="<?php echo SITE_URL; ?>/logout.php" class="nav-btn nav-btn-outline magnetic-btn logout-trigger">Déconnexion</a>
                <?php else: ?>
                    <li><a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-secondary">Connexion</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary">S'inscrire</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <script>
    document.getElementById('notif-bell')?.addEventListener('click', function(e) {
        e.preventDefault();
        const dropdown = document.getElementById('notif-dropdown');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    });

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notif-dropdown');
        if (dropdown && !e.target.closest('.nav-item.dropdown')) {
            dropdown.style.display = 'none';
        }
    });
    </script>
    
    <main>