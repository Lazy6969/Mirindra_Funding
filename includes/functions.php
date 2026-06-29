<?php
/**
 * MIRINDRA FUNDING - FONCTIONS UTILITAIRES PHP
 * Fichier central pour toutes les fonctions helpers de l'application.
 * Compatible PHP 7.4+ | Sécurisé contre les re-déclarations
 */

// ============================================
// 🔒 SÉCURITÉ & SESSION
// ============================================
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        return strip_tags(trim($data));
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            redirect('login.php');
        }
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
}

// ============================================
// 🔄 REDIRECTION SÉCURISÉE
// ============================================
if (!function_exists('redirect')) {
    function redirect($url) {
        $baseUrl = rtrim(SITE_URL, '/') . '/';
        $target = $baseUrl . ltrim($url, '/');
        
        if (!headers_sent()) {
            header("Location: $target");
            exit();
        }
        // Fallback JS si les headers sont déjà envoyés
        echo "<script>window.location.replace('$target');</script>";
        exit();
    }
}

// ============================================
// 📅 DATE & TEMPS
// ============================================
if (!function_exists('getDaysRemaining')) {
    function getDaysRemaining($endDate) {
        if (empty($endDate)) return 0;
        try {
            $end = new DateTime($endDate);
            $now = new DateTime();
            $interval = $now->diff($end);
            return max(0, $interval->days);
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('formatDate')) {
    function formatDate($dateString) {
        if (empty($dateString)) return '';
        try {
            $date = new DateTime($dateString);
            return $date->format('d F Y');
        } catch (Exception $e) {
            return $dateString;
        }
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        try {
            $timestamp = strtotime($datetime);
            if ($timestamp === false) return $datetime;

            $diff = time() - $timestamp;
            $heureStr = date('H:i', $timestamp);
            
            if ($diff < 0) return "À venir";

            if ($diff < 60) return "À l'instant ($heureStr)";
            elseif ($diff < 3600) {
                $minutes = floor($diff / 60);
                return "Il y a $minutes minute" . ($minutes > 1 ? "s" : "") . " ($heureStr)";
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                return "Il y a $hours heure" . ($hours > 1 ? "s" : "") . " ($heureStr)";
            } elseif ($diff < 604800) {
                $days = floor($diff / 86400);
                return "Il y a $days jour" . ($days > 1 ? "s" : "") . " ($heureStr)";
            } else {
                return date('d/m/Y à H:i', $timestamp);
            }
        } catch (Exception $e) {
            return $datetime;
        }
    }
}

// ============================================
// 💰 FORMATAGE
// ============================================
if (!function_exists('formatAmount')) {
    function formatAmount($amount, $currency = 'Ar') {
        return number_format($amount, 0, ',', ' ') . ' ' . $currency;
    }
}

if (!function_exists('generateSlug')) {
    function generateSlug($string) {
        $string = strtolower(trim($string));
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
}

// ============================================
// 📁 UPLOAD DE FICHIERS
// ============================================
if (!function_exists('uploadImage')) {
    function uploadImage($file, $projectTitle) {
        $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../assets/images/uploads/';

        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                return ['success' => false, 'message' => 'Impossible de créer le dossier d\'upload.'];
            }
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($file['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Format non autorisé. Utilisez JPG, PNG, GIF ou WebP.'];
        }

        $maxSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'Fichier trop volumineux (max 5MB).'];
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = generateSlug($projectTitle) . '-' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'success' => true,
                'filepath' => 'assets/images/uploads/' . $filename
            ];
        }

        return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier.'];
    }
}

// ============================================
// 📁 UPLOAD DE FICHIERS (pour messages)
// ============================================
if (!function_exists('uploadMessageImage')) {
    function uploadMessageImage($file) {
        $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../assets/images/uploads/';
        $messageUploadDir = $uploadDir . 'messages/'; // Sous-répertoire pour les images de messages

        if (!file_exists($messageUploadDir)) {
            if (!mkdir($messageUploadDir, 0755, true) && !is_dir($messageUploadDir)) {
                return ['success' => false, 'message' => 'Impossible de créer le dossier d\'upload pour les messages.'];
            }
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($file['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Format d\'image non autorisé. Utilisez JPG, PNG, GIF ou WebP.'];
        }

        $maxSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'Fichier image trop volumineux (max 5MB).'];
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'msg-' . uniqid() . '-' . time() . '.' . $extension; // Nom de fichier unique
        $filepath = $messageUploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => true, 'filepath' => 'assets/images/uploads/messages/' . $filename]; // Chemin relatif à SITE_URL
        }

        return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier image.'];
    }
}

