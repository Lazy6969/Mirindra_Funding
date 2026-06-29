<?php
$pageTitle = "Ma Messagerie";
require_once 'includes/header.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Requête pour récupérer les derniers messages de chaque conversation
$stmt = $pdo->prepare("
    SELECT 
        u.id as contact_id, 
        u.first_name, 
        u.last_name, 
        u.profile_image,
        m.content as last_message,
        m.created_at as message_date,
        m.is_read,
        m.sender_id,
        (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = :uid AND is_read = 0) as unread_count
    FROM (
        SELECT 
            CASE WHEN sender_id = :uid THEN receiver_id ELSE sender_id END as contact_id,
            MAX(id) as last_msg_id
        FROM messages 
        WHERE sender_id = :uid OR receiver_id = :uid
        GROUP BY contact_id
    ) as last_chats
    JOIN messages m ON m.id = last_chats.last_msg_id
    JOIN users u ON u.id = last_chats.contact_id
    ORDER BY m.created_at DESC
");
$stmt->execute([':uid' => $user_id]);
$conversations = $stmt->fetchAll();
?>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 1200px; margin: 0 auto; text-align: center; color: white;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;"><i class="fas fa-envelope"></i> Ma Messagerie</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">Retrouvez toutes vos discussions privées.</p>
    </div>
</section>

<div style="max-width: 800px; margin: 3rem auto; padding: 0 2rem;">
    <div style="background: white; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #eee; background: #f8f9fa; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.2rem; margin: 0; color: var(--dark-color);">Discussions récentes</h2>
            <a href="search-users.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; min-width: auto;">
                <i class="fas fa-user-plus"></i> Trouver des membres
            </a>
        </div>

        <?php if (empty($conversations)): ?>
            <div style="padding: 4rem 2rem; text-align: center; color: #999;">
                <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p>Vous n'avez pas encore de conversation.</p>
                <a href="projects.php" class="btn btn-primary" style="margin-top: 1rem;">Découvrir des projets</a>
            </div>
        <?php else: ?>
            <div class="conversation-list">
                <?php foreach ($conversations as $conv): ?>
                    <?php 
                    $isUnread = (!$conv['is_read'] && $conv['sender_id'] != $user_id);
                    $avatar = !empty($conv['profile_image']) ? SITE_URL . '/' . $conv['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($conv['first_name'].'+'.$conv['last_name']).'&background=667eea&color=fff';
                    ?>
                    <a href="messages.php?with=<?php echo $conv['contact_id']; ?>" 
                       style="display: flex; align-items: center; gap: 1rem; padding: 1.2rem; text-decoration: none; color: inherit; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; <?php echo $isUnread ? 'background: #f0f7ff;' : ''; ?>"
                       onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='<?php echo $isUnread ? '#f0f7ff' : 'transparent'; ?>'">
                        <img src="<?php echo $avatar; ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <div style="flex-grow: 1; overflow: hidden;">
                            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px;">
                                <h4 style="margin: 0; font-size: 1rem; color: var(--dark-color);">
                                    <?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span style="display: inline-block; background: var(--danger-color); color: white; border-radius: 50%; padding: 2px 8px; font-size: 0.75rem; font-weight: 700; margin-left: 8px; vertical-align: middle;">
                                            <?php echo $conv['unread_count']; ?>
                                        </span>
                                    <?php endif; ?>
                                </h4>
                                <small style="color: #999; font-size: 0.75rem;"><?php echo timeAgo($conv['message_date']); ?></small>
                            </div>
                            <p style="margin: 0; font-size: 0.9rem; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; <?php echo $isUnread ? 'font-weight: 700; color: #333;' : ''; ?>">
                                <?php if ($conv['sender_id'] == $user_id): ?>
                                    <i class="fas <?php echo $conv['is_read'] ? 'fa-check-double' : 'fa-check'; ?>" 
                                       style="font-size: 0.8rem; margin-right: 3px; <?php echo $conv['is_read'] ? 'color: #34b7f1;' : 'color: #999;'; ?>"></i>
                                    Vous : 
                                <?php endif; ?>
                                <?php echo htmlspecialchars($conv['last_message']); ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>