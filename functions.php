<?php
require_once 'db.php';

// App Constants
define('APP_ROOT', dirname(__FILE__));
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', false);
}

// Default session duration (30 days)
if (!defined('SESSION_DURATION')) {
    define('SESSION_DURATION', 86400 * 30);
}

// Default hash cost
if (!defined('HASH_COST')) {
    define('HASH_COST', 12);
}

// Sitzung starten mit sicheren Einstellungen
function startSecureSession() {
    // Set session cookie parameters for security
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    
    // Enable secure cookies in production
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    // Set SameSite attribute to Lax for CSRF protection
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => SESSION_DURATION,
        'path' => '/',
        'domain' => $_SERVER['SERVER_NAME'],
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Use a custom session name
    session_name('basketball_notfallkontakte');
    
    // Start the session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        // Regenerate session ID every 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Benutzerauthentifizierung
function authenticateUser($email, $password) {
    if (empty($email) || empty($password)) {
        return false;
    }
    
    try {
        $user = db()->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Passwort neu hashen, falls Hashkosten aktualisiert wurden
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT, ['cost' => HASH_COST])) {
                $newHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
                db()->execute("UPDATE users SET password = ? WHERE id = ?", [$newHash, $user['id']]);
            }
            
            return $user;
        }
    } catch (Exception $e) {
        handleError($e, 'Fehler bei der Benutzerauthentifizierung.');
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
    
    try {
        $user = db()->fetchOne("SELECT role FROM users WHERE id = ?", [$_SESSION['user_id']]);
        
        if (!$user) return false;
        
        if ($role === 'admin') {
            return $user['role'] === 'admin';
        } else if ($role === 'trainer') {
            return $user['role'] === 'admin' || $user['role'] === 'trainer';
        } else if ($role === 'manager') {
            return $user['role'] === 'admin' || $user['role'] === 'trainer' || $user['role'] === 'manager';
        }
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Prüfen der Benutzerrolle.');
        return false;
    }
    
    return false;
}

// CSRF-Token generieren
function generateCSRFToken() {
    // Use a cryptographically secure random value
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 3600) { // Refresh after 1 hour
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

// CSRF-Token validieren
function validateCSRFToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Text sicher ausgeben (XSS-Schutz)
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
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
    if (empty($userId)) return [];
    
    try {
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
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Abrufen der Teams.');
        return [];
    }
}

