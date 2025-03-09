<?php
require_once 'functions.php';

// Sitzung starten
startSecureSession();

// Zugriffsschlüssel aus der URL abrufen
$accessKey = $_GET['key'] ?? '';

// Team-Zugriffsinformationen basierend auf dem Schlüssel abrufen
$teamAccess = getTeamAccessByKey($accessKey);

if (!$teamAccess) {
    // Ungültiger oder abgelaufener Schlüssel
    require_once 'templates/error_page.php';
    exit;
}

// Team-ID und Benutzer-ID aus dem Zugriff abrufen
$teamId = $teamAccess['team_id'] ?? 0;
$userId = $teamAccess['user_id'] ?? 0;

if (!$teamId || !$userId) {
    // Ungültige Zugangsdaten
    require_once 'templates/error_page.php';
    exit;
}

try {
    // Team-Informationen abrufen
    $team = db()->fetchOne("SELECT * FROM teams WHERE id = ?", [$teamId]);
    
    if (!$team) {
        // Team nicht gefunden
        require_once 'templates/error_page.php';
        exit;
    }
    
    // Spieler für das Team abrufen
    $players = db()->fetchAll("
        SELECT p.id, p.first_name, p.last_name, p.jersey_number, p.position
        FROM players p
        WHERE p.team_id = ?
        ORDER BY p.last_name, p.first_name
    ", [$teamId]);
    
    // Get all player IDs
    $playerIds = array_column($players, 'id');
    
    // Optimize: Fetch all contacts in a single query
    $allContacts = [];
    if (!empty($playerIds)) {
        $allContacts = db()->fetchAll("
            SELECT player_id, contact_name, phone_number, relationship
            FROM emergency_contacts
            WHERE player_id IN (" . implode(',', $playerIds) . ")
            ORDER BY player_id, id
        ");
    }
    
    // Organize contacts by player ID
    $contactsByPlayer = [];
    foreach ($allContacts as $contact) {
        $contactsByPlayer[$contact['player_id']][] = $contact;
    }
    
    // Build final data structure
    $playerContactsData = [];
    foreach ($players as $player) {
        $playerContactsData[$player['id']] = [
            'player' => $player,
            'contacts' => $contactsByPlayer[$player['id']] ?? []
        ];
    }
    
    // Aktivität protokollieren
    logActivity($userId, 'emergency_access', "Notfallzugriff auf Team $teamId über QR-Code");
} catch (Exception $e) {
    handleError($e, 'Fehler beim Laden der Notfallkontakte.');
    require_once 'templates/error_page.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="description" content="Notfallkontakte für <?= e($team['name'] ?? 'Team') ?> - <?= APP_NAME ?>">
    <meta name="theme-color" content="#e65100">
    <meta name="robots" content="noindex, nofollow">
    
    <title>Notfallkontakte: <?= e($team['name'] ?? '') ?></title>
    
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Application CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
</head>
<body class="bg-gray-100 min-h-screen page-transition">
    <div class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-orange-600"><?= APP_NAME ?></h1>
                <h2 class="text-xl font-semibold">Notfallkontakte: <?= e($team['name'] ?? '') ?></h2>
                <p class="text-gray-600 mt-2">Stand: <?= date('d.m.Y H:i') ?> Uhr</p>
            </div>
            
            <?php if (empty($players)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
                    <p>Für dieses Team sind noch keine Spieler oder Notfallkontakte eingetragen.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full border" aria-label="Notfallkontakte für <?= e($team['name'] ?? 'Team') ?>">
                        <thead>
                            <tr>
                                <th scope="col" class="border px-4 py-2 bg-gray-100 text-left">Spieler</th>
                                <th scope="col" class="border px-4 py-2 bg-gray-100 text-left">Position</th>
                                <th scope="col" class="border px-4 py-2 bg-gray-100 text-left">Notfallkontakt</th>
                                <th scope="col" class="border px-4 py-2 bg-gray-100 text-left">Telefonnummer</th>
                                <th scope="col" class="border px-4 py-2 bg-gray-100 text-left">Beziehung</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($playerContactsData as $data): ?>
                                <?php 
                                $player = $data['player'];
                                $contacts = $data['contacts'];
                                
                                $playerName = e($player['first_name'] ?? '') . ' ' . e($player['last_name'] ?? '');
                                $playerNumber = e($player['jersey_number'] ?? '');
                                $playerPosition = e($player['position'] ?? '');
                                
                                if (empty($contacts)): 
                                ?>
                                    <tr>
                                        <td class="border px-4 py-2"><?= $playerName ?> <?= !empty($playerNumber) ? "($playerNumber)" : '' ?></td>
                                        <td class="border px-4 py-2"><?= $playerPosition ?></td>
                                        <td class="border px-4 py-2 text-red-600" colspan="3">Kein Notfallkontakt vorhanden</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contacts as $index => $contact): ?>
                                        <tr>
                                            <?php if ($index === 0): ?>
                                                <td class="border px-4 py-2" rowspan="<?= count($contacts) ?>">
                                                    <?= $playerName ?> <?= !empty($playerNumber) ? "($playerNumber)" : '' ?>
                                                </td>
                                                <td class="border px-4 py-2" rowspan="<?= count($contacts) ?>">
                                                    <?= $playerPosition ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="border px-4 py-2"><?= e($contact['contact_name'] ?? '') ?></td>
                                            <td class="border px-4 py-2">
                                                <a href="tel:<?= e($contact['phone_number'] ?? '') ?>" 
                                                   class="text-orange-600 hover:underline"
                                                   aria-label="Anrufen: <?= e($contact['phone_number'] ?? '') ?>">
                                                    <?= e($contact['phone_number'] ?? '') ?>
                                                </a>
                                            </td>
                                            <td class="border px-4 py-2"><?= e($contact['relationship'] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="mt-8 text-sm text-gray-600">
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
                    <p class="font-bold">Vertrauliche Daten</p>
                    <p>Diese Liste enthält vertrauliche Kontaktdaten und darf nur für Notfälle verwendet werden.</p>
                </div>
                <p>Generiert durch <?= e(APP_NAME) ?> am <?= date('d.m.Y') ?>.</p>
            </div>
            
            <!-- Enhanced functionality: Print button -->
            <div class="mt-6 flex justify-center">
                <button id="print-button" 
                        class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        aria-label="Liste drucken">
                    <i class="fas fa-print mr-2" aria-hidden="true"></i> Drucken
                </button>
            </div>
        </div>
        
        <!-- Dark mode toggle -->
        <div class="text-center mb-4">
            <button id="theme-toggle" 
                    class="inline-flex items-center justify-center p-2 rounded-full bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400"
                    aria-label="Dunkelmodus umschalten">
                <i class="fas fa-moon text-gray-700" id="dark-icon" aria-hidden="true"></i>
                <i class="fas fa-sun text-orange-500 hidden" id="light-icon" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    
    <script src="assets/js/dark-mode.js" defer></script>
    <script>
        // Print functionality
        document.addEventListener('DOMContentLoaded', function() {
            const printButton = document.getElementById('print-button');
            if (printButton) {
                printButton.addEventListener('click', function() {
                    window.print();
                });
            }
            
            // Make phone numbers easier to tap on mobile
            const phoneLinks = document.querySelectorAll('a[href^="tel:"]');
            phoneLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Add visual feedback on tap
                    this.classList.add('bg-orange-100');
                    setTimeout(() => {
                        this.classList.remove('bg-orange-100');
                    }, 200);
                });
            });
        });
    </script>
</body>
</html>