// ============================================
// 📊 STATISTIQUES BASE DE DONNÉES
// ============================================
if (!function_exists('getProjectStats')) {
    function getProjectStats($pdo) {
        $stats = [
            'total_projects' => 0,
            'total_raised'   => 0,
            'total_donors'   => 0,
            'active_projects'=> 0
        ];

        try {
            $stats['total_projects'] = (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('active', 'completed')")->fetchColumn();
            $stats['total_raised']   = (float) $pdo->query("SELECT COALESCE(SUM(current_amount), 0) FROM projects WHERE status IN ('active', 'completed')")->fetchColumn();
            $stats['total_donors']   = (int) $pdo->query("SELECT COUNT(DISTINCT donor_email) FROM donations WHERE payment_status = 'completed'")->fetchColumn();
            $stats['active_projects']= (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'active'")->fetchColumn();
        } catch (PDOException $e) {
            // Retourne les valeurs par défaut en cas d'erreur DB
            error_log("Erreur stats projets: " . $e->getMessage());
        }

        return $stats;
    }
}

// ============================================
// 📧 ENVOI D'EMAIL (BASIQUE)
// ============================================
if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $message) {
        $headers = implode("\r\n", [
            "From: noreply@mirindra-funding.mg",
            "Reply-To: contact@mirindra-funding.mg",
            "Content-Type: text/html; charset=UTF-8",
            "MIME-Version: 1.0"
        ]);

        return mail($to, $subject, $message, $headers);
    }
}

// ============================================
// 🔔 SYSTÈME DE NOTIFICATIONS
// ============================================
if (!function_exists('addNotification')) {
    function addNotification($pdo, $userId, $message, $link) {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $message, $link]);
    }
}

if (!function_exists('getUnreadNotificationsCount')) {
    function getUnreadNotificationsCount($pdo, $userId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('getNotifications')) {
    function getNotifications($pdo, $userId, $limit = 5) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :u_id ORDER BY created_at DESC LIMIT :lim");
            $stmt->bindValue(':u_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}

if (!function_exists('getAllNotifications')) {
    function getAllNotifications($pdo, $userId) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :u_id ORDER BY created_at DESC");
            $stmt->bindValue(':u_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}

if (!function_exists('getConnectionStatus')) {
    function getConnectionStatus($pdo, $user1, $user2) {
        $stmt = $pdo->prepare("SELECT * FROM user_connections WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        $stmt->execute([$user1, $user2, $user2, $user1]);
        return $stmt->fetch();
    }
}

if (!function_exists('getUnreadMessagesCount')) {
    function getUnreadMessagesCount($pdo, $userId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('getEstablishmentStats')) {
    function getEstablishmentStats($pdo, $search = '') {
        try {
            $sql = "SELECT 
                        p1.school_name,
                        COUNT(*) as total_projects,
                        SUM(p1.target_amount) as total_target,
                        SUM(p1.current_amount) as total_raised,
                        COUNT(CASE WHEN p1.status = 'active' THEN 1 END) as active_projects,
                        COUNT(CASE WHEN p1.status = 'completed' OR p1.current_amount >= p1.target_amount THEN 1 END) as funded_projects,
                        (SELECT COUNT(DISTINCT d.donor_email) 
                         FROM donations d 
                         JOIN projects p2 ON d.project_id = p2.id 
                         WHERE p2.school_name = p1.school_name AND d.payment_status = 'completed') as total_donors
                    FROM projects p1
                    WHERE p1.school_name IS NOT NULL AND p1.school_name != '' 
                    AND p1.status IN ('active', 'completed')";
            
            if (!empty($search)) {
                $sql .= " AND p1.school_name LIKE :search";
            }

            $sql .= " GROUP BY p1.school_name ORDER BY total_raised DESC";
            
            $stmt = $pdo->prepare($sql);
            if (!empty($search)) {
                $stmt->execute([':search' => "%$search%"]);
            } else {
                $stmt->execute();
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>