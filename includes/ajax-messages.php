<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $receiver_id = intval($data['receiver_id'] ?? 0);
    $content = sanitizeInput($data['content'] ?? '');

    if ($receiver_id <= 0 || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $receiver_id, $content]);
        
        echo json_encode([
            'success' => true, 
            'data' => [
                'content' => htmlspecialchars($content),
                'created_at' => 'À l\'instant',
                'sender_id' => $user_id
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $with_id = intval($_GET['with'] ?? 0);
    $last_id = intval($_GET['last_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT id, sender_id, content, created_at, is_read
        FROM messages 
        WHERE id > :last_id 
        AND ((sender_id = :uid AND receiver_id = :with) OR (sender_id = :with AND receiver_id = :uid))
        ORDER BY created_at ASC
    ");
    $stmt->execute([':last_id' => $last_id, ':uid' => $user_id, ':with' => $with_id]);
    echo json_encode(['success' => true, 'messages' => $stmt->fetchAll()]);
}