<?php
$pageTitle = "Connexion";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND is_active = 1");
        $stmt->execute([':email' => sanitizeInput($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['profile_image'] = $user['profile_image'];
            
            redirect('dashboard.php');
        } else {
            $error = "Email ou mot de passe incorrect";
        }
    }
}
?>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 1200px; margin: 0 auto; text-align: center; color: white;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;">Connexion</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">Accédez à votre espace personnel</p>
    </div>
</section>

<div class="form-container">
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email" class="form-control" required
                   placeholder="votre@email.com" autocomplete="off">
        </div>
        
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" class="form-control" required
                   placeholder="••••••••" autocomplete="off">
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" name="remember">
                <span>Se souvenir de moi</span>
            </label>
            <a href="forgot-password.php" style="color: var(--primary-color); text-decoration: none;">Mot de passe oublié?</a>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
            <i class="fas fa-sign-in-alt"></i> Se connecter
        </button>
        
        <p style="text-align: center; margin-top: 1.5rem; color: #666;">
            Pas encore de compte? <a href="register.php" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">S'inscrire</a>
        </p>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Vider les champs email et mot de passe au chargement de la page
    document.getElementById('email').value = 'example@gmail.com';
    document.getElementById('password').value = '********';
});
</script>

<?php require_once 'includes/footer.php'; ?>