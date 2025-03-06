<?php
require_once 'db.php';

// Sitzung starten mit sicheren Einstellungen
function startSecureSession() {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => SESSION_DURATION,
        'path' => '/',
        'domain' => $_SERVER['SERVER_NAME'],
        'secure' => true,    // Nur über HTTPS
        'httponly' => true,  // Nicht per JavaScript zugreifbar
        'samesite' => 'Lax' // Schutz gegen CSRF
    ]);
    
    session_name('basketball_notfallkontakte');
    session_start();
    
    // Sitzung regenerieren alle 30 Minuten
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Sitzung nach 30 Minuten regenerieren
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Benutzerauthentifizierung
function authenticateUser($email, $password) {
    $user = db()->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
    
    if ($user && password_verify($password, $user['password'])) {
        // Passwort neu hashen, falls Hashkosten aktualisiert wurden
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT, ['cost' => HASH_COST])) {
            $newHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
            db()->execute("UPDATE users SET password = ? WHERE id = ?", [$newHash, $user['id']]);
        }
        
        return $user;
    }
    
    return false;
}

// Sitzungsauthentifizierung überprüfen
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Benutzerrolle prüfen
function hasRole($role) {
    if (!isLoggedIn()) return false;
    
    if ($role === 'any') return true;
    
    $user = db()->fetchOne("SELECT role FROM users WHERE id = ?", [$_SESSION['user_id']]);
    
    if ($role === 'admin') {
        return $user['role'] === 'admin';
    } else if ($role === 'trainer') {
        return $user['role'] === 'admin' || $user['role'] === 'trainer';
    } else if ($role === 'manager') {
        return $user['role'] === 'admin' || $user['role'] === 'trainer' || $user['role'] === 'manager';
    }
    
    return false;
}

// CSRF-Token generieren
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF-Token validieren
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Text sicher ausgeben (XSS-Schutz)
function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// Umleitung
function redirect($path) {
    // Überprüfen, ob bereits Inhalte gesendet wurden
    if (!headers_sent()) {
        header("Location: " . APP_URL . "/" . $path);
        exit;
    } else {
        // Alternative Methode, wenn Header bereits gesendet wurden
        echo '<script>window.location.href="' . APP_URL . '/' . $path . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . APP_URL . '/' . $path . '"></noscript>';
        echo 'Falls keine automatische Weiterleitung erfolgt, klicken Sie bitte <a href="' . APP_URL . '/' . $path . '">hier</a>.';
        exit;
    }
}

