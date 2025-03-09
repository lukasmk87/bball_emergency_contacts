<?php
require_once 'functions.php';
require_once 'templates/header.php';

// Überprüfen, ob Benutzer angemeldet ist und Trainerrechte hat
if (!isLoggedIn() || !hasRole('trainer')) {
    setMessage('red', 'Sie haben keine Berechtigung, diese Seite aufzurufen.');
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
    if (isset($team['id']) && $team['id'] == $teamId) {
        $hasAccess = true;
        $teamName = $team['name'] ?? '';
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
    try {
        $player = db()->fetchOne("SELECT * FROM players WHERE id = ? AND team_id = ?", [$playerId, $teamId]);
        
        if ($player) {
            $formData = $player;
        } else {
            setMessage('red', 'Spieler nicht gefunden.');
            redirect("players.php?team_id=$teamId");
            exit;
        }
    } catch (Exception $e) {
        handleError($e, 'Fehler beim Laden des Spielers.');
        redirect("players.php?team_id=$teamId");
        exit;
    }
} elseif ($action === 'delete' && $playerId > 0) {
    // CSRF-Token überprüfen
    if (!validateCSRFToken($_GET['token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        // Prüfen, ob der Spieler existiert
        try {
            $player = db()->fetchOne("SELECT * FROM players WHERE id = ? AND team_id = ?", [$playerId, $teamId]);
            
            if (!$player) {
                setMessage('red', 'Spieler nicht gefunden.');
                redirect("players.php?team_id=$teamId");
                exit;
            }
            
            // Transaktion starten
            db()->getConnection()->beginTransaction();
            
            // Zuerst Notfallkontakte löschen
            db()->execute("DELETE FROM emergency_contacts WHERE player_id = ?", [$playerId]);
            
            // Dann den Spieler löschen
            $deleted = db()->execute("DELETE FROM players WHERE id = ? AND team_id = ?", [$playerId, $teamId]);
            
            // Transaktion abschließen
            db()->getConnection()->commit();
            
            if ($deleted) {
                setMessage('green', 'Spieler wurde erfolgreich gelöscht.');
                logActivity($_SESSION['user_id'], 'player_delete', "Spieler mit ID $playerId gelöscht");
            } else {
                setMessage('red', 'Spieler konnte nicht gelöscht werden.');
            }
        } catch (Exception $e) {
            // Transaktion zurückrollen bei Fehler
            if (db()->getConnection()->inTransaction()) {
                db()->getConnection()->rollBack();
            }
            
            handleError($e, 'Fehler beim Löschen des Spielers.');
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
        // Daten aus dem Formular abrufen und bereinigen
        $playerId = isset($_POST['player_id']) ? (int)$_POST['player_id'] : 0;
        $firstName = sanitizeInput($_POST['first_name'] ?? '');
        $lastName = sanitizeInput($_POST['last_name'] ?? '');
        $jerseyNumber = sanitizeInput($_POST['jersey_number'] ?? '');
        $position = sanitizeInput($_POST['position'] ?? '');
        
        // Formularvalidierung
        $rules = [
            'first_name' => ['required' => true],
            'last_name' => ['required' => true]
        ];
        
        $errors = validateForm([
            'first_name' => $firstName,
            'last_name' => $lastName
        ], $rules);
        
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
                    // Prüfen, ob Spieler bereits existiert
                    $existingPlayer = db()->fetchOne(
                        "SELECT id FROM players WHERE team_id = ? AND first_name = ? AND last_name = ?", 
                        [$teamId, $firstName, $lastName]
                    );
                    
                    if ($existingPlayer) {
                        setMessage('red', 'Ein Spieler mit diesem Namen existiert bereits in diesem Team.');
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
                }
                
                redirect("players.php?team_id=$teamId");
                exit;
            } catch (Exception $e) {
                handleError($e, 'Fehler beim Speichern des Spielers.');
            }
        }
    }
}

// Spielerliste für das Team abrufen
try {
    $players = getPlayersForTeam($teamId);
} catch (Exception $e) {
    handleError($e, 'Fehler beim Abrufen der Spieler.');
    $players = [];
}

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-6" id="main-content">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 sm:mb-6">
        <div class="mb-3 sm:mb-0">
            <h2 class="text-xl sm:text-2xl font-bold"><?= e($teamName) ?> - Spieler</h2>
            <p class="text-gray-600 text-sm sm:text-base">Verwalten Sie die Spieler dieses Teams</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <a href="dashboard.php?team_id=<?= $teamId ?>" 
               class="flex items-center justify-center bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 w-full sm:w-auto"
               aria-label="Zurück zum Dashboard">
                <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i>Zurück
            </a>
            <a href="players.php?team_id=<?= $teamId ?>&action=new" 
               class="flex items-center justify-center bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 w-full sm:w-auto"
               aria-label="Neuen Spieler hinzufügen">
                <i class="fas fa-plus mr-2" aria-hidden="true"></i>Neuer Spieler
            </a>
        </div>
    </div>
    
    <?php if ($action === 'edit' || $action === 'new'): ?>
    <!-- Spieler-Formular - Mobile-optimiert -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <h3 class="text-lg sm:text-xl font-bold mb-4" id="form-heading">
            <?= $action === 'edit' ? 'Spieler bearbeiten' : 'Neuen Spieler hinzufügen' ?>
        </h3>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <ul class="list-disc pl-4">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="players.php?team_id=<?= $teamId ?>" aria-labelledby="form-heading">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="player_id" value="<?= $formData['id'] ?>">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                <div>
                    <label for="first_name" id="first_name_label" class="block text-gray-700 mb-2">Vorname</label>
                    <input type="text" 
                           id="first_name" 
                           name="first_name" 
                           value="<?= e($formData['first_name']) ?>" 
                           class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" 
                           required
                           aria-required="true"
                           aria-labelledby="first_name_label"
                           aria-invalid="<?= isset($errors['first_name']) ? 'true' : 'false' ?>">
                </div>
                
                <div>
                    <label for="last_name" id="last_name_label" class="block text-gray-700 mb-2">Nachname</label>
                    <input type="text" 
                           id="last_name" 
                           name="last_name" 
                           value="<?= e($formData['last_name']) ?>" 
                           class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" 
                           required
                           aria-required="true"
                           aria-labelledby="last_name_label"
                           aria-invalid="<?= isset($errors['last_name']) ? 'true' : 'false' ?>">
                </div>
                
                <div>
                    <label for="jersey_number" id="jersey_number_label" class="block text-gray-700 mb-2">Trikotnummer</label>
                    <input type="text" 
                           id="jersey_number" 
                           name="jersey_number" 
                           value="<?= e($formData['jersey_number']) ?>" 
                           class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500"
                           aria-labelledby="jersey_number_label"
                           inputmode="numeric"
                           pattern="[0-9]*">
                </div>
                
                <div>
                    <label for="position" id="position_label" class="block text-gray-700 mb-2">Position</label>
                    <select id="position" 
                            name="position" 
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500"
                            aria-labelledby="position_label">
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
            
            <div class="flex flex-col sm:flex-row justify-between mt-6 gap-2">
                <a href="players.php?team_id=<?= $teamId ?>" 
                   class="flex items-center justify-center bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 w-full sm:w-auto">
                    Abbrechen
                </a>
                <button type="submit" 
                        class="flex items-center justify-center bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600 w-full sm:w-auto">
                    <?= $action === 'edit' ? 'Spieler aktualisieren' : 'Spieler hinzufügen' ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Mobile-optimierte Spielerliste -->
    <?php if (empty($players)): ?>
        <div class="bg-white rounded-lg shadow-md p-4 text-center text-gray-500" role="alert">
            Keine Spieler vorhanden
        </div>
    <?php else: ?>
        <!-- Card Layout für Mobilgeräte -->
        <div class="block sm:hidden space-y-4">
            <?php foreach ($players as $player): ?>
                <div class="bg-white rounded-lg shadow-md p-4" id="player-mobile-<?= $player['id'] ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center">
                            <div class="h-12 w-12 flex-shrink-0 bg-orange-200 rounded-full flex items-center justify-center" aria-hidden="true">
                                <i class="fas fa-user text-orange-600"></i>
                            </div>
                            <div class="ml-3">
                                <div class="font-medium"><?= e($player['first_name']) ?> <?= e($player['last_name']) ?></div>
                                <div class="text-sm text-gray-600">
                                    <?= !empty($player['jersey_number']) ? "#" . e($player['jersey_number']) : '' ?>
                                    <?= !empty($player['position']) ? ' - ' . e($player['position']) : '' ?>
                                </div>
                            </div>
                        </div>
                        <div>
                            <?php if (isset($player['has_emergency_contact']) && $player['has_emergency_contact'] > 0): ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    <?= $player['has_emergency_contact'] ?> Kontakt(e)
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Keine Kontakte
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-4 gap-3">
                        <a href="emergency_contacts.php?player_id=<?= $player['id'] ?>" 
                           class="text-blue-600 hover:text-blue-900" 
                           title="Notfallkontakte verwalten"
                           aria-label="Notfallkontakte für <?= e($player['first_name']) ?> <?= e($player['last_name']) ?> verwalten">
                            <i class="fas fa-phone-alt" aria-hidden="true"></i>
                        </a>
                        <a href="players.php?team_id=<?= $teamId ?>&action=edit&player_id=<?= $player['id'] ?>" 
                           class="text-orange-600 hover:text-orange-900" 
                           title="Bearbeiten"
                           aria-label="Spieler <?= e($player['first_name']) ?> <?= e($player['last_name']) ?> bearbeiten">
                            <i class="fas fa-edit" aria-hidden="true"></i>
                        </a>
                        <a href="players.php?team_id=<?= $teamId ?>&action=delete&player_id=<?= $player['id'] ?>&token=<?= $csrf_token ?>" 
                           class="text-red-600 hover:text-red-900 delete-confirm" 
                           title="Löschen"
                           aria-label="Spieler <?= e($player['first_name']) ?> <?= e($player['last_name']) ?> löschen"
                           data-item="Spieler">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Tabelle für Desktop -->
        <div class="hidden sm:block bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" aria-label="Spielerliste">
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
                        <?php foreach ($players as $player): ?>
                            <tr id="player-<?= $player['id'] ?>" tabindex="0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 bg-orange-200 rounded-full flex items-center justify-center" aria-hidden="true">
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
                                    <?php if (isset($player['has_emergency_contact']) && $player['has_emergency_contact'] > 0): ?>
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
                                    <a href="emergency_contacts.php?player_id=<?= $player['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-900 mr-3" 
                                       title="Notfallkontakte verwalten"
                                       aria-label="Notfallkontakte für <?= e($player['first_name']) ?> <?= e($player['last_name']) ?> verwalten">
                                        <i class="fas fa-phone-alt" aria-hidden="true"></i>
                                    </a>
                                    <a href="players.php?team_id=<?= $teamId ?>&action=edit&player_id=<?= $player['id'] ?>" 
                                       class="text-orange-600 hover:text-orange-900 mr-3" 
                                       title="Bearbeiten"
                                       aria-label="Spieler <?= e($player['first_name']) ?> <?= e($player['last_name']) ?> bearbeiten">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </a>
                                    <a href="players.php?team_id=<?= $teamId ?>&action=delete&player_id=<?= $player['id'] ?>&token=<?= $csrf_token ?>" 
                                       class="text-red-600 hover:text-red-900 delete-confirm" 
                                       title="Löschen"
                                       aria-label="Spieler <?= e($player['first_name']) ?> <?= e($player['last_name']) ?> löschen"
                                       data-item="Spieler">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Bulk-Import Button -->
    <div class="mt-6 text-right">
        <a href="player_team.php?team_id=<?= $teamId ?>" 
           class="inline-flex items-center justify-center bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
           aria-label="Spieler importieren">
            <i class="fas fa-file-import mr-2" aria-hidden="true"></i>Spieler importieren
        </a>
    </div>
</div>

<script>
// Enhanced player table keyboard navigation
document.addEventListener('DOMContentLoaded', function() {
    const playerRows = document.querySelectorAll('table tbody tr');
    
    playerRows.forEach((row, index) => {
        row.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'Enter':
                case ' ':
                    // Find and click the edit link when Enter or Space is pressed
                    const editLink = row.querySelector('a[title="Bearbeiten"]');
                    if (editLink) {
                        e.preventDefault();
                        editLink.click();
                    }
                    break;
                    
                case 'ArrowDown':
                    // Navigate to next row
                    e.preventDefault();
                    if (index < playerRows.length - 1) {
                        playerRows[index + 1].focus();
                    }
                    break;
                    
                case 'ArrowUp':
                    // Navigate to previous row
                    e.preventDefault();
                    if (index > 0) {
                        playerRows[index - 1].focus();
                    }
                    break;
            }
        });
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>