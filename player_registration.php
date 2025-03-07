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
    // Spielerdaten verarbeiten
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $jerseyNumber = trim($_POST['jersey_number'] ?? '');
    $position = trim($_POST['position'] ?? '');
    
    // Notfallkontaktdaten
    $contactName = trim($_POST['contact_name'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    
    // Prüfen, ob Datenschutzerklärung akzeptiert wurde
    $privacyAccepted = isset($_POST['privacy_accepted']) && $_POST['privacy_accepted'] === 'yes';
    
    // Validierung
    if (empty($firstName)) {
        $errors[] = 'Vorname ist erforderlich.';
    }
    
    if (empty($lastName)) {
        $errors[] = 'Nachname ist erforderlich.';
    }
    
    if (empty($contactName)) {
        $errors[] = 'Name des Notfallkontakts ist erforderlich.';
    }
    
    if (empty($phoneNumber)) {
        $errors[] = 'Telefonnummer des Notfallkontakts ist erforderlich.';
    }
    
    if (empty($relationship)) {
        $errors[] = 'Beziehung zum Notfallkontakt ist erforderlich.';
    }
    
    if (!$privacyAccepted) {
        $errors[] = 'Sie müssen der Datenschutzerklärung zustimmen, um fortzufahren.';
    }
    
    // Wenn keine Fehler vorhanden sind, Daten speichern
    if (empty($errors)) {
        try {
            // Prüfen, ob der Spieler bereits existiert (basierend auf Name und Team)
            $existingPlayer = db()->fetchOne("
                SELECT id FROM players 
                WHERE team_id = ? AND first_name = ? AND last_name = ?
            ", [$teamInfo['id'], $firstName, $lastName]);
            
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
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.';
            if (DEBUG_MODE) {
                $errors[] = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spielerregistrierung - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Dark Mode Grundeinstellungen */
        body {
            background-color: #121212;
            color: #e0e0e0;
        }

        .bg-white {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }

        /* Dark Mode für Formulare */
        input, select, textarea {
            background-color: #262626;
            color: #ffffff;
            border-color: #444;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #e65100;
        }

        ::placeholder {
            color: #808080;
            opacity: 1;
        }

        /* Dark Mode für Text */
        .text-gray-600, .text-gray-700, .text-gray-800, .text-gray-900 {
            color: #b0b0b0;
        }

        /* Dark Mode für Buttons */
        .bg-gray-300, .bg-gray-400 {
            background-color: #383838;
            color: #e0e0e0;
        }

        .bg-gray-300:hover, .bg-gray-400:hover {
            background-color: #444444;
        }

        .bg-orange-500 {
            background-color: #e65100;
        }

        .bg-orange-500:hover {
            background-color: #ff6d00;
        }

        /* Dark Mode für Meldungen */
        .bg-red-100 {
            background-color: #4a1d1a;
            border-color: #e53e3e;
        }

        .text-red-700 {
            color: #feb2b2;
        }

        .bg-green-100 {
            background-color: #1b3a2a;
            border-color: #38a169;
        }

        .text-green-700 {
            color: #7ae2b0;
        }
        
        /* Mobile optimizations */
        @media (max-width: 640px) {
            button, a, input[type="submit"] {
                min-height: 44px; /* Apple's recommended minimum touch target size */
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen py-6">
    <div class="container mx-auto px-4 max-w-lg">
        <!-- Header -->
        <div class="text-center mb-6">
            <i class="fas fa-basketball-ball text-orange-500 text-4xl sm:text-5xl"></i>
            <h1 class="text-xl sm:text-2xl font-bold mt-4"><?= APP_NAME ?></h1>
            <h2 class="text-lg sm:text-xl mt-2">Spielerregistrierung für <?= e($teamInfo['name']) ?></h2>
            <p class="text-gray-600 text-sm mt-1"><?= e($teamInfo['category']) ?></p>
        </div>
        
        <?php if ($success): ?>
            <!-- Erfolgsanzeige -->
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <h3 class="font-bold">Registrierung erfolgreich!</h3>
                <p class="mt-2">Vielen Dank für Ihre Registrierung. Ihre Daten wurden erfolgreich gespeichert.</p>
                
                <div class="mt-4">
                    <button onclick="resetForm()" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 w-full">
                        Weiteren Spieler registrieren
                    </button>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
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
                <h3 class="text-lg font-bold mb-4 border-b pb-2">Spielerdaten</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-gray-700 mb-1 text-sm">Vorname *</label>
                        <input type="text" id="first_name" name="first_name" value="<?= isset($firstName) ? e($firstName) : '' ?>" 
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    
                    <div>
                        <label for="last_name" class="block text-gray-700 mb-1 text-sm">Nachname *</label>
                        <input type="text" id="last_name" name="last_name" value="<?= isset($lastName) ? e($lastName) : '' ?>" 
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    
                    <div>
                        <label for="jersey_number" class="block text-gray-700 mb-1 text-sm">Trikotnummer</label>
                        <input type="text" id="jersey_number" name="jersey_number" value="<?= isset($jerseyNumber) ? e($jerseyNumber) : '' ?>" 
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    
                    <div>
                        <label for="position" class="block text-gray-700 mb-1 text-sm">Position</label>
                        <select id="position" name="position" 
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
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
                <h3 class="text-lg font-bold mb-4 border-b pb-2">Notfallkontakt</h3>
                
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="contact_name" class="block text-gray-700 mb-1 text-sm">Name des Kontakts *</label>
                        <input type="text" id="contact_name" name="contact_name" value="<?= isset($contactName) ? e($contactName) : '' ?>" 
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    
                    <div>
                        <label for="phone_number" class="block text-gray-700 mb-1 text-sm">Telefonnummer *</label>
                        <input type="tel" id="phone_number" name="phone_number" value="<?= isset($phoneNumber) ? e($phoneNumber) : '' ?>" 
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                    </div>
                    
                    <div>
                        <label for="relationship" class="block text-gray-700 mb-1 text-sm">Beziehung zum Spieler *</label>
                        <select id="relationship" name="relationship" 
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
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
                    <div class="flex items-center h-5">
                        <input type="checkbox" id="privacy_accepted" name="privacy_accepted" value="yes" 
                               class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded" required>
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="privacy_accepted" class="font-medium text-gray-600">Datenschutzerklärung *</label>
                        <p class="text-gray-500 mt-1">Ich habe die <a href="privacy_policy.php" target="_blank" class="text-orange-600 hover:text-orange-800 underline">Datenschutzerklärung</a> gelesen und bin damit einverstanden, dass meine Daten gemäß dieser Datenschutzerklärung verarbeitet werden.</p>
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <p class="text-sm text-gray-600 mb-4">* Pflichtfelder</p>
                <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 w-full">
                    Registrieren
                </button>
            </div>
        </form>
        
        <div class="mt-6 text-center text-sm text-gray-600">
            <div class="flex justify-center space-x-4">
                <a href="privacy_policy.php" class="text-orange-600 hover:text-orange-800">Datenschutzerklärung</a>
                <a href="impressum.php" class="text-orange-600 hover:text-orange-800">Impressum</a>
            </div>
            <div class="mt-2">
                &copy; <?= date('Y') ?> <?= APP_NAME ?>
            </div>
        </div>
    </div>
    
    <script>
        function resetForm() {
            document.getElementById('registrationForm').classList.remove('hidden');
            document.getElementById('registrationForm').reset();
            document.querySelector('.bg-green-100').classList.add('hidden');
            window.scrollTo(0, 0);
        }
    </script>
</body>
</html>