// Spieler für ein Team abrufen
function getPlayersForTeam($teamId) {
    if (empty($teamId)) return [];
    
    try {
        return db()->fetchAll("
            SELECT p.*, 
                   (SELECT COUNT(*) FROM emergency_contacts WHERE player_id = p.id) AS has_emergency_contact
            FROM players p
            WHERE p.team_id = ?
            ORDER BY p.last_name, p.first_name
        ", [$teamId]);
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Abrufen der Spieler.');
        return [];
    }
}

// Notfallkontakte für einen Spieler abrufen
function getEmergencyContactsForPlayer($playerId) {
    if (empty($playerId)) return [];
    
    try {
        return db()->fetchAll("
            SELECT * FROM emergency_contacts
            WHERE player_id = ?
            ORDER BY id
        ", [$playerId]);
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Abrufen der Notfallkontakte.');
        return [];
    }
}

// Notfallkontakte für mehrere Spieler abrufen (Batch)
function getEmergencyContactsForPlayers($playerIds) {
    if (empty($playerIds)) {
        return [];
    }
    
    try {
        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        $contacts = db()->fetchAll("
            SELECT * FROM emergency_contacts
            WHERE player_id IN ($placeholders)
            ORDER BY player_id, id
        ", $playerIds);
        
        $contactsByPlayer = [];
        foreach ($contacts as $contact) {
            $contactsByPlayer[$contact['player_id']][] = $contact;
        }
        
        return $contactsByPlayer;
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Batch-Abrufen der Notfallkontakte.');
        return [];
    }
}

// Formatiert Nachrichten für den Benutzer
function setMessage($type, $message) {
    if (!isset($_SESSION['messages'])) {
        $_SESSION['messages'] = [];
    }
    
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
    if (empty($userId) || empty($action)) return false;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        db()->insert(
            "INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
            [$userId, $action, $details, $ip, $userAgent]
        );
        
        return true;
    } catch (Exception $e) {
        // Silent fail for logging errors
        if (DEBUG_MODE) {
            error_log('Error logging activity: ' . $e->getMessage());
        }
        
        return false;
    }
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
    if (empty($teamId) || empty($userId)) return '';
    
    try {
        // Check if a team access key already exists
        $result = db()->fetchOne(
            "SELECT access_key FROM team_access WHERE team_id = ? AND is_active = 1",
            [$teamId]
        );
        
        $accessKey = $result && isset($result['access_key']) ? $result['access_key'] : null;
        
        if ($accessKey && !$forceNew) {
            return $accessKey;
        }
        
        // Force regenerate a new key if requested
        if ($forceNew) {
            invalidateTeamAccessKey($teamId);
        }
        
        // Generate a new cryptographically secure random key
        $newKey = bin2hex(random_bytes(16));
        
        // Store the key in the database
        db()->insert(
            "INSERT INTO team_access (team_id, user_id, access_key, created_at, expires_at, is_active) 
             VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1)",
            [$teamId, $userId, $newKey]
        );
        
        return $newKey;
    } catch (Exception $e) {
        handleError($e, 'Fehler bei der Erstellung des Zugangsschlüssels.');
        return '';
    }
}

/**
 * Deaktiviert alle aktiven Zugangsschlüssel für ein Team
 */
function invalidateTeamAccessKey($teamId) {
    if (empty($teamId)) return false;
    
    try {
        return db()->execute(
            "UPDATE team_access SET is_active = 0 WHERE team_id = ? AND is_active = 1",
            [$teamId]
        );
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Deaktivieren des Zugangsschlüssels.');
        return false;
    }
}

/**
 * Ruft Team-Informationen basierend auf einem Zugriffschlüssel ab
 */
function getTeamAccessByKey($accessKey) {
    if (empty($accessKey)) {
        return false;
    }
    
    try {
        // Überprüfen, ob der Schlüssel gültig und aktiv ist
        $teamAccess = db()->fetchOne(
            "SELECT team_id, user_id FROM team_access 
             WHERE access_key = ? AND is_active = 1 AND expires_at > NOW()",
            [$accessKey]
        );
        
        return $teamAccess;
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Abrufen des Team-Zugriffs.');
        return false;
    }
}

/**
 * Generiert eine PDF-Datei mit QR-Code für den Teamzugriff
 */
function generateTeamAccessPDF($teamId, $teamName, $qrCodeUrl) {
    // Set proper content type header
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    
    // If TCPDF library is not available, fall back to HTML
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>QR-Code für <?= e($teamName) ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
        <link rel="stylesheet" href="assets/css/styles.css">
        <link rel="stylesheet" href="assets/css/dark-mode.css">
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
                <h2 class="text-xl mb-6"><?= e($teamName) ?></h2>
                
                <div class="mb-6">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?= urlencode($qrCodeUrl) ?>" 
                         alt="QR-Code für <?= e($teamName) ?>" 
                         class="mx-auto border p-2" />
                </div>
                
                <p class="mb-4">Scannen Sie diesen QR-Code, um auf die Notfallkontakte zuzugreifen</p>
                <p class="text-sm text-gray-600">
                    Direkter Link: <br>
                    <a href="<?= $qrCodeUrl ?>" class="text-orange-600 break-all"><?= e($qrCodeUrl) ?></a>
                </p>
                
                <div class="mt-8 text-sm text-gray-600">
                    <p>Erstellt am: <?= date('d.m.Y') ?></p>
                    <p>Gültig bis: <?= date('d.m.Y', strtotime('+1 year')) ?></p>
                </div>
            </div>
        </div>
        
        <script src="assets/js/dark-mode.js"></script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Legt die benötigten Tabellen für die QR-Code Funktionalität an, falls sie noch nicht existieren
 */
function ensureQRCodeTablesExist() {
    try {
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
        return true;
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Erstellen der QR-Code-Tabellen.');
        return false;
    }
}

/**
 * Ensures that the player registration tables exist
 */
function ensurePlayerRegistrationTablesExist() {
    try {
        // Tabelle für Spielerregistrierung erstellen, falls sie noch nicht existiert
        $sql = "CREATE TABLE IF NOT EXISTS player_registration_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NOT NULL,
            user_id INT NOT NULL,
            registration_key VARCHAR(64) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (registration_key),
            INDEX (is_active)
        )";
        
        db()->execute($sql);
        return true;
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Erstellen der Spielerregistrierungs-Tabellen.');
        return false;
    }
}

/**
 * Generiert oder gibt einen bestehenden Registrierungslink für ein Team zurück
 */
function getOrCreatePlayerRegistrationKey($teamId, $userId, $forceNew = false) {
    if (empty($teamId) || empty($userId)) return '';
    
    try {
        // Prüfen, ob bereits ein Registrierungslink für dieses Team existiert
        $result = db()->fetchOne(
            "SELECT registration_key FROM player_registration_links WHERE team_id = ? AND is_active = 1",
            [$teamId]
        );
        
        $registrationKey = $result && isset($result['registration_key']) ? $result['registration_key'] : null;
        
        if ($registrationKey && !$forceNew) {
            return $registrationKey;
        }
        
        // Alte Schlüssel deaktivieren, wenn ein neuer erzwungen wird
        if ($forceNew) {
            invalidatePlayerRegistrationKey($teamId);
        }
        
        // Neuen eindeutigen Registrierungsschlüssel generieren
        $newKey = bin2hex(random_bytes(16));
        
        // Schlüssel in der Datenbank speichern
        db()->insert(
            "INSERT INTO player_registration_links (team_id, user_id, registration_key, created_at, expires_at, is_active) 
             VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1)",
            [$teamId, $userId, $newKey]
        );
        
        return $newKey;
    } catch (Exception $e) {
        handleError($e, 'Fehler bei der Erstellung des Registrierungsschlüssels.');
        return '';
    }
}

