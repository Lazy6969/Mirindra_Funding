<?php
$pageTitle = "Mot de passe oublié";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email)) {
        $error = "Veuillez entrer votre adresse email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } else {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Vérifier si l'utilisateur existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Générer un jeton unique et sécurisé
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Nettoyer les anciens jetons
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            
            // Insérer le jeton
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $expires]);
            
            $resetLink = SITE_URL . "/reset-password.php?token=" . $token;
            $subject = "Réinitialisation de votre mot de passe";
            $body = "Cliquez sur ce lien pour changer votre mot de passe (valide 1h) : <br><a href='$resetLink'>$resetLink</a>";
            
            if (sendEmail($email, $subject, $body)) {
                $message = "Un lien de réinitialisation a été envoyé à votre adresse.";
            } else {
                // En local, on affiche le lien car mail() ne fonctionne pas sans configuration
                $message = "Test local : <a href='$resetLink'>Cliquez ici pour réinitialiser</a>";
            }
        } else {
            $message = "Un lien de réinitialisation a été envoyé si cet email existe.";
        }
    }
}
?>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 1200px; margin: 0 auto; text-align: center; color: white;">
        <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Récupération</h1>
    </div>
</section>

<div class="form-container">
    <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Email du compte</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">Envoyer le lien</button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>