<?php
require_once 'functions.php';

// Sitzung starten
startSecureSession();

// Wenn Benutzer angemeldet ist, ausloggen
if (isLoggedIn()) {
    // Aktivität protokollieren
    logActivity($_SESSION['user_id'], 'logout', 'Benutzer hat sich abgemeldet');
    
    // Session zerstören
    session_unset();
    session_destroy();
    
    // Cookie löschen
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Umleitung zur Login-Seite
redirect('index.php');
exit;