// Teams abrufen, die für einen Benutzer zugänglich sind
function getTeamsForUser($userId) {
    if (hasRole('admin')) {
        // Admins haben Zugriff auf alle Teams
        return db()->fetchAll("SELECT * FROM teams ORDER BY name");
    } else {
        // Trainer und Manager haben nur Zugriff auf zugewiesene Teams
        return db()->fetchAll("
            SELECT t.* 
            FROM teams t
            JOIN user_team ut ON t.id = ut.team_id
            WHERE ut.user_id = ?
            ORDER BY t.name
        ", [$userId]);
    }
}

// Spieler für ein Team abrufen
function getPlayersForTeam($teamId) {
    return db()->fetchAll("
        SELECT p.*, 
               (SELECT COUNT(*) FROM emergency_contacts WHERE player_id = p.id) AS has_emergency_contact
        FROM players p
        WHERE p.team_id = ?
        ORDER BY p.last_name, p.first_name
    ", [$teamId]);
}

// Notfallkontakte für einen Spieler abrufen
function getEmergencyContactsForPlayer($playerId) {
    return db()->fetchAll("
        SELECT * FROM emergency_contacts
        WHERE player_id = ?
        ORDER BY id
    ", [$playerId]);
}

// Formatiert Nachrichten für den Benutzer
function setMessage($type, $message) {
    $_SESSION['messages'][] = ['type' => $type, 'text' => $message];
}

// Holt alle Nachrichten und löscht sie aus der Sitzung
function getMessages() {
    $messages = $_SESSION['messages'] ?? [];
    unset($_SESSION['messages']);
    return $messages;
}

// Log-Funktion für wichtige Ereignisse
function logActivity($userId, $action, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    db()->insert(
        "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
        [$userId, $action, $details, $ip, $userAgent]
    );
}

/**
 * Erzeugt oder gibt einen bestehenden Zugangsschlüssel für ein Team zurück
 *
 * @param int $teamId Die Team-ID
 * @param int $userId Die Benutzer-ID des Trainers
 * @param bool $forceNew Erzwingt die Erstellung eines neuen Schlüssels
 * @return string Der Zugangsschlüssel
 */
function getOrCreateTeamAccessKey($teamId, $userId, $forceNew = false) {
    // Prüfen, ob bereits ein Zugriffsschlüssel für dieses Team existiert
    $accessKey = db()->fetchOne(
        "SELECT access_key FROM team_access WHERE team_id = ? AND is_active = 1",
        [$teamId]
    );
    
    if ($accessKey && !$forceNew) {
        return $accessKey['access_key'];
    }
    
    // Alte Schlüssel deaktivieren, wenn ein neuer erzwungen wird
    if ($forceNew) {
        invalidateTeamAccessKey($teamId);
    }
    
    // Neuen eindeutigen Zugriffsschlüssel generieren
    $newKey = bin2hex(random_bytes(16));
    
    // Schlüssel in der Datenbank speichern
    db()->insert(
        "INSERT INTO team_access (team_id, user_id, access_key, created_at, expires_at, is_active) 
         VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1)",
        [$teamId, $userId, $newKey]
    );
    
    return $newKey;
}

/**
 * Deaktiviert alle aktiven Zugangsschlüssel für ein Team
 *
 * @param int $teamId Die Team-ID
 * @return bool True, wenn erfolgreich
 */
function invalidateTeamAccessKey($teamId) {
    return db()->execute(
        "UPDATE team_access SET is_active = 0 WHERE team_id = ? AND is_active = 1",
        [$teamId]
    );
}

/**
 * Ruft Team-Informationen basierend auf einem Zugriffschlüssel ab
 *
 * @param string $accessKey Der Zugriffsschlüssel
 * @return array|false Team-Zugriffsinformationen oder false bei ungültigem Schlüssel
 */
function getTeamAccessByKey($accessKey) {
    if (empty($accessKey)) {
        return false;
    }
    
    // Überprüfen, ob der Schlüssel gültig und aktiv ist
    $teamAccess = db()->fetchOne(
        "SELECT team_id, user_id FROM team_access 
         WHERE access_key = ? AND is_active = 1 AND expires_at > NOW()",
        [$accessKey]
    );
    
    return $teamAccess;
}

/**
 * Generiert eine PDF-Datei mit QR-Code für den Teamzugriff
 *
 * @param int $teamId Die Team-ID
 * @param string $teamName Der Teamname
 * @param string $qrCodeUrl Die URL für den QR-Code
 */
function generateTeamAccessPDF($teamId, $teamName, $qrCodeUrl) {
    // Wir verwenden die TCPDF-Bibliothek, falls diese nicht verfügbar ist,
    // können wir nur HTML anzeigen
    if (!class_exists('TCPDF')) {
        // Alternativ eine druckbare HTML-Seite anzeigen
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>QR-Code für <?= htmlspecialchars($teamName) ?></title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
            <style>
                @media print {
                    .no-print { display: none !important; }
                    @page { margin: 0.5cm; }
                    body { font-family: Arial, sans-serif; }
                }
            </style>
        </head>
        <body class="bg-white p-8">
            <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
                <div class="no-print mb-4 flex justify-end">
                    <button onclick="window.print()" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                        <i class="fas fa-print mr-2"></i>Drucken
                    </button>
                </div>
                
                <div class="text-center">
                    <h1 class="text-2xl font-bold mb-4">Notfallkontakte</h1>
                    <h2 class="text-xl mb-6"><?= htmlspecialchars($teamName) ?></h2>
                    
                    <div class="mb-6">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?= urlencode($qrCodeUrl) ?>" 
                             alt="QR-Code für <?= htmlspecialchars($teamName) ?>" 
                             class="mx-auto border p-2" />
                    </div>
                    
                    <p class="mb-4">Scannen Sie diesen QR-Code, um auf die Notfallkontakte zuzugreifen</p>
                    <p class="text-sm text-gray-600">
                        Direkter Link: <br>
                        <a href="<?= $qrCodeUrl ?>" class="text-orange-600 break-all"><?= htmlspecialchars($qrCodeUrl) ?></a>
                    </p>
                    
                    <div class="mt-8 text-sm text-gray-600">
                        <p>Erstellt am: <?= date('d.m.Y') ?></p>
                        <p>Gültig bis: <?= date('d.m.Y', strtotime('+1 year')) ?></p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Falls TCPDF verfügbar ist, hier den Code für die PDF-Generierung
    // Dies wäre ein zusätzlicher Code, der die TCPDF-Bibliothek verwendet
    // Die Implementierung hängt davon ab, ob TCPDF auf dem Server installiert ist
}

/**
 * Legt die benötigten Tabellen für die QR-Code Funktionalität an, falls sie noch nicht existieren
 */
function ensureQRCodeTablesExist() {
    // Tabelle für Team-Zugriff erstellen, falls sie noch nicht existiert
    $sql = "CREATE TABLE IF NOT EXISTS team_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_id INT NOT NULL,
        user_id INT NOT NULL,
        access_key VARCHAR(64) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        is_active TINYINT(1) DEFAULT 1,
        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (access_key),
        INDEX (is_active)
    )";
    
    db()->execute($sql);
}

// Diese Funktion aufrufen, um sicherzustellen, dass die Tabellen existieren
ensureQRCodeTablesExist();