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
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? ''; // Passwort nicht sanitieren, da es gehasht wird
        
        // Validierung mit der verbesserten Funktion
        $rules = [
            'email' => ['required' => true, 'email' => true],
            'password' => ['required' => true]
        ];
        
        $errors = validateForm([
            'email' => $email,
            'password' => $password
        ], $rules);
        
        if (!empty($errors)) {
            $loginError = implode(' ', $errors);
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
<html lang="de" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="description" content="<?= APP_NAME ?> - Sichere Verwaltung von Notfallkontakten für Basketballvereine">
    <meta name="theme-color" content="#e65100">
    
    <title><?= APP_NAME ?> - Login</title>
    
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Application CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center page-transition">
    <div class="flex flex-col items-center justify-center min-h-screen p-4">
        <div class="bg-white p-6 sm:p-8 rounded-lg shadow-md w-full max-w-md">
            <div class="text-center mb-8">
                <i class="fas fa-basketball-ball text-orange-500 text-4xl sm:text-5xl" aria-hidden="true"></i>
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
                <div class="mb-4 bg-<?= $msg['type'] ?>-100 border-l-4 border-<?= $msg['type'] ?>-500 text-<?= $msg['type'] ?>-700 p-4" role="alert">
                    <p><?= e($msg['text']) ?></p>
                </div>
            <?php 
                endforeach;
            endif; 
            ?>
            
            <form method="POST" action="index.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div>
                    <label for="email" id="email_label" class="block text-gray-700">E-Mail</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="w-full p-2 border rounded mt-1 focus:outline-none focus:ring-2 focus:ring-orange-500" 
                           placeholder="ihre@email.de" 
                           required
                           aria-required="true"
                           aria-labelledby="email_label"
                           aria-invalid="<?= !empty($loginError) ? 'true' : 'false' ?>"
                           autocomplete="email">
                </div>
                
                <div>
                    <label for="password" id="password_label" class="block text-gray-700">Passwort</label>
                    <div class="relative">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="w-full p-2 border rounded mt-1 focus:outline-none focus:ring-2 focus:ring-orange-500" 
                               placeholder="Passwort" 
                               required
                               aria-required="true"
                               aria-labelledby="password_label"
                               aria-invalid="<?= !empty($loginError) ? 'true' : 'false' ?>"
                               autocomplete="current-password">
                        <button type="button" 
                                id="toggle-password" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5 mt-1"
                                aria-label="Passwort anzeigen/verstecken">
                            <i class="fas fa-eye text-gray-500" id="eye-icon" aria-hidden="true"></i>
                            <i class="fas fa-eye-slash text-gray-500 hidden" id="eye-slash-icon" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" 
                        class="w-full bg-orange-500 text-white p-2 rounded font-medium hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-opacity-50"
                        aria-label="Anmelden">
                    Anmelden
                </button>
            </form>
            
            <div class="mt-4 text-center text-sm text-gray-600">
                <a href="reset_password.php" 
                   class="text-orange-600 hover:text-orange-800 focus:outline-none focus:underline"
                   aria-label="Passwort zurücksetzen">
                    Passwort vergessen?
                </a>
                <p class="mt-2">Bei Problemen wenden Sie sich bitte an Ihren Administrator</p>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-600 text-center text-xs">
                <p class="text-gray-500 mb-2">
                    Mit der Anmeldung akzeptieren Sie unsere
                </p>
                <div class="flex justify-center space-x-3">
                    <a href="privacy_policy.php" 
                       class="text-orange-600 hover:text-orange-800 focus:outline-none focus:underline"
                       aria-label="Datenschutzerklärung öffnen">
                       Datenschutzerklärung
                    </a>
                    <a href="impressum.php" 
                       class="text-orange-600 hover:text-orange-800 focus:outline-none focus:underline"
                       aria-label="Impressum öffnen">
                       Impressum
                    </a>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-center text-sm text-gray-500">
            <?= APP_NAME ?> &copy; <?= date('Y') ?> | <i class="fas fa-lock text-xs" aria-hidden="true"></i> SSL-gesichert
        </div>
        
        <!-- Enhanced Accessibility Feature: Dark Mode Toggle -->
        <button id="theme-toggle" 
                class="mt-4 p-2 rounded-full bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400"
                aria-label="Dunkelmodus umschalten">
            <i class="fas fa-moon text-gray-700" id="dark-icon" aria-hidden="true"></i>
            <i class="fas fa-sun text-orange-500 hidden" id="light-icon" aria-hidden="true"></i>
        </button>
    </div>

    <script src="assets/js/dark-mode.js" defer></script>
    <script>
        // Toggle password visibility
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('toggle-password');
            const password = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeSlashIcon = document.getElementById('eye-slash-icon');
            
            if (togglePassword && password) {
                togglePassword.addEventListener('click', function() {
                    // Toggle password visibility
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    
                    // Toggle eye icons
                    eyeIcon.classList.toggle('hidden');
                    eyeSlashIcon.classList.toggle('hidden');
                    
                    // Set focus back to password field
                    password.focus();
                });
            }
            
            // Auto-focus to email field on page load
            const emailField = document.getElementById('email');
            if (emailField) {
                emailField.focus();
            }
        });
    </script>
</body>
</html>