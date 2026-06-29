<?php
require_once 'includes/header.php';
requireLogin();

$id = intval($_GET['id'] ?? 0);
if ($id === 0 || $id === $_SESSION['user_id']) redirect('dashboard.php');

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$targetUser = $stmt->fetch();
if (!$targetUser) redirect('dashboard.php');

$connection = getConnectionStatus($pdo, $_SESSION['user_id'], $id);

// Récupérer les projets de cet utilisateur
$stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ? AND status != 'cancelled' ORDER BY created_at DESC");
$stmt->execute([$id]);
$userProjects = $stmt->fetchAll();

// Récupérer les amis (connexions acceptées)
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.profile_image 
    FROM user_connections c
    JOIN users u ON (u.id = c.sender_id OR u.id = c.receiver_id)
    WHERE (c.sender_id = ? OR c.receiver_id = ?) 
    AND c.status = 'accepted' 
    AND u.id != ?
");
$stmt->execute([$id, $id, $id]);
$friends = $stmt->fetchAll();
?>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem;">
    <div style="max-width: 800px; margin: 0 auto; color: white; text-align: center;">
        <div style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; margin: 0 auto 1.5rem; border: 4px solid white;">
            <img src="<?php echo !empty($targetUser['profile_image']) ? SITE_URL . '/' . $targetUser['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($targetUser['first_name'].'+'.$targetUser['last_name']); ?>" style="width:100%; height:100%; object-fit:cover;">
        </div>
        <h1><?php echo htmlspecialchars($targetUser['first_name'] . ' ' . $targetUser['last_name']); ?></h1>
        <p><?php echo ucfirst($targetUser['user_type']); ?> <?php echo $targetUser['school_name'] ? 'chez ' . htmlspecialchars($targetUser['school_name']) : ''; ?></p>
    </div>
</section>

<div style="max-width: 800px; margin: -2rem auto 3rem; padding: 0 2rem; position: relative;">
    <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-lg); text-align: center;">
        <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 1rem;">
            <?php if (!$connection): ?>
                <a href="user-actions.php?action=send_invitation&id=<?php echo $id; ?>" class="btn btn-primary">Envoyer une invitation</a>
            <?php elseif ($connection['status'] === 'pending'): ?>
                <?php if ($connection['sender_id'] == $_SESSION['user_id']): ?>
                    <button class="btn btn-secondary" disabled>Invitation envoyée (En attente)</button>
                <?php else: ?>
                    <a href="user-actions.php?action=accept_invitation&id=<?php echo $connection['id']; ?>" class="btn btn-primary">Accepter l'invitation</a>
                    <a href="user-actions.php?action=refuse_invitation&id=<?php echo $connection['id']; ?>" class="btn btn-secondary" style="color:red; border-color:red;">Refuser</a>
                <?php endif; ?>
            <?php elseif ($connection['status'] === 'accepted'): ?>
                <a href="messages.php?with=<?php echo $id; ?>" class="btn btn-primary"><i class="fas fa-envelope"></i> Envoyer un message</a>
                <span class="btn btn-secondary" disabled><i class="fas fa-check"></i> Amis</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="max-width: 1000px; margin: 0 auto 5rem; padding: 0 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem;">
    
    <!-- Section Projets de l'utilisateur -->
    <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-md);">
        <h3 style="margin-bottom: 1.5rem; color: var(--dark-color); display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-lightbulb" style="color: var(--primary-color);"></i> Projets lancés (<?php echo count($userProjects); ?>)
        </h3>
        
        <?php if (empty($userProjects)): ?>
            <p style="color: #999; text-align: center; padding: 2rem;">Aucun projet publié pour le moment.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($userProjects as $proj): ?>
                    <a href="project-detail.php?id=<?php echo $proj['id']; ?>" style="text-decoration: none; display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid #eee; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">
                        <div>
                            <div style="font-weight: 600; color: var(--dark-color);"><?php echo htmlspecialchars($proj['title']); ?></div>
                            <div style="font-size: 0.8rem; color: #999;"><?php echo formatAmount($proj['current_amount']); ?> récoltés</div>
                        </div>
                        <span style="font-size: 0.7rem; background: #ebf0ff; color: #5a67d8; padding: 2px 8px; border-radius: 10px; text-transform: uppercase;"><?php echo $proj['status']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section Réseau d'Amis -->
    <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-md);">
        <h3 style="margin-bottom: 1.5rem; color: var(--dark-color); display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-users" style="color: var(--primary-color);"></i> Réseau d'amis (<?php echo count($friends); ?>)
        </h3>

        <?php if (empty($friends)): ?>
            <p style="color: #999; text-align: center; padding: 2rem;">Pas encore d'amis dans le réseau.</p>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 1rem;">
                <?php foreach ($friends as $friend): ?>
                    <a href="user-profile.php?id=<?php echo $friend['id']; ?>" title="<?php echo htmlspecialchars($friend['first_name'] . ' ' . $friend['last_name']); ?>" style="text-decoration: none; text-align: center;">
                        <img src="<?php echo !empty($friend['profile_image']) ? SITE_URL . '/' . $friend['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($friend['first_name'].'+'.$friend['last_name']).'&background=667eea&color=fff'; ?>" 
                             style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s;"
                             onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                        <span style="display: block; font-size: 0.75rem; color: #666; margin-top: 0.5rem; text-align: center;">
                            <?php echo htmlspecialchars($friend['first_name'] . ' ' . $friend['last_name']); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Détails supplémentaires en bas -->
            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #eee; font-size: 0.9rem; color: #666;">
                <div style="margin-bottom: 0.5rem;">
                    <i class="fas fa-calendar-alt" style="width: 20px;"></i> Membre depuis : <?php echo date('M Y', strtotime($targetUser['created_at'])); ?>
                </div>
                <div>
                    <i class="fas fa-id-badge" style="width: 20px;"></i> Rôle : <?php echo ucfirst($targetUser['user_type']); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>
<?php require_once 'includes/footer.php'; ?>