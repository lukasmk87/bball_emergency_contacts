<?php
require_once 'functions.php';
require_once 'templates/header.php';

// Überprüfen, ob Benutzer angemeldet ist und Trainerrechte hat
if (!isLoggedIn() || !hasRole('trainer')) {
    redirect('dashboard.php');
    exit;
}

// Team-ID aus der URL abrufen
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// Überprüfen, ob der Benutzer Zugriff auf dieses Team hat
$teams = getTeamsForUser($_SESSION['user_id']);
$hasAccess = false;
$teamName = '';

foreach ($teams as $team) {
    if ($team['id'] == $teamId) {
        $hasAccess = true;
        $teamName = $team['name'];
        break;
    }
}

if (!$hasAccess) {
    setMessage('red', 'Sie haben keinen Zugriff auf dieses Team.');
    redirect('dashboard.php');
    exit;
}

// Aktion basierend auf GET-Parameter
$action = $_GET['action'] ?? '';
$playerId = isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0;

// Spieler-Formular-Daten
$formData = [
    'id' => 0,
    'first_name' => '',
    'last_name' => '',
    'jersey_number' => '',
    'position' => ''
];

// Spieler zum Bearbeiten laden oder Löschen
if ($action === 'edit' && $playerId > 0) {
    $player = db()->fetchOne("SELECT * FROM players WHERE id = ? AND team_id = ?", [$playerId, $teamId]);
    
    if ($player) {
        $formData = $player;
    } else {
        setMessage('red', 'Spieler nicht gefunden.');
        redirect("players.php?team_id=$teamId");
        exit;
    }
} elseif ($action === 'delete' && $playerId > 0) {
    // CSRF-Token überprüfen
    if (!validateCSRFToken($_GET['token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        // Spieler löschen
        try {
            // Zuerst Notfallkontakte löschen
            db()->execute("DELETE FROM emergency_contacts WHERE player_id = ?", [$playerId]);
            
            // Dann den Spieler löschen
            $deleted = db()->execute("DELETE FROM players WHERE id = ? AND team_id = ?", [$playerId, $teamId]);
            
            if ($deleted) {
                setMessage('green', 'Spieler wurde erfolgreich gelöscht.');
                logActivity($_SESSION['user_id'], 'player_delete', "Spieler mit ID $playerId gelöscht");
            } else {
                setMessage('red', 'Spieler konnte nicht gelöscht werden.');
            }
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                setMessage('red', 'Fehler: ' . $e->getMessage());
            } else {
                setMessage('red', 'Es ist ein Fehler aufgetreten. Spieler konnte nicht gelöscht werden.');
            }
        }
    }
    
    redirect("players.php?team_id=$teamId");
    exit;
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        $playerId = isset($_POST['player_id']) ? (int)$_POST['player_id'] : 0;
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $jerseyNumber = trim($_POST['jersey_number'] ?? '');
        $position = trim($_POST['position'] ?? '');
        
        $errors = [];
        
        // Validierung
        if (empty($firstName)) {
            $errors[] = 'Vorname ist erforderlich.';
        }
        
        if (empty($lastName)) {
            $errors[] = 'Nachname ist erforderlich.';
        }
        
        if (empty($errors)) {
            try {
                if ($playerId > 0) {
                    // Spieler aktualisieren
                    $updated = db()->execute(
                        "UPDATE players SET first_name = ?, last_name = ?, jersey_number = ?, position = ? WHERE id = ? AND team_id = ?",
                        [$firstName, $lastName, $jerseyNumber, $position, $playerId, $teamId]
                    );
                    
                    if ($updated) {
                        setMessage('green', 'Spieler wurde erfolgreich aktualisiert.');
                        logActivity($_SESSION['user_id'], 'player_update', "Spieler mit ID $playerId aktualisiert");
                    } else {
                        setMessage('red', 'Spieler konnte nicht aktualisiert werden.');
                    }
                } else {
                    // Neuen Spieler hinzufügen
                    $newPlayerId = db()->insert(
                        "INSERT INTO players (first_name, last_name, jersey_number, position, team_id) VALUES (?, ?, ?, ?, ?)",
                        [$firstName, $lastName, $jerseyNumber, $position, $teamId]
                    );
                    
                    if ($newPlayerId) {
                        setMessage('green', 'Spieler wurde erfolgreich hinzugefügt.');
                        logActivity($_SESSION['user_id'], 'player_add', "Neuer Spieler mit ID $newPlayerId hinzugefügt");
                    } else {
                        setMessage('red', 'Spieler konnte nicht hinzugefügt werden.');
                    }
                }
                
                redirect("players.php?team_id=$teamId");
                exit;
            } catch (Exception $e) {
                if (DEBUG_MODE) {
                    $errors[] = 'Fehler: ' . $e->getMessage();
                } else {
                    $errors[] = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.';
                }
            }
        }
    }
}

