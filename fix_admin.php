<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

$database = new Database();
$pdo = $database->getConnection();

$email = 'mirindra@gmail.com';
$new_password = 'mirindra123';
$first_name = 'LAZY';

try {
    // 1. Nettoyage radical des comptes admin ou email identique
    $pdo->prepare("DELETE FROM users WHERE user_type = 'admin' OR email = :email")->execute([':email' => $email]);

    // 2. Insertion propre avec hachage
    // On s'assure que is_active est bien à 1 (entier)
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password, first_name, last_name, user_type, is_active) 
        VALUES (:email, :pw, :fn, 'ADMIN', 'admin', 1)
    ");
    
    $stmt->execute([':email' => $email, ':pw' => $hashed_password, ':fn' => $first_name]);

    echo "<h3>Succès !</h3>";
    echo "Compte administrateur réinitialisé.<br>";
    echo "Email: <strong>$email</strong><br>Mot de passe: <strong>$new_password</strong><br><br>";
    echo "<a href='login.php'>Retourner à la connexion</a>";
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>