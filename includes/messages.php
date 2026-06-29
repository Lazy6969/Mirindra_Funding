<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

// Marquer les messages reçus de cet utilisateur comme "lus" AVANT d'inclure le header
// Cela permet au compteur de messages dans la barre de navigation d'être à jour immédiatement
if (isLoggedIn() && isset($_GET['with'])) {
    $database = new Database();
    $pdo = $database->getConnection();
    $with_id_to_read = intval($_GET['with']);
    $stmtRead = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
    $stmtRead->execute([$_SESSION['user_id'], $with_id_to_read]);
}

require_once 'includes/header.php';
requireLogin();

$with_id = intval($_GET['with'] ?? 0);

// Vérifier si on est connectés avant de chatter
$status = getConnectionStatus($pdo, $_SESSION['user_id'], $with_id);
if (!$status || $status['status'] !== 'accepted') {
    echo "<div class='container'><p>Vous devez être connectés pour discuter.</p></div>";
    require_once 'includes/footer.php'; exit;
}

// Récupérer les messages
$stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
$stmt->execute([$_SESSION['user_id'], $with_id, $with_id, $_SESSION['user_id']]);
$chat = $stmt->fetchAll();

// Récupérer infos destinataire
$stmt = $pdo->prepare("SELECT first_name, last_name, profile_image FROM users WHERE id = ?");
$stmt->execute([$with_id]);
$receiver = $stmt->fetch();
?>

<div style="max-width: 800px; margin: 2rem auto; padding: 2rem; background: white; border-radius: 12px; box-shadow: var(--shadow-md);">
    <h2 style="border-bottom: 2px solid #eee; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem;">
        <img src="<?php echo !empty($receiver['profile_image']) ? SITE_URL . '/' . $receiver['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($receiver['first_name'].'+'.$receiver['last_name']).'&background=667eea&color=fff'; ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
        Discussion avec <?php echo htmlspecialchars($receiver['first_name'] . ' ' . $receiver['last_name']); ?>
    </h2>

    <div id="chat-box" style="height: 400px; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; padding: 1rem; background: #f9f9f9; border-radius: 8px; margin-bottom: 1.5rem;">
        <?php foreach ($chat as $msg): ?>
            <?php $isMe = $msg['sender_id'] == $_SESSION['user_id']; ?>
            <div style="max-width: 70%; align-self: <?php echo $isMe ? 'flex-end' : 'flex-start'; ?>; background: <?php echo $isMe ? 'var(--primary-color)' : '#eee'; ?>; color: <?php echo $isMe ? 'white' : '#333'; ?>; padding: 0.8rem 1.2rem; border-radius: 15px; font-size: 0.95rem;">
                <?php if (!empty($msg['image_url'])): ?>
                    <img src="<?php echo SITE_URL . '/' . $msg['image_url']; ?>" style="max-width: 100%; height: auto; border-radius: 8px; margin-bottom: 8px;">
                <?php endif; ?>
                <?php if (!empty($msg['content'])): ?>
                    <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                <?php endif; ?>
                <div style="font-size: 0.7rem; opacity: 0.8; margin-top: 4px; display: flex; justify-content: flex-end; align-items: center; gap: 5px;">
                    <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                    <?php if ($isMe): ?>
                        <i class="fas <?php echo $msg['is_read'] ? 'fa-check-double' : 'fa-check'; ?>" 
                           style="<?php echo $msg['is_read'] ? 'color: #34b7f1;' : ''; ?>" 
                           title="<?php echo $msg['is_read'] ? 'Vu' : 'Envoyé'; ?>"></i>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form action="user-actions.php?action=send_message" method="POST" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center;">
        <input type="hidden" name="receiver_id" value="<?php echo $with_id; ?>">
        <input type="file" name="message_image" id="message_image" accept="image/*" style="display: none;">
        <label for="message_image" class="btn btn-secondary" style="cursor: pointer; padding: 0.5rem 1rem; font-size: 1rem; min-width: auto;">
            <i class="fas fa-image"></i>
        </label>
        <input type="text" name="content" class="form-control" placeholder="Écrivez votre message..." style="flex-grow: 1;">
        <button type="submit" class="btn btn-primary">Envoyer</button>
    </form>
</div>

<script>
    const chatBox = document.getElementById('chat-box');
    chatBox.scrollTop = chatBox.scrollHeight;
</script>
<?php require_once 'includes/footer.php'; ?>