<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

requireLogin();

$data = json_decode(file_get_contents('php://input'), true);

$title = sanitizeInput($data['title'] ?? '');
$description = sanitizeInput($data['description'] ?? '');
$category = sanitizeInput($data['category'] ?? '');
$target_amount = floatval($data['target_amount'] ?? 0);
$start_date = sanitizeInput($data['start_date'] ?? '');
$end_date = sanitizeInput($data['end_date'] ?? '');
$school_name = sanitizeInput($data['school_name'] ?? '');
$class_level = sanitizeInput($data['class_level'] ?? '');
$number_of_students = intval($data['number_of_students'] ?? 0);

$errors = [];

if (empty($title)) $errors[] = "Le titre est requis";
if (empty($description)) $errors[] = "La description est requise";
if ($target_amount <= 0) $errors[] = "Le montant objectif doit être supérieur à 0";
if (empty($start_date) || empty($end_date)) $errors[] = "Les dates sont requises";

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("
        INSERT INTO projects 
        (user_id, title, description, category, target_amount, start_date, end_date, 
         school_name, class_level, number_of_students, status)
        VALUES 
        (:user_id, :title, :description, :category, :target_amount, :start_date, :end_date,
         :school_name, :class_level, :number_of_students, 'pending')
    ");
    
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':title' => $title,
        ':description' => $description,
        ':category' => $category,
        ':target_amount' => $target_amount,
        ':start_date' => $start_date,
        ':end_date' => $end_date,
        ':school_name' => $school_name,
        ':class_level' => $class_level,
        ':number_of_students' => $number_of_students
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Projet créé avec succès',
        'project_id' => $pdo->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>