<?php
require_once 'functions.php';

// Sitzung starten
startSecureSession();

// Überprüfen, ob Benutzer bereits angemeldet ist
if (isLoggedIn()) {
    redirect('dashboard.php');
    exit;
}

// Fehler- und Erfolgsmeldungen
$error = '';
$success = '';

// Verarbeitung des Formulars (Schritt 1 - E-Mail-Adresse eingeben)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültige Sitzung. Bitte versuchen Sie es erneut.';
    } else {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        } else {
            // Benutzer mit dieser E-Mail-Adresse suchen
            $user = db()->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
            
            if (!$user) {
                // Auch wenn der Benutzer nicht existiert, geben wir eine allgemeine Erfolgsmeldung aus
                // (Sicherheitsmaßnahme, damit keine E-Mail-Adressen abgegriffen werden können)
                $success = 'Falls ein Konto mit dieser E-Mail-Adresse existiert, wurden Anweisungen zum Zurücksetzen des Passworts gesendet.';
            } else {
                // Gültigen Reset-Token erstellen
                $token = bin2hex(random_bytes(32));
                $expires = time() + 3600; // 1 Stunde gültig
                
                // Token in der Datenbank speichern
                $updated = db()->execute(
                    "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?",
                    [$token, date('Y-m-d H:i:s', $expires), $user['id']]
                );
                
                if ($updated) {
                    // In einer produktiven Umgebung würde hier eine E-Mail mit dem Link zum Zurücksetzen gesendet
                    // Da wir keine E-Mail-Funktionalität haben, zeigen wir den Link direkt an
                    $resetLink = APP_URL . '/reset_password.php?token=' . $token;
                    
                    // Aktivität protokollieren
                    logActivity($user['id'], 'password_reset_request', 'Benutzer hat Passwort-Reset angefordert');
                    
                    $success = 'Anweisungen zum Zurücksetzen des Passworts wurden gesendet.<br><br>';
                    $success .= 'Da wir in dieser Demo-Anwendung keine E-Mails versenden, hier der direkte Link:<br>';
                    $success .= '<a href="' . $resetLink . '" class="text-orange-600 underline">' . $resetLink . '</a>';
                } else {
                    $error = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.';
                }
            }
        }
    }
}

// Verarbeitung des Formulars (Schritt 2 - Neues Passwort setzen)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültige Sitzung. Bitte versuchen Sie es erneut.';
    } else {
        $token = $_POST['token'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Token-Validierung
        $user = db()->fetchOne(
            "SELECT * FROM users WHERE reset_token = ? AND reset_expires > ?",
            [$token, date('Y-m-d H:i:s')]
        );
        
        if (!$user) {
            $error = 'Ungültiger oder abgelaufener Token. Bitte fordern Sie einen neuen Link an.';
        } elseif (empty($newPassword)) {
            $error = 'Bitte geben Sie ein neues Passwort ein.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Die Passwörter stimmen nicht überein.';
        } else {
            // Passwort-Hash erstellen
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
            
            // Passwort aktualisieren und Token zurücksetzen
            $updated = db()->execute(
                "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
                [$hashedPassword, $user['id']]
            );
            
            if ($updated) {
                // Aktivität protokollieren
                logActivity($user['id'], 'password_reset_complete', 'Benutzer hat Passwort zurückgesetzt');
                
                setMessage('green', 'Ihr Passwort wurde erfolgreich zurückgesetzt. Sie können sich jetzt anmelden.');
                redirect('index.php');
                exit;
            } else {
                $error = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.';
            }
        }
    }
}

// Token aus URL überprüfen (für Schritt 2)
$token = $_GET['token'] ?? '';
$validToken = false;

