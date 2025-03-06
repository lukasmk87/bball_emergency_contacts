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
$teamId = $teamAccess['team_id'];
$userId = $teamAccess['user_id'];

// Team-Informationen abrufen
$team = db()->fetchOne("SELECT * FROM teams WHERE id = ?", [$teamId]);

if (!$team) {
    // Team nicht gefunden
    require_once 'templates/error_page.php';
    exit;
}

// Spieler und ihre Notfallkontakte für das Team abrufen
$players = db()->fetchAll("
    SELECT p.id, p.first_name, p.last_name, p.jersey_number, p.position
    FROM players p
    WHERE p.team_id = ?
    ORDER BY p.last_name, p.first_name
", [$teamId]);

$playerContacts = [];

foreach ($players as $player) {
    $contacts = db()->fetchAll("
        SELECT contact_name, phone_number, relationship
        FROM emergency_contacts
        WHERE player_id = ?
        ORDER BY id
    ", [$player['id']]);
    
    $playerContacts[$player['id']] = [
        'player' => $player,
        'contacts' => $contacts
    ];
}

// Aktivität protokollieren
logActivity($userId, 'emergency_access', "Notfallzugriff auf Team $teamId über QR-Code");

// HTML-Ausgabe beginnen
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notfallkontakte: <?= htmlspecialchars($team['name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-orange-600"><?= APP_NAME ?></h1>
                <h2 class="text-xl font-semibold">Notfallkontakte: <?= htmlspecialchars($team['name']) ?></h2>
                <p class="text-gray-600 mt-2">Stand: <?= date('d.m.Y H:i') ?> Uhr</p>
            </div>
            
            <?php if (empty($players)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
                    <p>Für dieses Team sind noch keine Spieler oder Notfallkontakte eingetragen.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full border">
                        <thead>
                            <tr>
                                <th class="border px-4 py-2 bg-gray-100">Spieler</th>
                                <th class="border px-4 py-2 bg-gray-100">Position</th>
                                <th class="border px-4 py-2 bg-gray-100">Notfallkontakt</th>
                                <th class="border px-4 py-2 bg-gray-100">Telefonnummer</th>
                                <th class="border px-4 py-2 bg-gray-100">Beziehung</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($playerContacts as $data): ?>
                                <?php 
                                $player = $data['player'];
                                $contacts = $data['contacts'];
                                
                                $playerName = htmlspecialchars($player['first_name'] . ' ' . $player['last_name']);
                                $playerNumber = htmlspecialchars($player['jersey_number']);
                                $playerPosition = htmlspecialchars($player['position']);
                                
                                if (empty($contacts)): 
                                ?>
                                    <tr>
                                        <td class="border px-4 py-2"><?= $playerName ?> (<?= $playerNumber ?>)</td>
                                        <td class="border px-4 py-2"><?= $playerPosition ?></td>
                                        <td class="border px-4 py-2 text-red-600" colspan="3">Kein Notfallkontakt vorhanden</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($contacts as $index => $contact): ?>
                                        <tr>
                                            <?php if ($index === 0): ?>
                                                <td class="border px-4 py-2" rowspan="<?= count($contacts) ?>">
                                                    <?= $playerName ?> (<?= $playerNumber ?>)
                                                </td>
                                                <td class="border px-4 py-2" rowspan="<?= count($contacts) ?>">
                                                    <?= $playerPosition ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="border px-4 py-2"><?= htmlspecialchars($contact['contact_name']) ?></td>
                                            <td class="border px-4 py-2">
                                                <a href="tel:<?= htmlspecialchars($contact['phone_number']) ?>" class="text-orange-600">
                                                    <?= htmlspecialchars($contact['phone_number']) ?>
                                                </a>
                                            </td>
                                            <td class="border px-4 py-2"><?= htmlspecialchars($contact['relationship']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="mt-8 text-sm text-gray-600">
                <p>Diese Liste enthält vertrauliche Daten und darf nur für Notfälle verwendet werden.</p>
                <p>Generiert durch <?= htmlspecialchars(APP_NAME) ?> am <?= date('d.m.Y') ?>.</p>
            </div>
        </div>
    </div>
</body>
</html>