// Spielerliste für das Team abrufen
try {
    $players = getPlayersForTeam($teamId);
} catch (Exception $e) {
    if (DEBUG_MODE) {
        die("Fehler beim Abrufen der Spieler: " . $e->getMessage());
    } else {
        $players = [];
        setMessage('red', 'Spieler konnten nicht abgerufen werden.');
    }
}

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold"><?= e($teamName) ?> - Spieler</h2>
            <p class="text-gray-600">Verwalten Sie die Spieler dieses Teams</p>
        </div>
        <div>
            <a href="dashboard.php?team_id=<?= $teamId ?>" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 mr-2">
                <i class="fas fa-arrow-left mr-2"></i>Zurück
            </a>
            <a href="players.php?team_id=<?= $teamId ?>&action=new" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                <i class="fas fa-plus mr-2"></i>Neuer Spieler
            </a>
        </div>
    </div>
    
    <?php if ($action === 'edit' || $action === 'new'): ?>
    <!-- Spieler-Formular -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-xl font-bold mb-4"><?= $action === 'edit' ? 'Spieler bearbeiten' : 'Neuen Spieler hinzufügen' ?></h3>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                <ul class="list-disc pl-4">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="players.php?team_id=<?= $teamId ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="player_id" value="<?= $formData['id'] ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="first_name" class="block text-gray-700 mb-2">Vorname</label>
                    <input type="text" id="first_name" name="first_name" value="<?= e($formData['first_name']) ?>" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>
                
                <div>
                    <label for="last_name" class="block text-gray-700 mb-2">Nachname</label>
                    <input type="text" id="last_name" name="last_name" value="<?= e($formData['last_name']) ?>" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>
                
                <div>
                    <label for="jersey_number" class="block text-gray-700 mb-2">Trikotnummer</label>
                    <input type="text" id="jersey_number" name="jersey_number" value="<?= e($formData['jersey_number']) ?>" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                
                <div>
                    <label for="position" class="block text-gray-700 mb-2">Position</label>
                    <select id="position" name="position" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="" <?= empty($formData['position']) ? 'selected' : '' ?>>Keine Position</option>
                        <option value="Guard" <?= $formData['position'] === 'Guard' ? 'selected' : '' ?>>Guard</option>
                        <option value="Forward" <?= $formData['position'] === 'Forward' ? 'selected' : '' ?>>Forward</option>
                        <option value="Center" <?= $formData['position'] === 'Center' ? 'selected' : '' ?>>Center</option>
                        <option value="Point Guard" <?= $formData['position'] === 'Point Guard' ? 'selected' : '' ?>>Point Guard</option>
                        <option value="Shooting Guard" <?= $formData['position'] === 'Shooting Guard' ? 'selected' : '' ?>>Shooting Guard</option>
                        <option value="Small Forward" <?= $formData['position'] === 'Small Forward' ? 'selected' : '' ?>>Small Forward</option>
                        <option value="Power Forward" <?= $formData['position'] === 'Power Forward' ? 'selected' : '' ?>>Power Forward</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-between mt-6">
                <a href="players.php?team_id=<?= $teamId ?>" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
                    Abbrechen
                </a>
                <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    <?= $action === 'edit' ? 'Spieler aktualisieren' : 'Spieler hinzufügen' ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Spieler-Tabelle -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trikotnummer</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notfallkontakte</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($players)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">Keine Spieler vorhanden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($players as $player): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0 bg-orange-200 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-orange-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= e($player['first_name']) ?> <?= e($player['last_name']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= e($player['jersey_number']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= e($player['position']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($player['has_emergency_contact'] > 0): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <?= $player['has_emergency_contact'] ?> Kontakt(e)
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Keine Kontakte
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="emergency_contacts.php?player_id=<?= $player['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="Notfallkontakte verwalten">
                                    <i class="fas fa-phone-alt"></i>
                                </a>
                                <a href="players.php?team_id=<?= $teamId ?>&action=edit&player_id=<?= $player['id'] ?>" class="text-orange-600 hover:text-orange-900 mr-3" title="Bearbeiten">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="players.php?team_id=<?= $teamId ?>&action=delete&player_id=<?= $player['id'] ?>&token=<?= $csrf_token ?>" 
                                   class="text-red-600 hover:text-red-900 delete-confirm" title="Löschen">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>