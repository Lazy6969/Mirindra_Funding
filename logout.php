<?php
require_once 'includes/config.php';

// 1. Vider la session proprement
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 2. Redirection absolue (évite les 404 de chemin relatif)
$redirectUrl = rtrim(SITE_URL, '/') . '/login.php';

if (!headers_sent()) {
    header("Location: $redirectUrl");
    exit();
}
// Fallback JS si des espaces/echo ont déjà été envoyés
echo "<script>window.location.replace('$redirectUrl');</script>";
exit();
?>