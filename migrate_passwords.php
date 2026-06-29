<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

/**
 * Script de migration unique pour hacher les mots de passe existants
 */

$database = new Database();
$pdo = $database->getConnection();

try {
    // 1. Récupérer tous les utilisateurs
    $stmt = $pdo->query("SELECT id, email, password FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = 0;
    $already_hashed = 0;

    foreach ($users as $user) {
        $current_pw = $user['password'];
        
        // Vérification robuste : est-ce un hash valide ?
        $info = password_get_info($current_pw);
        
        // Si l'algorithme est 0 (indéfini), c'est du texte clair
        if ($info['algo'] === 0) {
            $hashed_pw = password_hash($current_pw, PASSWORD_DEFAULT);
            
            // Mettre à jour la base de données
            $update = $pdo->prepare("UPDATE users SET password = :pw WHERE id = :id");
            $update->execute([
                ':pw' => $hashed_pw,
                ':id' => $user['id']
            ]);
            $count++;
        } else {
            $already_hashed++;
        }
    }
    
    echo "<h3>Migration terminée !</h3>";
    echo "Comptes convertis au format sécurisé : <strong>$count</strong><br>";
    echo "Comptes déjà sécurisés (ignorés) : <strong>$already_hashed</strong><br><br>";
    echo "<a href='login.php'>Retourner à la connexion</a>";
} catch (PDOException $e) {
    echo "Erreur lors de la migration : " . $e->getMessage();
}
?>