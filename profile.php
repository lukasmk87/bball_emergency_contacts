<?php
require_once 'functions.php';
require_once 'templates/header.php';

// Überprüfen, ob Benutzer angemeldet ist
if (!isLoggedIn()) {
    redirect('index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// Verarbeitung des Profilformulars
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ungültige Sitzung. Bitte versuchen Sie es erneut.';
    } else {
        // Name aktualisieren
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            $errors[] = 'Name darf nicht leer sein.';
        }

        // E-Mail aktualisieren
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        } else {
            // Prüfen, ob E-Mail bereits von einem anderen Benutzer verwendet wird
            $existingUser = db()->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            if ($existingUser) {
                $errors[] = 'Diese E-Mail-Adresse wird bereits verwendet.';
            }
        }

        // Passwort aktualisieren (optional)
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $updatePassword = false;
        if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
            if (empty($currentPassword)) {
                $errors[] = 'Bitte geben Sie Ihr aktuelles Passwort ein.';
            } elseif (!password_verify($currentPassword, $user['password'])) {
                $errors[] = 'Das aktuelle Passwort ist nicht korrekt.';
            }

            if (empty($newPassword)) {
                $errors[] = 'Bitte geben Sie ein neues Passwort ein.';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Die Passwörter stimmen nicht überein.';
            }

            $updatePassword = true;
        }

        // Wenn keine Fehler vorliegen, Profil aktualisieren
        if (empty($errors)) {
            // Daten aktualisieren
            $params = [$name, $email, $userId];
            $sql = "UPDATE users SET name = ?, email = ? WHERE id = ?";

            if ($updatePassword) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
                $sql = "UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?";
                $params = [$name, $email, $hashedPassword, $userId];
            }

            db()->execute($sql, $params);

            // Sitzungsdaten aktualisieren
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;

            // Erfolg setzen
            setMessage('green', 'Ihr Profil wurde erfolgreich aktualisiert.');
            
            // Aktivität protokollieren
            logActivity($userId, 'profile_update', 'Benutzer hat sein Profil aktualisiert');
            
            // Umleiten, um POST-Wiederholung zu vermeiden
            redirect('profile.php');
            exit;
        }
    }
}

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-6">
    <div class="max-w-3xl mx-auto">
        <h2 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6">Mein Profil</h2>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 sm:mb-6">
                <ul class="list-disc pl-4">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <form method="POST" action="profile.php" class="space-y-4 sm:space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div>
                    <label for="name" class="block text-gray-700 mb-1 sm:mb-2 text-sm sm:text-base">Name</label>
                    <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" 
                        class="w-full p-2 sm:p-3 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>

                <div>
                    <label for="email" class="block text-gray-700 mb-1 sm:mb-2 text-sm sm:text-base">E-Mail</label>
                    <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" 
                        class="w-full p-2 sm:p-3 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>

                <hr class="my-4 sm:my-6 border-gray-600">
                
                <h3 class="text-lg font-semibold mb-3 sm:mb-4">Passwort ändern (optional)</h3>

                <div>
                    <label for="current_password" class="block text-gray-700 mb-1 sm:mb-2 text-sm sm:text-base">Aktuelles Passwort</label>
                    <input type="password" id="current_password" name="current_password" 
                        class="w-full p-2 sm:p-3 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label for="new_password" class="block text-gray-700 mb-1 sm:mb-2 text-sm sm:text-base">Neues Passwort</label>
                    <input type="password" id="new_password" name="new_password" 
                        class="w-full p-2 sm:p-3 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">Mindestens 8 Zeichen</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-gray-700 mb-1 sm:mb-2 text-sm sm:text-base">Passwort bestätigen</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                        class="w-full p-2 sm:p-3 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div class="flex flex-col sm:flex-row justify-between pt-4 gap-3">
                    <a href="dashboard.php" class="flex items-center justify-center bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 w-full sm:w-auto">
                        Zurück zum Dashboard
                    </a>
                    <button type="submit" class="flex items-center justify-center bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 w-full sm:w-auto">
                        Profil speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>