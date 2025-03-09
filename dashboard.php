<?php
require_once 'functions.php';
require_once 'templates/header.php';

// Überprüfen, ob Benutzer angemeldet ist
if (!isLoggedIn()) {
    redirect('index.php');
    exit;
}

// Benutzerinformationen abrufen
$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';
$isAdmin = hasRole('admin');

// Aktives Tab bestimmen
$activeTab = $_GET['tab'] ?? 'emergency-contacts';
$validTabs = ['emergency-contacts', 'teams', 'users'];

if (!in_array($activeTab, $validTabs) || ($activeTab !== 'emergency-contacts' && !$isAdmin)) {
    $activeTab = 'emergency-contacts';
}

// Ausgewähltes Team bestimmen
$selectedTeamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;
$teams = getTeamsForUser($userId);

// Wenn kein Team ausgewählt wurde, das erste Team als Standard verwenden
if (empty($selectedTeamId) && !empty($teams) && isset($teams[0]['id'])) {
    $selectedTeamId = $teams[0]['id'];
}

// Daten für das aktuelle Tab laden
try {
    switch ($activeTab) {
        case 'emergency-contacts':
            $players = [];
            if ($selectedTeamId) {
                // Players with basic information
                $players = getPlayersForTeam($selectedTeamId);
                
                // Batch fetch all emergency contacts for these players in a single query
                $playerIds = array_column($players, 'id');
                
                if (!empty($playerIds)) {
                    // Get all contacts in one query
                    $allContacts = db()->fetchAll("
                        SELECT * FROM emergency_contacts
                        WHERE player_id IN (" . implode(',', $playerIds) . ")
                        ORDER BY player_id, id
                    ");
                    
                    // Index contacts by player_id
                    $contactsByPlayer = [];
                    foreach ($allContacts as $contact) {
                        $contactsByPlayer[$contact['player_id']][] = $contact;
                    }
                    
                    // Assign contacts to each player
                    foreach ($players as &$player) {
                        $player['emergency_contacts'] = $contactsByPlayer[$player['id']] ?? [];
                    }
                }
            }
            break;
            
        case 'teams':
            // Alle Teams mit zusätzlichen Informationen für Admins laden
            if ($isAdmin) {
                $teamsWithDetails = [];
                foreach ($teams as $team) {
                    // Safe array access
                    $teamId = $team['id'] ?? 0;
                    if (!$teamId) continue;
                    
                    $playerCount = db()->fetchOne(
                        "SELECT COUNT(*) as count FROM players WHERE team_id = ?", 
                        [$teamId]
                    );
                    
                    $coach = null;
                    if (isset($team['coach_id']) && $team['coach_id']) {
                        $coach = db()->fetchOne(
                            "SELECT name FROM users WHERE id = ?", 
                            [$team['coach_id']]
                        );
                    }
                    
                    $teamsWithDetails[] = [
                        'id' => $teamId,
                        'name' => $team['name'] ?? 'Unbekannt',
                        'category' => $team['category'] ?? '',
                        'player_count' => $playerCount ? $playerCount['count'] : 0,
                        'coach_name' => $coach ? $coach['name'] : 'Nicht zugewiesen'
                    ];
                }
                $teams = $teamsWithDetails;
            }
            break;
            
        case 'users':
            // Users will be loaded directly in the display section
            break;
    }
} catch (Exception $e) {
    // Use the improved error handling
    handleError($e, 'Fehler beim Laden der Daten. Bitte versuchen Sie es später erneut.');
    $players = [];
    $teams = [];
}

// CSRF-Token für Formulare generieren
$csrf_token = generateCSRFToken();
?>

<div id="main-content" class="container mx-auto px-2 sm:px-4 py-4 sm:py-6">
    <!-- Tabs - Optimized for Mobile with full width tabs on small screens -->
    <div class="border-b border-gray-200 mb-4 sm:mb-6 overflow-x-auto" role="tablist" aria-label="Dashboard-Tabs">
        <ul class="flex flex-nowrap sm:flex-wrap -mb-px min-w-full sm:min-w-0">
            <li class="mr-1 sm:mr-2 flex-shrink-0" role="presentation">
                <a href="?tab=emergency-contacts" 
                   class="inline-block p-2 sm:p-4 rounded-t-lg border-b-2 text-sm sm:text-base <?= $activeTab === 'emergency-contacts' ? 'border-orange-500 text-orange-500' : 'border-transparent hover:border-gray-300' ?>"
                   role="tab"
                   aria-selected="<?= $activeTab === 'emergency-contacts' ? 'true' : 'false' ?>"
                   aria-controls="emergency-contacts-panel"
                   id="emergency-contacts-tab">
                    <i class="fas fa-phone-alt mr-1 sm:mr-2" aria-hidden="true"></i>Notfallkontakte
                </a>
            </li>
            
            <?php if ($isAdmin): ?>
                <li class="mr-1 sm:mr-2 flex-shrink-0" role="presentation">
                    <a href="?tab=teams" 
                       class="inline-block p-2 sm:p-4 rounded-t-lg border-b-2 text-sm sm:text-base <?= $activeTab === 'teams' ? 'border-orange-500 text-orange-500' : 'border-transparent hover:border-gray-300' ?>"
                       role="tab"
                       aria-selected="<?= $activeTab === 'teams' ? 'true' : 'false' ?>"
                       aria-controls="teams-panel"
                       id="teams-tab">
                        <i class="fas fa-users mr-1 sm:mr-2" aria-hidden="true"></i>Teams
                    </a>
                </li>
                <li class="mr-1 sm:mr-2 flex-shrink-0" role="presentation">
                    <a href="?tab=users" 
                       class="inline-block p-2 sm:p-4 rounded-t-lg border-b-2 text-sm sm:text-base <?= $activeTab === 'users' ? 'border-orange-500 text-orange-500' : 'border-transparent hover:border-gray-300' ?>"
                       role="tab"
                       aria-selected="<?= $activeTab === 'users' ? 'true' : 'false' ?>"
                       aria-controls="users-panel"
                       id="users-tab">
                        <i class="fas fa-user-cog mr-1 sm:mr-2" aria-hidden="true"></i>Benutzer
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Tab Content -->
    <div id="tab-content">
        <?php if ($activeTab === 'emergency-contacts'): ?>
            <!-- Emergency Contacts Tab -->
            <div id="emergency-contacts-panel" 
                 role="tabpanel" 
                 aria-labelledby="emergency-contacts-tab" 
                 class="tab-pane active">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 sm:mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold mb-3 sm:mb-0">Notfallkontakte</h2>
                    
                    <?php if (!empty($teams)): ?>
                        <div class="w-full sm:w-auto mb-3 sm:mb-0">
                            <label for="team-select" class="block text-sm font-medium text-gray-700 mb-1">Team auswählen:</label>
                            <select id="team-select" 
                                    name="team_id" 
                                    class="w-full sm:w-64 p-2 text-base border-gray-300 focus:outline-none focus:ring-orange-500 focus:border-orange-500 rounded-md"
                                    aria-label="Team auswählen">
                                <?php foreach ($teams as $team): ?>
                                    <?php if (isset($team['id']) && isset($team['name'])): ?>
                                    <option value="<?= $team['id'] ?>" <?= $selectedTeamId == $team['id'] ? 'selected' : '' ?>>
                                        <?= e($team['name']) ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($selectedTeamId)): ?>
                    <!-- Mobile-friendly action buttons: Stack vertically on mobile, horizontal on larger screens -->
                    <div class="flex flex-wrap gap-2 mb-4 sm:mb-6">
                        <a href="players.php?team_id=<?= $selectedTeamId ?>" 
                           class="flex items-center justify-center bg-blue-500 text-white p-2 rounded hover:bg-blue-600 w-full sm:w-auto"
                           aria-label="Spieler verwalten">
                            <i class="fas fa-user-plus mr-2" aria-hidden="true"></i>Spieler verwalten
                        </a>
                        <a href="export.php?team_id=<?= $selectedTeamId ?>&format=print" 
                           class="flex items-center justify-center bg-green-500 text-white p-2 rounded hover:bg-green-600 w-full sm:w-auto"
                           aria-label="Liste drucken">
                            <i class="fas fa-print mr-2" aria-hidden="true"></i>Drucken
                        </a>
                        <a href="export.php?team_id=<?= $selectedTeamId ?>&format=csv" 
                           class="flex items-center justify-center bg-orange-500 text-white p-2 rounded hover:bg-orange-600 w-full sm:w-auto"
                           aria-label="Als CSV exportieren">
                            <i class="fas fa-file-csv mr-2" aria-hidden="true"></i>CSV
                        </a>
                        <a href="team_qrcode.php?team_id=<?= $selectedTeamId ?>" 
                           class="flex items-center justify-center bg-purple-500 text-white p-2 rounded hover:bg-purple-600 w-full sm:w-auto"
                           aria-label="QR-Code generieren">
                            <i class="fas fa-qrcode mr-2" aria-hidden="true"></i>QR-Code
                        </a>
                    </div>
                    
                    <!-- Mobile-optimized player list -->
                    <div class="space-y-4">
                        <?php if (empty($players)): ?>
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4" role="alert">
                                <p class="text-yellow-700">Keine Spieler in diesem Team vorhanden. Bitte fügen Sie Spieler hinzu.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($players as $player): ?>
                                <div class="bg-white rounded-lg shadow-md p-4" id="player-<?= $player['id'] ?>">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 bg-orange-200 rounded-full flex items-center justify-center mr-3" aria-hidden="true">
                                                <i class="fas fa-user text-orange-600"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-medium"><?= e($player['first_name']) ?> <?= e($player['last_name']) ?></h3>
                                                <p class="text-sm text-gray-600">
                                                    <?= !empty($player['jersey_number']) ? "#" . e($player['jersey_number']) : '' ?>
                                                    <?= !empty($player['position']) ? ' - ' . e($player['position']) : '' ?>
                                                </p>
                                            </div>
                                        </div>
                                        <a href="emergency_contacts.php?player_id=<?= $player['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-900"
                                           aria-label="Notfallkontakte verwalten für <?= e($player['first_name']) ?> <?= e($player['last_name']) ?>">
                                            <i class="fas fa-phone-alt" aria-hidden="true"></i>
                                            <span class="ml-1"><?= count($player['emergency_contacts'] ?? []) ?></span>
                                        </a>
                                    </div>
                                    
                                    <?php if (empty($player['emergency_contacts'])): ?>
                                        <div class="text-red-600 text-sm" role="alert">Keine Notfallkontakte</div>
                                    <?php else: ?>
                                        <div class="text-sm space-y-2">
                                            <?php foreach ($player['emergency_contacts'] as $contact): ?>
                                                <div class="flex justify-between items-center border-t pt-2">
                                                    <div>
                                                        <div><?= e($contact['contact_name']) ?></div>
                                                        <div class="text-xs text-gray-600"><?= e($contact['relationship']) ?></div>
                                                    </div>
                                                    <a href="tel:<?= e($contact['phone_number']) ?>" 
                                                       class="text-orange-600"
                                                       aria-label="Anrufen: <?= e($contact['phone_number']) ?>">
                                                        <?= e($contact['phone_number']) ?>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4" role="alert">
                        <p class="text-yellow-700">Bitte wählen Sie ein Team aus.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($activeTab === 'teams' && $isAdmin): ?>
            <!-- Teams Tab (Admin Only) -->
            <div id="teams-panel" 
                 role="tabpanel" 
                 aria-labelledby="teams-tab" 
                 class="tab-pane active">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 sm:mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold mb-3 sm:mb-0">Teamverwaltung</h2>
                    <a href="teams.php?action=new" 
                       class="flex items-center justify-center bg-orange-500 text-white p-2 rounded hover:bg-orange-600 w-full sm:w-auto"
                       aria-label="Neues Team hinzufügen">
                        <i class="fas fa-plus mr-2" aria-hidden="true"></i>Team hinzufügen
                    </a>
                </div>

                <!-- Mobile-friendly Teams List -->
                <?php if (empty($teams)): ?>
                    <div class="bg-white rounded-lg shadow-md p-4 text-center text-gray-500" role="alert">
                        Keine Teams vorhanden
                    </div>
                <?php else: ?>
                    <!-- Show card layout on mobile, table on larger screens -->
                    <div class="block sm:hidden space-y-4">
                        <?php foreach ($teams as $team): ?>
                            <div class="bg-white rounded-lg shadow-md p-4" id="team-mobile-<?= $team['id'] ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-medium text-lg"><?= e($team['name']) ?></h3>
                                    <div class="flex gap-2">
                                        <a href="team_users.php?team_id=<?= $team['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-900" 
                                           title="Benutzer verwalten"
                                           aria-label="Benutzer verwalten für Team <?= e($team['name']) ?>">
                                            <i class="fas fa-users" aria-hidden="true"></i>
                                        </a>
                                        <a href="players.php?team_id=<?= $team['id'] ?>" 
                                           class="text-green-600 hover:text-green-900" 
                                           title="Spieler verwalten"
                                           aria-label="Spieler verwalten für Team <?= e($team['name']) ?>">
                                            <i class="fas fa-basketball-ball" aria-hidden="true"></i>
                                        </a>
                                        <a href="teams.php?action=edit&team_id=<?= $team['id'] ?>" 
                                           class="text-orange-600 hover:text-orange-900" 
                                           title="Bearbeiten"
                                           aria-label="Team <?= e($team['name']) ?> bearbeiten">
                                            <i class="fas fa-edit" aria-hidden="true"></i>
                                        </a>
                                        <a href="teams.php?action=delete&team_id=<?= $team['id'] ?>&token=<?= $csrf_token ?>" 
                                           class="text-red-600 hover:text-red-900 delete-confirm" 
                                           title="Löschen"
                                           aria-label="Team <?= e($team['name']) ?> löschen"
                                           data-item="Team">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="text-sm">
                                    <div class="flex justify-between py-1 border-b">
                                        <span class="text-gray-600">Kategorie:</span>
                                        <span><?= e($team['category']) ?></span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b">
                                        <span class="text-gray-600">Spieler:</span>
                                        <span><?= (int)$team['player_count'] ?></span>
                                    </div>
                                    <div class="flex justify-between py-1">
                                        <span class="text-gray-600">Trainer:</span>
                                        <span><?= e($team['coach_name'] ?? 'Nicht zugewiesen') ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Table layout for larger screens -->
                    <div class="hidden sm:block">
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200" aria-label="Teams">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teamname</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategorie</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spieleranzahl</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trainer</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($teams as $team): ?>
                                            <tr id="team-<?= $team['id'] ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= e($team['name']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= e($team['category']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= (int)$team['player_count'] ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900"><?= e($team['coach_name'] ?? 'Nicht zugewiesen') ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <a href="team_users.php?team_id=<?= $team['id'] ?>" 
                                                       class="text-blue-600 hover:text-blue-900 mr-3" 
                                                       title="Benutzer verwalten"
                                                       aria-label="Benutzer verwalten für Team <?= e($team['name']) ?>">
                                                        <i class="fas fa-users" aria-hidden="true"></i>
                                                    </a>
                                                    <a href="players.php?team_id=<?= $team['id'] ?>" 
                                                       class="text-green-600 hover:text-green-900 mr-3" 
                                                       title="Spieler verwalten"
                                                       aria-label="Spieler verwalten für Team <?= e($team['name']) ?>">
                                                        <i class="fas fa-basketball-ball" aria-hidden="true"></i>
                                                    </a>
                                                    <a href="teams.php?action=edit&team_id=<?= $team['id'] ?>" 
                                                       class="text-orange-600 hover:text-orange-900 mr-3" 
                                                       title="Bearbeiten"
                                                       aria-label="Team <?= e($team['name']) ?> bearbeiten">
                                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                                    </a>
                                                    <a href="teams.php?action=delete&team_id=<?= $team['id'] ?>&token=<?= $csrf_token ?>" 
                                                       class="text-red-600 hover:text-red-900 delete-confirm" 
                                                       title="Löschen"
                                                       aria-label="Team <?= e($team['name']) ?> löschen"
                                                       data-item="Team">
                                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($activeTab === 'users' && $isAdmin): ?>
            <!-- Users Tab (Admin Only) -->
            <div id="users-panel" 
                 role="tabpanel" 
                 aria-labelledby="users-tab" 
                 class="tab-pane active">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 sm:mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold mb-3 sm:mb-0">Benutzerverwaltung</h2>
                    <a href="users.php?action=new" 
                       class="flex items-center justify-center bg-orange-500 text-white p-2 rounded hover:bg-orange-600 w-full sm:w-auto"
                       aria-label="Neuen Benutzer hinzufügen">
                        <i class="fas fa-plus mr-2" aria-hidden="true"></i>Benutzer hinzufügen
                    </a>
                </div>

                <!-- Mobile-friendly Users List -->
                <?php 
                try {
                    // Direct query to get all users
                    $allUsers = db()->fetchAll("SELECT * FROM users ORDER BY name");
                } catch (Exception $e) {
                    handleError($e, 'Fehler beim Laden der Benutzer');
                    $allUsers = [];
                }
                
                if (empty($allUsers)): 
                ?>
                    <div class="bg-white rounded-lg shadow-md p-4 text-center text-gray-500" role="alert">
                        Keine Benutzer vorhanden
                    </div>
                <?php else: ?>
                    <!-- Show card layout on mobile, table on larger screens -->
                    <div class="block sm:hidden space-y-4">
                        <?php foreach ($allUsers as $user): ?>
                            <div class="bg-white rounded-lg shadow-md p-4" id="user-mobile-<?= $user['id'] ?>">
                                <div class="flex items-center mb-3">
                                    <div class="h-10 w-10 bg-orange-200 rounded-full flex items-center justify-center mr-3" aria-hidden="true">
                                        <i class="fas fa-user text-orange-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-medium"><?= e($user['name']) ?></h3>
                                        <p class="text-sm text-gray-600"><?= e($user['email']) ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center mb-3">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        if ($user['role'] === 'admin') echo 'bg-green-100 text-green-800';
                                        elseif ($user['role'] === 'trainer') echo 'bg-blue-100 text-blue-800';
                                        else echo 'bg-gray-100 text-gray-800';
                                        ?>">
                                        <?= ucfirst(e($user['role'])) ?>
                                    </span>
                                    
                                    <div class="flex gap-2">
                                        <a href="users.php?action=edit&user_id=<?= $user['id'] ?>" 
                                           class="text-orange-600 hover:text-orange-900" 
                                           title="Bearbeiten"
                                           aria-label="Benutzer <?= e($user['name']) ?> bearbeiten">
                                            <i class="fas fa-edit" aria-hidden="true"></i>
                                        </a>
                                        <?php if ($user['id'] !== $userId): ?>
                                            <a href="users.php?action=delete&user_id=<?= $user['id'] ?>&token=<?= $csrf_token ?>" 
                                               class="text-red-600 hover:text-red-900 delete-confirm" 
                                               title="Löschen"
                                               aria-label="Benutzer <?= e($user['name']) ?> löschen"
                                               data-item="Benutzer">
                                                <i class="fas fa-trash" aria-hidden="true"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="text-sm">
                                    <div class="flex justify-between py-1">
                                        <span class="text-gray-600">Team-Zugang:</span>
                                        <span>
                                            <?php
                                            try {
                                                if ($user['role'] === 'admin') {
                                                    echo 'Alle Teams';
                                                } else {
                                                    $userTeams = db()->fetchAll("
                                                        SELECT t.name 
                                                        FROM teams t
                                                        JOIN user_team ut ON t.id = ut.team_id
                                                        WHERE ut.user_id = ?
                                                    ", [$user['id']]);
                                                    
                                                    $teamNames = array_column($userTeams, 'name');
                                                    echo empty($teamNames) ? 'Keine Teams' : implode(', ', $teamNames);
                                                }
                                            } catch (Exception $e) {
                                                echo 'Fehler beim Laden der Teams';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Table layout for larger screens -->
                    <div class="hidden sm:block">
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200" aria-label="Benutzer">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">E-Mail</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rolle</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team-Zugang</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($allUsers as $user): ?>
                                            <tr id="user-<?= $user['id'] ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="h-10 w-10 flex-shrink-0 bg-orange-200 rounded-full flex items-center justify-center" aria-hidden="true">
                                                            <i class="fas fa-user text-orange-600"></i>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900"><?= e($user['name']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900"><?= e($user['email']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        <?php 
                                                        if ($user['role'] === 'admin') echo 'bg-green-100 text-green-800';
                                                        elseif ($user['role'] === 'trainer') echo 'bg-blue-100 text-blue-800';
                                                        else echo 'bg-gray-100 text-gray-800';
                                                        ?>">
                                                        <?= ucfirst(e($user['role'])) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php
                                                    try {
                                                        if ($user['role'] === 'admin') {
                                                            echo 'Alle Teams';
                                                        } else {
                                                            $userTeams = db()->fetchAll("
                                                                SELECT t.name 
                                                                FROM teams t
                                                                JOIN user_team ut ON t.id = ut.team_id
                                                                WHERE ut.user_id = ?
                                                            ", [$user['id']]);
                                                            
                                                            $teamNames = array_column($userTeams, 'name');
                                                            echo empty($teamNames) ? 'Keine Teams' : implode(', ', $teamNames);
                                                        }
                                                    } catch (Exception $e) {
                                                        echo 'Fehler beim Laden der Teams';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <a href="users.php?action=edit&user_id=<?= $user['id'] ?>" 
                                                       class="text-orange-600 hover:text-orange-900 mr-3" 
                                                       title="Bearbeiten"
                                                       aria-label="Benutzer <?= e($user['name']) ?> bearbeiten">
                                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                                    </a>
                                                    <?php if ($user['id'] !== $userId): ?>
                                                        <a href="users.php?action=delete&user_id=<?= $user['id'] ?>&token=<?= $csrf_token ?>" 
                                                           class="text-red-600 hover:text-red-900 delete-confirm" 
                                                           title="Löschen"
                                                           aria-label="Benutzer <?= e($user['name']) ?> löschen"
                                                           data-item="Benutzer">
                                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Enhanced JavaScript for team selection -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const teamSelect = document.getElementById('team-select');
    if (teamSelect) {
        teamSelect.addEventListener('change', function() {
            // Clear current URL parameters and set new ones to avoid parameter duplication
            const baseUrl = window.location.pathname; // Gets just the 'dashboard.php' part
            window.location.href = baseUrl + '?tab=emergency-contacts&team_id=' + this.value;
        });
    }
    
    // Add enhanced keyboard navigation for tables
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            row.setAttribute('tabindex', '0');
            row.addEventListener('keydown', (e) => {
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (index < rows.length - 1) {
                            rows[index + 1].focus();
                        }
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        if (index > 0) {
                            rows[index - 1].focus();
                        }
                        break;
                }
            });
        });
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>