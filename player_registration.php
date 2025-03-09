<?php
require_once 'functions.php';

// Sitzung starten
startSecureSession();

// Registrierungsschlüssel aus der URL abrufen
$registrationKey = $_GET['key'] ?? '';

// Team-Informationen basierend auf dem Schlüssel abrufen
$teamInfo = getTeamByRegistrationKey($registrationKey);

if (!$teamInfo) {
    // Ungültiger oder abgelaufener Schlüssel
    require_once 'templates/error_page.php';
    exit;
}

// Formularverarbeitung
$errors = [];
$success = false;
$playerId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sicheres Erfassen der Formulardaten
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $jerseyNumber = sanitizeInput($_POST['jersey_number'] ?? '');
    $position = sanitizeInput($_POST['position'] ?? '');
    $contactName = sanitizeInput($_POST['contact_name'] ?? '');
    $phoneNumber = sanitizeInput($_POST['phone_number'] ?? '');
    $relationship = sanitizeInput($_POST['relationship'] ?? '');
    $privacyAccepted = isset($_POST['privacy_accepted']) && $_POST['privacy_accepted'] === 'yes';
    
    // Formularvalidierung mit der neuen Validierungsfunktion
    $rules = [
        'first_name' => ['required' => true],
        'last_name' => ['required' => true],
        'contact_name' => ['required' => true],
        'phone_number' => ['required' => true],
        'relationship' => ['required' => true]
    ];
    
    $errors = validateForm([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'contact_name' => $contactName,
        'phone_number' => $phoneNumber,
        'relationship' => $relationship
    ], $rules);
    
    // Überprüfen der Datenschutzerklärung
    if (!$privacyAccepted) {
        $errors[] = 'Sie müssen der Datenschutzerklärung zustimmen, um fortzufahren.';
    }
    
    // Wenn keine Fehler vorhanden sind, Daten speichern
    if (empty($errors)) {
        try {
            db()->getConnection()->beginTransaction();
            
            // Prüfen, ob der Spieler bereits existiert (basierend auf Name und Team)
            // Verbesserte Spieleridentifikation mit mehreren Feldern
            $existingPlayer = db()->fetchOne("
                SELECT id FROM players 
                WHERE team_id = ? AND first_name = ? AND last_name = ? AND 
                    (jersey_number = ? OR jersey_number IS NULL OR jersey_number = '')
            ", [$teamInfo['id'], $firstName, $lastName, $jerseyNumber]);
            
            if ($existingPlayer) {
                $playerId = $existingPlayer['id'];
                
                // Spielerdaten aktualisieren
                db()->execute("
                    UPDATE players 
                    SET jersey_number = ?, position = ? 
                    WHERE id = ?
                ", [$jerseyNumber, $position, $playerId]);
            } else {
                // Neuen Spieler anlegen
                $playerId = db()->insert("
                    INSERT INTO players (team_id, first_name, last_name, jersey_number, position)
                    VALUES (?, ?, ?, ?, ?)
                ", [$teamInfo['id'], $firstName, $lastName, $jerseyNumber, $position]);
            }
            
            // Notfallkontakt hinzufügen
            if ($playerId) {
                $contactId = db()->insert("
                    INSERT INTO emergency_contacts (player_id, contact_name, phone_number, relationship)
                    VALUES (?, ?, ?, ?)
                ", [$playerId, $contactName, $phoneNumber, $relationship]);
                
                if ($contactId) {
                    $success = true;
                    logActivity($teamInfo['user_id'], 'player_self_register', "Spieler hat sich selbst registriert: $firstName $lastName (Team: {$teamInfo['name']})");
                    
                    // Commit the transaction
                    db()->getConnection()->commit();
                } else {
                    // Rollback on error
                    db()->getConnection()->rollBack();
                    $errors[] = 'Notfallkontakt konnte nicht hinzugefügt werden.';
                }
            } else {
                // Rollback on error
                db()->getConnection()->rollBack();
                $errors[] = 'Spieler konnte nicht hinzugefügt werden.';
            }
        } catch (Exception $e) {
            // Rollback on error
            if (db()->getConnection()->inTransaction()) {
                db()->getConnection()->rollBack();
            }
            
            // Handle error
            handleError($e, 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="description" content="Spielerregistrierung für <?= e($teamInfo['name'] ?? '') ?> - <?= APP_NAME ?>">
    <meta name="theme-color" content="#e65100">
    <title>Spielerregistrierung - <?= APP_NAME ?></title>
    
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Application CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
</head>
<body class="bg-gray-100 min-h-screen page-transition">
    <div class="flex flex-col min-h-screen">
        <div class="flex-grow">
            <div class="container mx-auto px-2 sm:px-4 py-4 sm:py-6 max-w-lg">
                <!-- Header -->
                <div class="text-center mb-6">
                    <i class="fas fa-basketball-ball text-orange-500 text-4xl sm:text-5xl" aria-hidden="true"></i>
                    <h1 class="text-xl sm:text-2xl font-bold mt-4"><?= APP_NAME ?></h1>
                    <h2 class="text-lg sm:text-xl mt-2">Spielerregistrierung für <?= e($teamInfo['name'] ?? '') ?></h2>
                    <p class="text-gray-600 text-sm mt-1"><?= e($teamInfo['category'] ?? '') ?></p>
                </div>
                
                <?php if ($success): ?>
                    <!-- Erfolgsanzeige -->
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded" role="alert">
                        <h3 class="font-bold">Registrierung erfolgreich!</h3>
                        <p class="mt-2">Vielen Dank für Ihre Registrierung. Ihre Daten wurden erfolgreich gespeichert.</p>
                        
                        <div class="mt-4">
                            <button onclick="resetForm()" 
                                    class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 w-full flex items-center justify-center"
                                    aria-label="Weiteren Spieler registrieren">
                                <i class="fas fa-user-plus mr-2" aria-hidden="true"></i> Weiteren Spieler registrieren
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                        <h3 class="font-bold">Bitte korrigieren Sie folgende Fehler:</h3>
                        <ul class="list-disc pl-5 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Registrierungsformular -->
                <form method="POST" action="player_registration.php?key=<?= urlencode($registrationKey) ?>" id="registrationForm" <?= $success ? 'class="hidden"' : '' ?>>
                    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
                        <h3 class="text-lg font-bold mb-4 border-b pb-2" id="playerDataHeading">Spielerdaten</h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-gray-700 mb-1 text-sm sm:text-base">Vorname *</label>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       value="<?= isset($firstName) ? e($firstName) : '' ?>" 
                                       class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" 
                                       required
                                       aria-required="true"
                                       aria-labelledby="playerDataHeading"
                                       aria-invalid="<?= isset($errors['first_name']) ? 'true' : 'false' ?>">
                            </div>
                            
                            <div>
                                <label for="last_name" class="block text-gray-700 mb-1 text-sm sm:text-base">Nachname *</label>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       value="<?= isset($lastName) ? e($lastName) : '' ?>" 
                                       class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" 
                                       required
                                       aria-required="true"
                                       aria-labelledby="playerDataHeading"
                                       aria-invalid="<?= isset($errors['last_name']) ? 'true' : 'false' ?>">
                            </div>
                            
                            <div>
                                <label for="jersey_number" class="block text-gray-700 mb-1 text-sm sm:text-base">Trikotnummer</label>
                                <input type="text" 
                                       id="jersey_number" 
                                       name="jersey_number" 
                                       value="<?= isset($jerseyNumber) ? e($jerseyNumber) : '' ?>" 
                                       class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500"
                                       aria-labelledby="playerDataHeading"
                                       inputmode="numeric"
                                       pattern="[0-9]*">
                            </div>
                            
                            <div>
                                <label for="position" class="block text-gray-700 mb-1 text-sm sm:text-base">Position</label>
                                <select id="position" 
                                        name="position" 
                                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500"
                                        aria-labelledby="playerDataHeading">
                                    <option value="" <?= !isset($position) || empty($position) ? 'selected' : '' ?>>Keine Position</option>
                                    <option value="Guard" <?= isset($position) && $position === 'Guard' ? 'selected' : '' ?>>Guard</option>
                                    <option value="Forward" <?= isset($position) && $position === 'Forward' ? 'selected' : '' ?>>Forward</option>
                                    <option value="Center" <?= isset($position) && $position === 'Center' ? 'selected' : '' ?>>Center</option>
                                    <option value="Point Guard" <?= isset($position) && $position === 'Point Guard' ? 'selected' : '' ?>>Point Guard</option>
                                    <option value="Shooting Guard" <?= isset($position) && $position === 'Shooting Guard' ? 'selected' : '' ?>>Shooting Guard</option>
                                    <option value="Small Forward" <?= isset($position) && $position === 'Small Forward' ? 'selected' : '' ?>>Small Forward</option>
                                    <option value="Power Forward" <?= isset($position) && $position === 'Power Forward' ? 'selected' : '' ?>>Power Forward</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
                        <h3 class="text-lg font-bold mb-4 border-b pb-2" id="emergencyContactHeading">Notfallkontakt</h3>
                        
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="contact_name" class="block text-gray-700 mb-1 text-sm sm:text-base">Name des Kontakts *</label>
                                <input type="text" 
                                       id="contact_name" 
                                       name="contact_name" 
                                       value="<?= isset($contactName) ? e($contactName) : '' ?>" 
                                       class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" 
                                       required
                                       aria-required="true"
                                       aria-labelledby="emergencyContactHeading"
                                       aria-invalid="<?= isset($errors['contact_name']) ? 'true' : 'false' ?>">
                            </div>
                            
                            <div>
                                <label for="phone_number" class="block text-gray-700 mb-1 text-sm sm:text-base">Telefonnummer *</label>
                                <input type="tel" 
                                       id="phone_number" 
                                       name="phone_number" 
                                       value="<?= isset($phoneNumber) ? e($phoneNumber) : '' ?>" 
                                       class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" 
                                       required
                                       aria-required="true"
                                       aria-labelledby="emergencyContactHeading"
                                       aria-invalid="<?= isset($errors['phone_number']) ? 'true' : 'false' ?>"
                                       inputmode="tel"
                                       autocomplete="tel">
                            </div>
                            
                            <div>
                                <label for="relationship" class="block text-gray-700 mb-1 text-sm sm:text-base">Beziehung zum Spieler *</label>
                                <select id="relationship" 
                                        name="relationship" 
                                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" 
                                        required
                                        aria-required="true"
                                        aria-labelledby="emergencyContactHeading"
                                        aria-invalid="<?= isset($errors['relationship']) ? 'true' : 'false' ?>">
                                    <option value="" <?= !isset($relationship) || empty($relationship) ? 'selected' : '' ?>>Bitte wählen</option>
                                    <option value="Elternteil" <?= isset($relationship) && $relationship === 'Elternteil' ? 'selected' : '' ?>>Elternteil</option>
                                    <option value="Mutter" <?= isset($relationship) && $relationship === 'Mutter' ? 'selected' : '' ?>>Mutter</option>
                                    <option value="Vater" <?= isset($relationship) && $relationship === 'Vater' ? 'selected' : '' ?>>Vater</option>
                                    <option value="Geschwister" <?= isset($relationship) && $relationship === 'Geschwister' ? 'selected' : '' ?>>Geschwister</option>
                                    <option value="Großeltern" <?= isset($relationship) && $relationship === 'Großeltern' ? 'selected' : '' ?>>Großeltern</option>
                                    <option value="Partner" <?= isset($relationship) && $relationship === 'Partner' ? 'selected' : '' ?>>Partner</option>
                                    <option value="Freund" <?= isset($relationship) && $relationship === 'Freund' ? 'selected' : '' ?>>Freund</option>
                                    <option value="Sonstige" <?= isset($relationship) && $relationship === 'Sonstige' ? 'selected' : '' ?>>Sonstige</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Datenschutzerklärung Zustimmung -->
                    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
                        <div class="flex items-start">
                            <div class="flex items-center h-5 mt-1">
                                <input type="checkbox" 
                                       id="privacy_accepted" 
                                       name="privacy_accepted" 
                                       value="yes" 
                                       class="h-5 w-5 text-orange-500 focus:ring-orange-400 border-gray-300 rounded" 
                                       required
                                       aria-required="true"
                                       aria-invalid="<?= isset($errors['privacy_accepted']) ? 'true' : 'false' ?>">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="privacy_accepted" class="font-medium text-gray-600">Datenschutzerklärung *</label>
                                <p class="text-gray-500 mt-1">Ich habe die <a href="privacy_policy.php" target="_blank" class="text-orange-600 hover:text-orange-800 underline">Datenschutzerklärung</a> gelesen und bin damit einverstanden, dass meine Daten gemäß dieser Datenschutzerklärung verarbeitet werden.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-4">* Pflichtfelder</p>
                        <button type="submit" 
                                class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 w-full flex items-center justify-center"
                                aria-label="Spieler registrieren">
                            <i class="fas fa-user-plus mr-2" aria-hidden="true"></i> Registrieren
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="bg-gray-100 border-t mt-auto py-4">
            <div class="container mx-auto px-2 sm:px-4">
                <div class="flex flex-col sm:flex-row justify-center items-center text-gray-500 text-xs sm:text-sm space-y-2 sm:space-y-0">
                    <div class="text-center">
                        &copy; <?= date('Y') ?> <?= APP_NAME ?> | <i class="fas fa-lock text-xs" aria-hidden="true"></i> SSL-gesichert
                    </div>
                    <div class="sm:ml-4 flex space-x-3">
                        <a href="privacy_policy.php" class="text-orange-600 hover:text-orange-800 transition">Datenschutzerklärung</a>
                        <a href="impressum.php" class="text-orange-600 hover:text-orange-800 transition">Impressum</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Focus first field on page load
            const firstInput = document.getElementById('first_name');
            if (firstInput) {
                setTimeout(function() { 
                    firstInput.focus();
                }, 100);
            }
            
            // Scroll to first error on form submission if there are errors
            const errors = document.querySelector('.bg-red-100');
            if (errors) {
                errors.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
        
        function resetForm() {
            const form = document.getElementById('registrationForm');
            const successMessage = document.querySelector('.bg-green-100');
            
            if (form && successMessage) {
                form.classList.remove('hidden');
                form.reset();
                successMessage.classList.add('hidden');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
                // Focus first field
                setTimeout(function() { 
                    document.getElementById('first_name').focus();
                }, 100);
            }
        }
        
        // Enhanced form validation
        document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
            const phoneNumber = document.getElementById('phone_number')?.value || '';
            
            // Simple phone number format validation
            if (phoneNumber && !/^[+\d\s()-]{6,20}$/.test(phoneNumber)) {
                e.preventDefault();
                alert('Bitte geben Sie eine gültige Telefonnummer ein.');
                document.getElementById('phone_number').focus();
                return false;
            }
            
            return true;
        });
    </script>
    
    <!-- Include dark mode script for theme switching -->
    <script src="assets/js/dark-mode.js" defer></script>
</body>
</html>