if (!empty($token)) {
    // Überprüfen, ob der Token existiert und noch gültig ist
    $user = db()->fetchOne(
        "SELECT * FROM users WHERE reset_token = ? AND reset_expires > ?",
        [$token, date('Y-m-d H:i:s')]
    );
    
    $validToken = ($user !== false);
}

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Passwort zurücksetzen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Dark Mode Grundeinstellungen */
        body {
            background-color: #121212 !important;
            color: #e0e0e0 !important;
        }

        /* Dark Mode für Hauptcontainer */
        .bg-white, .bg-gray-50, .bg-gray-100 {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
        }

        /* Dark Mode für Text */
        .text-gray-600, .text-gray-700, .text-gray-800, .text-gray-900 {
            color: #b0b0b0 !important;
        }

        /* Dark Mode für Formulare */
        input, select, textarea {
            background-color: #262626 !important;
            color: #ffffff !important;
            border-color: #444 !important;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #e65100 !important;
        }

        ::placeholder {
            color: #808080 !important;
            opacity: 1;
        }

        /* Dark Mode für Meldungen */
        .bg-red-100 {
            background-color: #4a1d1a !important;
            border-color: #e53e3e !important;
        }

        .text-red-700 {
            color: #feb2b2 !important;
        }

        .bg-green-100 {
            background-color: #1b3a2a !important;
            border-color: #38a169 !important;
        }

        .text-green-700 {
            color: #7ae2b0 !important;
        }

        /* Dark Mode für Buttons */
        .bg-orange-500 {
            background-color: #e65100 !important;
        }

        .bg-orange-500:hover {
            background-color: #ff6d00 !important;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex flex-col items-center justify-center min-h-screen p-4">
        <div class="bg-white p-6 sm:p-8 rounded-lg shadow-md w-full max-w-md">
            <div class="text-center mb-6">
                <i class="fas fa-basketball-ball text-orange-500 text-4xl sm:text-5xl"></i>
                <h1 class="text-xl sm:text-2xl font-bold mt-4"><?= APP_NAME ?></h1>
                <?php if ($validToken): ?>
                    <p class="text-gray-600 text-sm sm:text-base">Neues Passwort setzen</p>
                <?php else: ?>
                    <p class="text-gray-600 text-sm sm:text-base">Passwort zurücksetzen</p>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p><?= e($error) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p><?= $success ?></p>
                </div>
            <?php elseif ($validToken): ?>
                <!-- Formular 2: Neues Passwort setzen -->
                <form method="POST" action="reset_password.php" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    
                    <div>
                        <label for="new_password" class="block text-gray-700 mb-1 text-sm sm:text-base">Neues Passwort</label>
                        <input type="password" id="new_password" name="new_password" 
                               class="w-full p-2 sm:p-3 border rounded mt-1 focus:outline-none focus:ring-2 focus:ring-orange-500" 
                               placeholder="Mindestens 8 Zeichen" required>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-gray-700 mb-1 text-sm sm:text-base">Passwort bestätigen</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="w-full p-2 sm:p-3 border rounded mt-1 focus:outline-none focus:ring-2 focus:ring-orange-500" 
                               placeholder="Passwort wiederholen" required>
                    </div>
                    
                    <button type="submit" class="w-full bg-orange-500 text-white p-2 sm:p-3 rounded font-medium hover:bg-orange-600 text-sm sm:text-base">
                        Passwort zurücksetzen
                    </button>
                </form>
            <?php else: ?>
                <!-- Formular 1: E-Mail-Adresse eingeben -->
                <form method="POST" action="reset_password.php" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div>
                        <label for="email" class="block text-gray-700 mb-1 text-sm sm:text-base">E-Mail-Adresse</label>
                        <input type="email" id="email" name="email" 
                               class="w-full p-2 sm:p-3 border rounded mt-1 focus:outline-none focus:ring-2 focus:ring-orange-500" 
                               placeholder="ihre@email.de" required>
                    </div>
                    
                    <button type="submit" class="w-full bg-orange-500 text-white p-2 sm:p-3 rounded font-medium hover:bg-orange-600 text-sm sm:text-base">
                        Link zum Zurücksetzen anfordern
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="mt-6 text-center">
                <a href="index.php" class="text-orange-600 hover:text-orange-800 text-sm sm:text-base">
                    <i class="fas fa-arrow-left mr-2"></i>Zurück zur Anmeldung
                </a>
            </div>
        </div>
        
        <div class="mt-4 text-center text-xs sm:text-sm text-gray-500">
            <?= APP_NAME ?> &copy; <?= date('Y') ?> | <i class="fas fa-lock text-xs"></i> SSL-gesichert
        </div>
    </div>

    <script src="assets/js/scripts.js"></script>
</body>
</html>