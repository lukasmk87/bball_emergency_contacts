<?php
require_once 'functions.php';
require_once 'templates/header.php';

// Überprüfen, ob Benutzer angemeldet ist und Trainer-Rechte hat
if (!isLoggedIn() || !hasRole('trainer')) {
    setMessage('red', 'Sie haben keine Berechtigung, diese Seite aufzurufen.');
    redirect('dashboard.php');
    exit;
}

// Team-ID aus der URL abrufen
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// Überprüfen, ob das Team existiert und der Benutzer Zugriff hat
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

// Spieler-ID aus der URL abrufen (falls ein Spieler transferiert werden soll)
$playerId = isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0;
$transferAction = isset($_GET['action']) && $_GET['action'] === 'transfer';

// Wenn ein Spieler transferiert werden soll
if ($transferAction && $playerId > 0) {
    // Überprüfen, ob der Spieler existiert und dem Benutzer zugänglich ist
    $player = db()->fetchOne("
        SELECT p.*, t.name AS current_team_name 
        FROM players p
        JOIN teams t ON p.team_id = t.id
        WHERE p.id = ?
    ", [$playerId]);
    
    if (!$player) {
        setMessage('red', 'Spieler nicht gefunden.');
        redirect("players.php?team_id=$teamId");
        exit;
    }
    
    // Überprüfen, ob der Benutzer Zugriff auf das aktuelle Team des Spielers hat
    $playerTeamId = $player['team_id'];
    $hasAccessToPlayerTeam = false;
    
    foreach ($teams as $team) {
        if ($team['id'] == $playerTeamId) {
            $hasAccessToPlayerTeam = true;
            break;
        }
    }
    
    if (!$hasAccessToPlayerTeam && !hasRole('admin')) {
        setMessage('red', 'Sie haben keinen Zugriff auf das aktuelle Team dieses Spielers.');
        redirect("players.php?team_id=$teamId");
        exit;
    }
    
    // Wenn das Formular für den Transfer abgesendet wurde
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_team_id'])) {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
        } else {
            $targetTeamId = (int)$_POST['transfer_team_id'];
            
            // Überprüfen, ob der Benutzer Zugriff auf das Zielteam hat
            $hasAccessToTargetTeam = false;
            
            foreach ($teams as $team) {
                if ($team['id'] == $targetTeamId) {
                    $hasAccessToTargetTeam = true;
                    break;
                }
            }
            
            if (!$hasAccessToTargetTeam && !hasRole('admin')) {
                setMessage('red', 'Sie haben keinen Zugriff auf das Zielteam.');
            } else {
                // Spieler transferieren
                $updated = db()->execute(
                    "UPDATE players SET team_id = ? WHERE id = ?",
                    [$targetTeamId, $playerId]
                );
                
                if ($updated) {
                    setMessage('green', 'Spieler wurde erfolgreich transferiert.');
                    logActivity($_SESSION['user_id'], 'player_transfer', "Spieler $playerId von Team $playerTeamId zu Team $targetTeamId transferiert");
                } else {
                    setMessage('red', 'Spieler konnte nicht transferiert werden.');
                }
                
                redirect("players.php?team_id=$targetTeamId");
                exit;
            }
        }
    }
    
    // Formular für den Transfer anzeigen
    $formTitle = "Spieler transferieren: " . $player['first_name'] . ' ' . $player['last_name'];
    $formDescription = "Aktuelles Team: " . $player['current_team_name'];
} else {
    // Mehrere Spieler gleichzeitig hinzufügen
    $formTitle = "Spieler zum Team hinzufügen: $teamName";
    $formDescription = "Fügen Sie mehrere Spieler gleichzeitig hinzu";
    
    // Wenn das Formular für Masseneinfügung abgesendet wurde
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['player_data'])) {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
        } else {
            $playerData = trim($_POST['player_data']);
            $lines = explode("\n", $playerData);
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Format: Vorname,Nachname,Trikotnummer,Position
                $parts = explode(',', $line);
                
                if (count($parts) >= 2) {
                    $firstName = trim($parts[0]);
                    $lastName = trim($parts[1]);
                    $jerseyNumber = isset($parts[2]) ? trim($parts[2]) : '';
                    $position = isset($parts[3]) ? trim($parts[3]) : '';
                    
                    if (!empty($firstName) && !empty($lastName)) {
                        try {
                            $newPlayerId = db()->insert(
                                "INSERT INTO players (first_name, last_name, jersey_number, position, team_id) VALUES (?, ?, ?, ?, ?)",
                                [$firstName, $lastName, $jerseyNumber, $position, $teamId]
                            );
                            
                            if ($newPlayerId) {
                                $successCount++;
                            } else {
                                $errorCount++;
                            }
                        } catch (Exception $e) {
                            $errorCount++;
                        }
                    }
                }
            }
            
            if ($successCount > 0) {
                setMessage('green', "$successCount Spieler wurden erfolgreich hinzugefügt.");
                logActivity($_SESSION['user_id'], 'bulk_player_add', "$successCount Spieler zu Team $teamId hinzugefügt");
            }
            
            if ($errorCount > 0) {
                setMessage('red', "$errorCount Spieler konnten nicht hinzugefügt werden.");
            }
            
            redirect("players.php?team_id=$teamId");
            exit;
        }
    }
}

// Alle zugänglichen Teams für den Transfer abrufen
$availableTeams = getTeamsForUser($_SESSION['user_id']);

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-3xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold"><?= e($formTitle) ?></h2>
                <p class="text-gray-600"><?= e($formDescription) ?></p>
            </div>
            <div>
                <a href="players.php?team_id=<?= $teamId ?>" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
                    <i class="fas fa-arrow-left mr-2"></i>Zurück
                </a>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <?php if ($transferAction && $playerId > 0): ?>
                <!-- Spieler-Transfer-Formular -->
                <form method="POST" action="player_team.php?team_id=<?= $teamId ?>&player_id=<?= $playerId ?>&action=transfer">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="mb-6">
                        <label for="transfer_team_id" class="block text-gray-700 mb-2">Zielteam auswählen</label>
                        <select id="transfer_team_id" name="transfer_team_id" 
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                            <option value="">Bitte wählen Sie ein Team</option>
                            <?php foreach ($availableTeams as $availableTeam): ?>
                                <?php if ($availableTeam['id'] != $player['team_id']): ?>
                                    <option value="<?= $availableTeam['id'] ?>"><?= e($availableTeam['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex justify-between">
                        <a href="players.php?team_id=<?= $teamId ?>" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
                            Abbrechen
                        </a>
                        <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                            Spieler transferieren
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Massenimport-Formular -->
                <form method="POST" action="player_team.php?team_id=<?= $teamId ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="mb-6">
                        <label for="player_data" class="block text-gray-700 mb-2">Spielerdaten (ein Spieler pro Zeile)</label>
                        <p class="text-sm text-gray-600 mb-2">Format: Vorname,Nachname,Trikotnummer,Position</p>
                        <textarea id="player_data" name="player_data" rows="10"
                            class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required
                            placeholder="Maximilian,Müller,23,Center&#10;Julia,Schmidt,7,Forward"></textarea>
                    </div>
                    
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    Beispiel:<br>
                                    Maximilian,Müller,23,Center<br>
                                    Julia,Schmidt,7,Forward<br>
                                    Thomas,Wagner,,Guard
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <a href="players.php?team_id=<?= $teamId ?>" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
                            Abbrechen
                        </a>
                        <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                            Spieler importieren
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>