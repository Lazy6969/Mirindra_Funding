<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$project_id = intval($data['project_id'] ?? 0);
$amount = floatval($data['amount'] ?? 0);
$donor_name = sanitizeInput($data['donor_name'] ?? '');
$donor_email = sanitizeInput($data['donor_email'] ?? '');
$phone_number = sanitizeInput($data['phone_number'] ?? '');
$message = sanitizeInput($data['message'] ?? '');
$is_anonymous = isset($data['is_anonymous']) ? (bool)$data['is_anonymous'] : false;

$errors = [];

if ($project_id <= 0) $errors[] = "Projet invalide";
if ($amount <= 0) $errors[] = "Le montant doit être supérieur à 0";
if (empty($donor_name)) $errors[] = "Le nom est requis";
if (empty($donor_email) || !filter_var($donor_email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Vérifier que le projet existe et est actif
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id AND status = 'active'");
    $stmt->execute([':id' => $project_id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'Projet non trouvé ou non actif']);
        exit;
    }
    
    // Générer un jeton unique pour le reçu
    $receipt_token = bin2hex(random_bytes(16));
    
    // Créer le don
    $stmt = $pdo->prepare("
        INSERT INTO donations 
        (project_id, user_id, donor_name, donor_email, amount, phone_number, message, is_anonymous, payment_status, receipt_token)
        VALUES 
        (:project_id, :user_id, :donor_name, :donor_email, :amount, :phone_number, :message, :is_anonymous, 'completed', :receipt_token)
    ");
    
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    
    $stmt->execute([
        ':project_id' => $project_id,
        ':user_id' => $user_id,
        ':donor_name' => $donor_name,
        ':donor_email' => $donor_email,
        ':amount' => $amount,
        ':phone_number' => $phone_number,
        ':message' => $message,
        ':is_anonymous' => $is_anonymous,
        ':receipt_token' => $receipt_token
    ]);
    
    // Mettre à jour le montant collecté du projet
    $stmt = $pdo->prepare("
        UPDATE projects 
        SET current_amount = current_amount + :amount
        WHERE id = :id
    ");
    $stmt->execute([
        ':amount' => $amount,
        ':id' => $project_id
    ]);
    
    // --- Notifications ---
    $donor_display_name = $is_anonymous ? 'Un donateur anonyme' : $donor_name;
    $receipt_link = SITE_URL . "/donation-receipt.php?token=" . $receipt_token;

    // 1. Notification au donateur (si connecté)
    if ($user_id) {
        $donor_notif_msg = "Votre don de " . formatAmount($amount) . " pour le projet '" . $project['title'] . "' a été reçu avec succès. <a href='$receipt_link'>Voir votre reçu</a>.";
        addNotification($pdo, $user_id, $donor_notif_msg, $receipt_link);
    }

    // 2. Notification au propriétaire du projet
    $project_owner_notif_msg = "Nouveau don de " . formatAmount($amount) . " pour votre projet '" . $project['title'] . "' par " . $donor_display_name . ".";
    addNotification($pdo, $project['user_id'], $project_owner_notif_msg, "project-detail.php?id=" . $project_id);

    // 3. Notification à l'administrateur
    $admin_notif_msg = "Nouveau don de " . formatAmount($amount) . " pour le projet '" . $project['title'] . "' par " . $donor_display_name . ".";
    $admin_id = $pdo->query("SELECT id FROM users WHERE user_type = 'admin' LIMIT 1")->fetchColumn();
    if ($admin_id) addNotification($pdo, $admin_id, $admin_notif_msg, "dashboard.php");

    echo json_encode(['success' => true, 'message' => 'Don enregistré avec succès', 'receipt_link' => $receipt_link]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>