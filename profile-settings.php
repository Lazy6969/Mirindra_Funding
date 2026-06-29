<?php
$pageTitle = "Paramètres du profil";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Récupérer les infos actuelles
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $school_name = sanitizeInput($_POST['school_name']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $errors[] = "Le nom, le prénom et l'email sont obligatoires.";
    }

    // Gestion de l'image de profil
    $profile_image = $user['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadImage($_FILES['profile_image'], $first_name . '-' . $last_name);
        if ($uploadResult['success']) {
            $profile_image = $uploadResult['filepath'];
        } else {
            $errors[] = $uploadResult['message'];
        }
    }

    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = "Le nouveau mot de passe doit faire au moins 6 caractères.";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "Les nouveaux mots de passe ne correspondent pas.";
        }
    }

    if (empty($errors)) {
        try {
            $sql = "UPDATE users SET first_name = :fn, last_name = :ln, email = :em, school_name = :sn, profile_image = :pi";
            $params = [':fn' => $first_name, ':ln' => $last_name, ':em' => $email, ':sn' => $school_name, ':pi' => $profile_image, ':id' => $user_id];

            if (!empty($new_password)) {
                $sql .= ", password = :pw";
                $params[':pw'] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            $_SESSION['profile_image'] = $profile_image;
            $success = true;
            // Rafraîchir les données locales
            $user['first_name'] = $first_name; $user['last_name'] = $last_name; $user['email'] = $email; $user['school_name'] = $school_name; $user['profile_image'] = $profile_image;
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}
?>

<section style="background: var(--gradient-primary); padding: 4rem 2rem 3rem;">
    <div style="max-width: 800px; margin: 0 auto; color: white;">
        <a href="dashboard.php" style="color: white; text-decoration: none; display: inline-block; margin-bottom: 1rem;">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
        </a>
        <h1>Modifier mon profil</h1>
    </div>
</section>

<div class="form-container" style="max-width: 800px; margin-top: -2rem;">
    <?php if ($success): ?>
        <div class="alert alert-success">Votre profil a été mis à jour avec succès !</div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><?php echo implode('<br>', $errors); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div style="display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem; padding: 1.5rem; background: white; border: 1px solid #eee; border-radius: 12px;">
            <div style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; border: 3px solid var(--primary-color); background: #eee; flex-shrink: 0;">
                <img src="<?php echo !empty($user['profile_image']) ? SITE_URL . '/' . $user['profile_image'] : 'https://ui-avatars.com/api/?name='.urlencode($user['first_name'].'+'.$user['last_name']).'&background=667eea&color=fff'; ?>" 
                     alt="Aperçu" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div class="form-group" style="flex: 1; margin: 0;">
                <label>Photo de profil</label>
                <input type="file" name="profile_image" class="form-control" accept="image/*">
                <small style="color: #666;">Formats: JPG, PNG, WebP. Max 5Mo.</small>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group"><label>Prénom *</label><input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name']); ?>" required></div>
            <div class="form-group"><label>Nom *</label><input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name']); ?>" required></div>
        </div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
        <div class="form-group"><label>Établissement</label><input type="text" name="school_name" class="form-control" value="<?php echo htmlspecialchars($user['school_name']); ?>"></div>
        
        <hr style="margin: 2rem 0; border: 0; border-top: 1px solid #eee;">
        <h3 style="margin-bottom: 1rem;">Changer le mot de passe</h3>
        <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">Laissez vide si vous ne souhaitez pas changer de mot de passe.</p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group"><label>Nouveau mot de passe</label><input type="password" name="new_password" class="form-control" placeholder="••••••••"></div>
            <div class="form-group"><label>Confirmer le mot de passe</label><input type="password" name="confirm_password" class="form-control" placeholder="••••••••"></div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Enregistrer les modifications</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>