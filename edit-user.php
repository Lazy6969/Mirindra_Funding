<?php
$pageTitle = "Modifier l'utilisateur";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

requireLogin();
if (!isAdmin()) {
    redirect('dashboard.php');
}

$database = new Database();
$pdo = $database->getConnection();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) redirect('dashboard.php');

// Récupérer les infos de l'utilisateur à modifier
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user_to_edit = $stmt->fetch();

if (!$user_to_edit) redirect('dashboard.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $user_type = sanitizeInput($_POST['user_type']);
    $school_name = sanitizeInput($_POST['school_name']);
    $new_password = $_POST['new_password'];

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $errors[] = "Tous les champs obligatoires (*) doivent être remplis.";
    }

    if (empty($errors)) {
        try {
            $sql = "UPDATE users SET first_name = :fn, last_name = :ln, email = :em, user_type = :ut, school_name = :sn";
            $params = [':fn' => $first_name, ':ln' => $last_name, ':em' => $email, ':ut' => $user_type, ':sn' => $school_name, ':id' => $id];

            if (!empty($new_password)) {
                $sql .= ", password = :pw";
                $params[':pw'] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['success_message'] = "L'utilisateur a été mis à jour.";
            redirect('dashboard.php');
        } catch (PDOException $e) {
            $errors[] = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<section style="background: var(--gradient-primary); padding: 4rem 2rem 3rem;">
    <div style="max-width: 800px; margin: 0 auto; color: white;">
        <a href="dashboard.php" style="color: white; text-decoration: none; display: inline-block; margin-bottom: 1rem;">
            <i class="fas fa-arrow-left"></i> Retour au Dashboard
        </a>
        <h1>Modifier l'utilisateur : <?php echo htmlspecialchars($user_to_edit['first_name']); ?></h1>
    </div>
</section>

<div class="form-container" style="max-width: 800px; margin-top: -2rem;">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><?php echo implode('<br>', $errors); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label>Prénom *</label>
                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user_to_edit['first_name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Nom *</label>
                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user_to_edit['last_name']); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_to_edit['email']); ?>" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label>Type de compte</label>
                <select name="user_type" class="form-control">
                    <option value="donor" <?php if($user_to_edit['user_type'] == 'donor') echo 'selected'; ?>>Donateur</option>
                    <option value="teacher" <?php if($user_to_edit['user_type'] == 'teacher') echo 'selected'; ?>>Enseignant</option>
                    <option value="student" <?php if($user_to_edit['user_type'] == 'student') echo 'selected'; ?>>Étudiant</option>
                    <option value="admin" <?php if($user_to_edit['user_type'] == 'admin') echo 'selected'; ?>>Administrateur</option>
                </select>
            </div>
            <div class="form-group">
                <label>Établissement</label>
                <input type="text" name="school_name" class="form-control" value="<?php echo htmlspecialchars($user_to_edit['school_name']); ?>">
            </div>
        </div>

        <div class="form-group" style="margin-top: 1rem; padding: 1rem; background: white; border: 1px solid #eee; border-radius: 8px;">
            <label>Changer le mot de passe (laisser vide pour ne pas modifier)</label>
            <input type="password" name="new_password" class="form-control" placeholder="Nouveau mot de passe">
        </div>

        <div style="margin-top: 2rem;">
            <button type="submit" class="btn btn-primary" style="width: 100%;">Enregistrer les modifications</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>