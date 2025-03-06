<?php
// Datenbankverbindungskonfiguration
define('DB_HOST', 'localhost');     // Host der MariaDB-Datenbank
define('DB_NAME', '#######');   // Name der Datenbank
define('DB_USER', '########');     // Benutzername für die Datenbank
define('DB_PASS', '##########');    // Passwort für den Datenbankbenutzer

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin');

// Sitzungsdauer
define('SESSION_DURATION', 3600); // 1 Stunde

// Anwendungs-URL - passen Sie diese Ihrem Server an
define('APP_URL', 'https://notfall.kotowicz.info');

// Globale Einstellungen für die Anwendung
define('APP_NAME', 'Basketball Notfallkontakte');
define('APP_VERSION', '1.0.0');

// Debug-Modus (auf false setzen in der Produktion)
define('DEBUG_MODE', true);

// Sicherheitseinstellungen
define('HASH_COST', 10); // Kosten für die Passwort-Hashing-Funktion

// Maximale Anzahl von Login-Versuchen
define('MAX_LOGIN_ATTEMPTS', 5);

// Fehlerbehandlung
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}
