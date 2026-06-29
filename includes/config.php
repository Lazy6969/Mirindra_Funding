<?php
// Configuration générale de Mirindra Funding
define('SITE_NAME', 'Mirindra Funding');
define('SITE_URL', 'http://localhost/mirindra-funding');
define('UPLOAD_DIR', __DIR__ . '/../assets/images/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Configuration de la session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fuseau horaire
date_default_timezone_set('Indian/Antananarivo');

// Fonction de sécurité de base (échappement HTML)
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return strip_tags(trim($data));
}
?>