/**
 * Deaktiviert alle aktiven Registrierungslinks für ein Team
 */
function invalidatePlayerRegistrationKey($teamId) {
    if (empty($teamId)) return false;
    
    try {
        return db()->execute(
            "UPDATE player_registration_links SET is_active = 0 WHERE team_id = ? AND is_active = 1",
            [$teamId]
        );
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Deaktivieren des Registrierungsschlüssels.');
        return false;
    }
}

/**
 * Ruft Team-Informationen basierend auf einem Registrierungsschlüssel ab
 */
function getTeamByRegistrationKey($registrationKey) {
    if (empty($registrationKey)) {
        return false;
    }
    
    try {
        // Überprüfen, ob der Schlüssel gültig und aktiv ist
        $teamInfo = db()->fetchOne(
            "SELECT t.id, t.name, t.category, prl.user_id 
             FROM player_registration_links prl
             JOIN teams t ON prl.team_id = t.id
             WHERE prl.registration_key = ? AND prl.is_active = 1 AND prl.expires_at > NOW()",
            [$registrationKey]
        );
        
        return $teamInfo;
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Abrufen der Team-Informationen.');
        return false;
    }
}

/**
 * Comprehensive form validation
 */
function validateForm($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $fieldRules) {
        $value = isset($data[$field]) ? $data[$field] : null;
        
        foreach ($fieldRules as $rule => $ruleValue) {
            switch ($rule) {
                case 'required':
                    if ($ruleValue && empty($value) && $value !== '0') {
                        $errors[] = ucfirst($field) . ' ist erforderlich.';
                    }
                    break;
                case 'email':
                    if ($ruleValue && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = 'Geben Sie eine gültige E-Mail-Adresse ein.';
                    }
                    break;
                case 'min_length':
                    if (!empty($value) && strlen($value) < $ruleValue) {
                        $errors[] = ucfirst($field) . " muss mindestens $ruleValue Zeichen lang sein.";
                    }
                    break;
                case 'match':
                    if (!empty($value) && $value !== ($data[$ruleValue] ?? '')) {
                        $errors[] = "Die Felder stimmen nicht überein.";
                    }
                    break;
                case 'unique':
                    if (!empty($value)) {
                        $table = $ruleValue['table'];
                        $column = $ruleValue['column'];
                        $exceptId = $ruleValue['except_id'] ?? 0;
                        
                        $query = "SELECT id FROM $table WHERE $column = ?";
                        $params = [$value];
                        
                        if ($exceptId) {
                            $query .= " AND id != ?";
                            $params[] = $exceptId;
                        }
                        
                        $existing = db()->fetchOne($query, $params);
                        if ($existing) {
                            $errors[] = ucfirst($field) . " wird bereits verwendet.";
                        }
                    }
                    break;
                case 'in_array':
                    if (!empty($value) && !in_array($value, $ruleValue)) {
                        $errors[] = ucfirst($field) . " enthält einen ungültigen Wert.";
                    }
                    break;
            }
        }
    }
    
    return $errors;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
    } else {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}

/**
 * Improved error handling
 */
function handleError($e, $userMessage = null) {
    // Create logs directory if it doesn't exist
    $logDir = APP_ROOT . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log the error
    $errorLog = $logDir . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $errorMessage = $timestamp . " - " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
    
    // Write to error log
    error_log($errorMessage, 3, $errorLog);
    
    // Set user message
    if (DEBUG_MODE) {
        setMessage('red', $e->getMessage());
    } else {
        setMessage('red', $userMessage ?? 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
    }
}

/**
 * Debug helper function
 */
function debug($variable, $exit = true) {
    echo '<pre>';
    var_dump($variable);
    echo '</pre>';
    
    if ($exit) {
        exit;
    }
}

// Ensure necessary tables exist
ensureQRCodeTablesExist();
ensurePlayerRegistrationTablesExist();