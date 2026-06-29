<?php
$pageTitle = "Inscription";
require_once 'includes/header.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = sanitizeInput($_POST['user_type']);
    $school_name = isset($_POST['school_name']) ? sanitizeInput($_POST['school_name']) : '';
    
    // Validation
    if (empty($first_name)) $errors[] = "Le prénom est requis";
    if (empty($last_name)) $errors[] = "Le nom est requis";
    if (empty($email)) $errors[] = "L'email est requis";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'email n'est pas valide";
    if (empty($password)) $errors[] = "Le mot de passe est requis";
    if (strlen($password) < 6) $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
    if ($password !== $confirm_password) $errors[] = "Les mots de passe ne correspondent pas";
    if ($user_type === 'teacher' && empty($school_name)) $errors[] = "Le nom de l'école est requis pour les enseignants";
    
    // Vérifier si l'email existe déjà
    if (empty($errors)) {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        
        if ($stmt->fetch()) {
            $errors[] = "Cet email est déjà utilisé";
        }
    }
    
    // Créer le compte
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (email, password, first_name, last_name, user_type, school_name)
                VALUES (:email, :password, :first_name, :last_name, :user_type, :school_name)
            ");
            
            $stmt->execute([
                ':email' => $email,
                ':password' => $hashed_password,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':user_type' => $user_type,
                ':school_name' => $school_name
            ]);
            
            $_SESSION['success_message'] = "Compte créé avec succès! Vous pouvez maintenant vous connecter.";
            redirect('login.php');
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la création du compte: " . $e->getMessage();
        }
    }
}
?>

<section style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 4rem 2rem 3rem;">
    <div style="max-width: 1200px; margin: 0 auto; text-align: center; color: white;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;">Créer un compte</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">Rejoignez la communauté Mirindra Funding</p>
    </div>
</section>

<div class="form-container">
    <?php if (count($errors) > 0): ?>
        <div class="alert alert-error">
            <ul style="margin: 0; padding-left: 1.5rem;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label for="first_name">Prénom *</label>
                <input type="text" id="first_name" name="first_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="last_name">Nom *</label>
                <input type="text" id="last_name" name="last_name" class="form-control" required>
            </div>
        </div>
        
        <div class="form-group">
            <label for="email">Adresse email *</label>
            <input type="email" id="email" name="email" class="form-control" required
                   placeholder="votre@email.com">
        </div>
        
        <div class="form-group">
            <label for="user_type">Type de compte *</label>
            <select id="user_type" name="user_type" class="form-control" required onchange="toggleSchoolField()">
                <option value="donor" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'donor') ? 'selected' : ''; ?>>Donateur</option>
                <option value="teacher" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'teacher') ? 'selected' : ''; ?>>Enseignant</option>
                <option value="student" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'student') ? 'selected' : ''; ?>>Élève/Étudiant</option>
            </select>
        </div>
        
        <div class="form-group" id="schoolField" style="display: none;">
            <label for="school_name">Nom de l'établissement *</label>
            <input type="text" id="school_name" name="school_name" class="form-control"
                   placeholder="Ex: École Primaire Centrale"
                   value="<?php echo isset($_POST['school_name']) ? htmlspecialchars($_POST['school_name']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Mot de passe *</label>
            <input type="password" id="password" name="password" class="form-control" required
                   placeholder="••••••••">
            <small style="color: #666; display: block; margin-top: 0.25rem;">
                Au moins 6 caractères
            </small>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirmer le mot de passe *</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                   placeholder="••••••••">
        </div>
        
        <div class="form-group">
            <label style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" required style="margin-top: 0.25rem;">
                <span style="font-size: 0.9rem;">
                    J'accepte les <a href="#" style="color: var(--primary-color);">conditions d'utilisation</a> 
                    et la <a href="#" style="color: var(--primary-color);">politique de confidentialité</a>
                </span>
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
            <i class="fas fa-user-plus"></i> Créer mon compte
        </button>
        
        <p style="text-align: center; margin-top: 1.5rem; color: #666;">
            Déjà un compte? <a href="login.php" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">Se connecter</a>
        </p>
    </form>
</div>

<script>
function toggleSchoolField() {
    const userType = document.getElementById('user_type').value;
    const schoolField = document.getElementById('schoolField');
    const schoolInput = document.getElementById('school_name');
    
    if (userType === 'teacher') {
        schoolField.style.display = 'block';
        schoolInput.required = true;
    } else {
        schoolField.style.display = 'none';
        schoolInput.required = false;
    }
}

// Initialiser au chargement
toggleSchoolField();
</script>

<?php require_once 'includes/footer.php'; ?>