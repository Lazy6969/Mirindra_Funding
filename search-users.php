<?php
$pageTitle = "Rechercher des membres";
require_once 'includes/header.php';
requireLogin();

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$users = [];

if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, profile_image, user_type, school_name FROM users WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) AND id != ? AND is_active = 1 LIMIT 20");
    $searchTerm = "%$search%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $_SESSION['user_id']]);
    $users = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, profile_image, user_type, school_name FROM users WHERE id != ? AND is_active = 1 ORDER BY created_at DESC LIMIT 12");
    $stmt->execute([$_SESSION['user_id']]);
    $users = $stmt->fetchAll();
}
?>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 1200px; margin: 0 auto; text-align: center; color: white;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;"><i class="fas fa-search"></i> Annuaire des membres</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">Trouvez des porteurs de projets, des donateurs ou l'administration.</p>
    </div>
</section>

<div style="max-width: 900px; margin: 3rem auto; padding: 0 2rem;">
    <div style="background: white; padding: 2rem; border-radius: 12px; box-shadow: var(--shadow-lg); margin-bottom: 2rem;">
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" class="form-control" placeholder="Rechercher par nom, établissement..." value="<?php echo htmlspecialchars($search); ?>" required>
            <button type="submit" class="btn btn-primary" style="min-width: auto;">Chercher</button>
        </form>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
        <?php if (empty($users)): ?>
            <p style="grid-column: 1/-1; text-align: center; color: #999; padding: 2rem;">Aucun membre trouvé.</p>
        <?php else: ?>
            <?php foreach ($users as $u): ?>
                <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow-md); text-align: center;">
                    <img src="<?php echo !empty($u['profile_image']) ? SITE_URL . '/' . $u['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($u['first_name'].'+'.$u['last_name']).'&background=667eea&color=fff'; ?>" 
                         style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem; border: 3px solid #f0f2f5;">
                    <h4 style="margin: 0 0 0.25rem; color: var(--dark-color);"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></h4>
                    <p style="font-size: 0.85rem; color: #7f8c8d; margin-bottom: 1rem;">
                        <span style="display: inline-block; padding: 2px 8px; background: #ebf0ff; color: #5a67d8; border-radius: 10px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                            <?php echo $u['user_type']; ?>
                        </span>
                        <?php if($u['school_name']) echo '<br><span style="font-size: 0.8rem;">' . htmlspecialchars($u['school_name']) . '</span>'; ?>
                    </p>
                    <a href="user-profile.php?id=<?php echo $u['id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; width: 100%;">Voir le profil</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>