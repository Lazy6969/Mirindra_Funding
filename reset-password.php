<?php
$pageTitle = "Nouveau mot de passe";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if (empty($token)) redirect('login.php');

$database = new Database();
$pdo = $database->getConnection();

// Valider le jeton
$stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $error = "Lien invalide ou expiré.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    $pass = $_POST['password'];
    if (strlen($pass) < 6) {
        $error = "6 caractères minimum.";
    } elseif ($pass !== $_POST['confirm']) {
        $error = "Les mots de passe diffèrent.";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $reset['email']]);
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$reset['email']]);
        $success = true;
    }
}
?>

<div class="form-container" style="margin-top: 5rem;">
    <h2>Réinitialisation</h2>
    <?php if ($success): ?>
        <div class="alert alert-success">Mot de passe mis à jour ! <a href="login.php">Connexion</a></div>
    <?php else: ?>
        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($reset): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Confirmer</label>
                    <input type="password" name="confirm" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Changer le mot de passe</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>