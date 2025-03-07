<?php
require_once 'functions.php';

// Sitzung starten
startSecureSession();

// Überprüfen, ob Benutzer bereits angemeldet ist
if (isLoggedIn()) {
    redirect('dashboard.php');
    exit;
}

// Verarbeitung des Login-Formulars
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $loginError = 'Ungültige Sitzung. Bitte versuchen Sie es erneut.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $loginError = 'Bitte geben Sie E-Mail und Passwort ein.';
        } else {
            $user = authenticateUser($email, $password);
            
            if ($user) {
                // Erfolgreiche Anmeldung
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Aktivität protokollieren
                logActivity($user['id'], 'login', 'Benutzer hat sich angemeldet');
                
                // Umleitung zum Dashboard
                redirect('dashboard.php');
                exit;
            } else {
                $loginError = 'Ungültige E-Mail oder Passwort.';
                
                // Verzögerung bei fehlgeschlagener Anmeldung zum Schutz vor Brute-Force-Angriffen
                sleep(1);
            }
        }
    }
}

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Login</title>
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

        /* Dark Mode für Links */
        a.text-orange-600 {
            color: #ff9800 !important;
        }

        a.text-orange-600:hover {
            color: #ffb74d !important;
        }

        /* Schatten im Dark Mode anpassen */
        .shadow-md {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.25) !important;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex flex-col items-center justify-center min-h-screen p-4">
        <div class="bg-white p-6 sm:p-8 rounded-lg shadow-md w-full max-w-md">
            <div class="text-center mb-8">
                <i class="fas fa-basketball-ball text-orange-500 text-4xl sm:text-5xl"></i>
                <h1 class="text-2xl font-bold mt-4"><?= APP_NAME ?></h1>
                <p class="text-gray-600">Bitte melden Sie sich an</p>
            </div>
            
            <?php if ($loginError): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p><?= e($loginError) ?></p>
                </div>
            <?php endif; ?>
            
            <?php 
            // Nachrichten abrufen und anzeigen
            $messages = getMessages();
            if (!empty($messages)): 
                foreach ($messages as $msg):
            ?>
                <div class="mb-4 bg-<?= $msg['type'] ?>-100 border-l-4 border-<?= $msg['type'] ?>-500 text-<?= $msg['type'] ?>-700 p-4">
                    <p><?= e($msg['text']) ?></p>
                </div>
            <?php 
                endforeach;
            endif; 
            ?>
            
            <form method="POST" action="index.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div>
                    <label for="email" class="block text-gray-700">E-Mail</label>
                    <input type="email" id="email" name="email" class="w-full p-2 border rounded mt-1 focus:outline-none focus:ring-2 focus:ring-orange-500 text-gray-800" placeholder="ihre@email.de" required>
                </div>
                
                <div>
                    <label for="password" class="block text-gray-700">Passwort</label>
                    <input type="password" id="password" name="password" class="w-full p-2 border rounded mt-1 focus:outline-none focus:ring-2 focus:ring-orange-500 text-gray-800" placeholder="Passwort" required>
                </div>
                
                <button type="submit" class="w-full bg-orange-500 text-white p-2 rounded font-medium hover:bg-orange-600">Anmelden</button>
            </form>
            
            <div class="mt-4 text-center text-sm text-gray-600">
                <a href="reset_password.php" class="text-orange-600 hover:text-orange-800">
                    Passwort vergessen?
                </a>
                <p class="mt-2">Bei Problemen wenden Sie sich bitte an Ihren Administrator</p>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-600 text-center text-xs">
                <p class="text-gray-500 mb-2">
                    Mit der Anmeldung akzeptieren Sie unsere
                </p>
                <div class="flex justify-center space-x-3">
                    <a href="privacy_policy.php" class="text-orange-600 hover:text-orange-800">Datenschutzerklärung</a>
                    <a href="impressum.php" class="text-orange-600 hover:text-orange-800">Impressum</a>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-center text-sm text-gray-500">
            <?= APP_NAME ?> &copy; <?= date('Y') ?> | <i class="fas fa-lock text-xs"></i> SSL-gesichert
        </div>
    </div>

    <script src="assets/js/scripts.js"></script>
</body>
</html>