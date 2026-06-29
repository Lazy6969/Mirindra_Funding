<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Connexion requise']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $project_id = intval($data['project_id'] ?? 0);
    $content = sanitizeInput($data['content'] ?? '');

    if ($project_id <= 0 || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Contenu vide']);
        exit;
    }

    $database = new Database();
    $pdo = $database->getConnection();
    
    try {
        $stmt = $pdo->prepare("INSERT INTO project_comments (project_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$project_id, $_SESSION['user_id'], $content]);

        echo json_encode([
            'success' => true,
            'comment' => [
                'user_name' => $_SESSION['user_name'],
                'content' => htmlspecialchars($content),
                'date' => 'À l\'instant'
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur technique']);
    }
}