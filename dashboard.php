<?php
require_once 'functions.php';
require_once 'templates/header.php';

// Überprüfen, ob Benutzer angemeldet ist
if (!isLoggedIn()) {
    redirect('index.php');
    exit;
}

// Benutzerinformationen abrufen
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userRole = $_SESSION['user_role'];
$isAdmin = hasRole('admin');

// Aktives Tab bestimmen
$activeTab = $_GET['tab'] ?? 'emergency-contacts';
$validTabs = ['emergency-contacts', 'teams', 'users'];

if (!in_array($activeTab, $validTabs) || ($activeTab !== 'emergency-contacts' && !$isAdmin)) {
    $activeTab = 'emergency-contacts';
}

// Ausgewähltes Team bestimmen
$selectedTeamId = $_GET['team_id'] ?? null;
$teams = getTeamsForUser($userId);

// Wenn kein Team ausgewählt wurde, das erste Team als Standard verwenden
if (empty($selectedTeamId) && !empty($teams)) {
    $selectedTeamId = $teams[0]['id'];
}

// Daten für das aktuelle Tab laden
switch ($activeTab) {
    case 'emergency-contacts':
        $players = [];
        if ($selectedTeamId) {
            $players = getPlayersForTeam($selectedTeamId);
            
            // Notfallkontakte für jeden Spieler abrufen
            foreach ($players as &$player) {
                $player['emergency_contacts'] = getEmergencyContactsForPlayer($player['id']);
            }
        }
        break;
        
    case 'teams':
        // Alle Teams mit zusätzlichen Informationen für Admins laden
        if ($isAdmin) {
            $teamsWithDetails = [];
            foreach ($teams as $team) {
                $playerCount = db()->fetchOne("SELECT COUNT(*) as count FROM players WHERE team_id = ?", [$team['id']])['count'] ?? 0;
                
                $coach = null;
                if ($team['coach_id']) {
                    $coach = db()->fetchOne("SELECT name FROM users WHERE id = ?", [$team['coach_id']]);
                }
                
                $teamsWithDetails[] = [
                    'id' => $team['id'],
                    'name' => $team['name'],
                    'category' => $team['category'],
                    'player_count' => $playerCount,
                    'coach_name' => $coach ? $coach['name'] : 'Nicht zugewiesen'
                ];
            }
            $teams = $teamsWithDetails;
        }
        break;
        
    case 'users':
        if ($isAdmin) {
            // Alle Benutzer für Admins laden
            $users = db()->fetchAll("SELECT * FROM users ORDER BY name");
            
            // Teams für jeden Benutzer abrufen
            foreach ($users as &$user) {
                $userTeams = db()->fetchAll("
                    SELECT t.name 
                    FROM teams t
                    JOIN user_team ut ON t.id = ut.team_id
                    WHERE ut.user_id = ?
                ", [$user['id']]);
                
                $teamNames = array_column($userTeams, 'name');
                $user['teams'] = empty($teamNames) ? 'Keine Teams' : implode(', ', $teamNames);
                
                if ($user['role'] === 'admin') {
                    $user['teams'] = 'Alle Teams';
                }
            }
        }
        break;
}

// CSRF-Token für Formulare generieren
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto px-4 py-6">
    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <ul class="flex flex-wrap -mb-px">
            <li class="mr-2">
                <a href="?tab=emergency-contacts" class="inline-block p-4 rounded-t-lg border-b-2 <?= $activeTab === 'emergency-contacts' ? 'border-orange-500 text-orange-500' : 'border-transparent hover:border-gray-300' ?>">
                    <i class="fas fa-phone-alt mr-2"></i>Notfallkontakte
                </a>
            </li>
            
            <?php if ($isAdmin): ?>
                <li class="mr-2">
                    <a href="?tab=teams" class="inline-block p-4 rounded-t-lg border-b-2 <?= $activeTab === 'teams' ? 'border-orange-500 text-orange-500' : 'border-transparent hover:border-gray-300' ?>">
                        <i class="fas fa-users mr-2"></i>Teams
                    </a>
                </li>
                <li class="mr-2">
                    <a href="?tab=users" class="inline-block p-4 rounded-t-lg border-b-2 <?= $activeTab === 'users' ? 'border-orange-500 text-orange-500' : 'border-transparent hover:border-gray-300' ?>">
                        <i class="fas fa-user-cog mr-2"></i>Benutzerverwaltung
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Tab Content -->
    <div id="tab-content">
        <?php if ($activeTab === 'emergency-contacts'): ?>
            <!-- Emergency Contacts Tab -->
            <div id="emergency-contacts" class="tab-pane active">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Notfallkontakte</h2>
                    <div class="flex items-center space-x-4">
                        <?php if (!empty($teams)): ?>
                            <div>
                                <label for="team-select" class="block text-sm font-medium text-gray-700">Team auswählen:</label>
                                <select id="team-select" name="team_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-orange-500 focus:border-orange-500 sm:text-sm rounded-md">
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?= $team['id'] ?>" <?= $selectedTeamId == $team['id'] ? 'selected' : '' ?>>
                                            <?= e($team['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
            </div>
        <?php elseif ($activeTab === 'teams' && $isAdmin): ?>
            <!-- Teams Tab (Admin Only) -->
            <div id="teams" class="tab-pane active">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Teamverwaltung</h2>
                    <a href="teams.php?action=new" class="bg-orange-500 text-white p-2 rounded hover:bg-orange-600">
                        <i class="fas fa-plus mr-2"></i>Team hinzufügen
                    </a>
                </div>

                <!-- Teams List -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
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
                            <?php if (empty($teams)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">Keine Teams vorhanden</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($teams as $team): ?>
                                    <tr>
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
                                            <a href="team_users.php?team_id=<?= $team['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="Benutzer verwalten">
                                                <i class="fas fa-users"></i>
                                            </a>
                                            <a href="players.php?team_id=<?= $team['id'] ?>" class="text-green-600 hover:text-green-900 mr-3" title="Spieler verwalten">
                                                <i class="fas fa-basketball-ball"></i>
                                            </a>
                                            <a href="teams.php?action=edit&team_id=<?= $team['id'] ?>" class="text-orange-600 hover:text-orange-900 mr-3" title="Bearbeiten">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="teams.php?action=delete&team_id=<?= $team['id'] ?>&token=<?= $csrf_token ?>" 
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
        <?php elseif ($activeTab === 'users' && $isAdmin): ?>
            <!-- Users Tab (Admin Only) -->
            <div id="users" class="tab-pane active">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Benutzerverwaltung</h2>
                    <a href="users.php?action=new" class="bg-orange-500 text-white p-2 rounded hover:bg-orange-600">
                        <i class="fas fa-plus mr-2"></i>Benutzer hinzufügen
                    </a>
                </div>

                <!-- Users List -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
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
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">Keine Benutzer vorhanden</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-10 w-10 flex-shrink-0 bg-orange-200 rounded-full flex items-center justify-center">
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
                                            <?= e($user['teams']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="users.php?action=edit&user_id=<?= $user['id'] ?>" class="text-orange-600 hover:text-orange-900 mr-3" title="Bearbeiten">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] !== $userId): ?>
                                                <a href="users.php?action=delete&user_id=<?= $user['id'] ?>&token=<?= $csrf_token ?>" 
                                                   class="text-red-600 hover:text-red-900 delete-confirm" title="Löschen">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
                        
                        <?php if (!empty($selectedTeamId)): ?>
    <div class="flex space-x-2">
        <a href="players.php?team_id=<?= $selectedTeamId ?>" class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">
            <i class="fas fa-user-plus mr-2"></i>Spieler verwalten
        </a>
        <a href="export.php?team_id=<?= $selectedTeamId ?>&format=print" class="bg-green-500 text-white p-2 rounded hover:bg-green-600">
            <i class="fas fa-print mr-2"></i>Drucken
        </a>
        <a href="export.php?team_id=<?= $selectedTeamId ?>&format=csv" class="bg-orange-500 text-white p-2 rounded hover:bg-orange-600">
            <i class="fas fa-file-csv mr-2"></i>CSV
        </a>
        <a href="team_qrcode.php?team_id=<?= $selectedTeamId ?>" class="bg-purple-500 text-white p-2 rounded hover:bg-purple-600">
            <i class="fas fa-qrcode mr-2"></i>QR-Code
        </a>
    </div>
<?php endif; ?>