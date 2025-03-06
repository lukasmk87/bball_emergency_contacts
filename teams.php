<?php
require_once 'functions.php';
require_once 'templates/header.php';

// Überprüfen, ob Benutzer angemeldet ist und Admin-Rechte hat
if (!isLoggedIn() || !hasRole('admin')) {
    setMessage('red', 'Sie haben keine Berechtigung, diese Seite aufzurufen.');
    redirect('dashboard.php');
    exit;
}

// Aktion basierend auf GET-Parameter
$action = $_GET['action'] ?? '';
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// Team-Formular-Daten
$formData = [
    'id' => 0,
    'name' => '',
    'category' => '',
    'coach_id' => null
];

// Team zum Bearbeiten laden oder Löschen
if ($action === 'edit' && $teamId > 0) {
    $team = db()->fetchOne("SELECT * FROM teams WHERE id = ?", [$teamId]);
    
    if ($team) {
        $formData = $team;
    } else {
        setMessage('red', 'Team nicht gefunden.');
        redirect("teams.php");
        exit;
    }
} elseif ($action === 'delete' && $teamId > 0) {
    // CSRF-Token überprüfen
    if (!validateCSRFToken($_GET['token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        // Prüfen, ob das Team Spieler hat
        $playerCount = db()->fetchOne("SELECT COUNT(*) as count FROM players WHERE team_id = ?", [$teamId])['count'] ?? 0;
        
        if ($playerCount > 0) {
            setMessage('red', 'Das Team hat noch Spieler. Bitte entfernen Sie zuerst alle Spieler des Teams.');
        } else {
            // Team löschen
            db()->execute("DELETE FROM user_team WHERE team_id = ?", [$teamId]);
            $deleted = db()->execute("DELETE FROM teams WHERE id = ?", [$teamId]);
            
            if ($deleted) {
                setMessage('green', 'Team wurde erfolgreich gelöscht.');
                logActivity($_SESSION['user_id'], 'team_delete', "Team mit ID $teamId gelöscht");
            } else {
                setMessage('red', 'Team konnte nicht gelöscht werden.');
            }
        }
    }
    
    redirect("teams.php");
    exit;
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        $teamId = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $coachId = !empty($_POST['coach_id']) ? (int)$_POST['coach_id'] : null;
        
        $errors = [];
        
        // Validierung
        if (empty($name)) {
            $errors[] = 'Teamname ist erforderlich.';
        }
        
        if (empty($category)) {
            $errors[] = 'Kategorie ist erforderlich.';
        }
        
        if (empty($errors)) {
            if ($teamId > 0) {
                // Team aktualisieren
                $updated = db()->execute(
                    "UPDATE teams SET name = ?, category = ?, coach_id = ? WHERE id = ?",
                    [$name, $category, $coachId, $teamId]
                );
                
                if ($updated) {
                    setMessage('green', 'Team wurde erfolgreich aktualisiert.');
                    logActivity($_SESSION['user_id'], 'team_update', "Team mit ID $teamId aktualisiert");
                } else {
                    setMessage('red', 'Team konnte nicht aktualisiert werden.');
                }
            } else {
                // Neues Team hinzufügen
                $newTeamId = db()->insert(
                    "INSERT INTO teams (name, category, coach_id) VALUES (?, ?, ?)",
                    [$name, $category, $coachId]
                );
                
                if ($newTeamId) {
                    setMessage('green', 'Team wurde erfolgreich hinzugefügt.');
                    logActivity($_SESSION['user_id'], 'team_add', "Neues Team mit ID $newTeamId hinzugefügt");
                } else {
                    setMessage('red', 'Team konnte nicht hinzugefügt werden.');
                }
            }
            
            redirect("teams.php");
            exit;
        }
    }
}

// Alle Teams laden
$teams = db()->fetchAll("
    SELECT t.*, 
           COUNT(p.id) AS player_count,
           u.name AS coach_name
    FROM teams t
    LEFT JOIN players p ON t.id = p.team_id
    LEFT JOIN users u ON t.coach_id = u.id
    GROUP BY t.id
    ORDER BY t.name
");

// Alle Trainer (für das Formular) laden
$coaches = db()->fetchAll("
    SELECT id, name
    FROM users
    WHERE role = 'trainer' OR role = 'admin'
    ORDER BY name
");

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold">Teamverwaltung</h2>
            <p class="text-gray-600">Verwalten Sie alle Teams</p>
        </div>
        <div>
            <a href="dashboard.php" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 mr-2">
                <i class="fas fa-arrow-left mr-2"></i>Zurück zum Dashboard
            </a>
            <a href="teams.php?action=new" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                <i class="fas fa-plus mr-2"></i>Neues Team
            </a>
        </div>
    </div>
    
    <?php if ($action === 'edit' || $action === 'new'): ?>
    <!-- Team-Formular -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-xl font-bold mb-4"><?= $action === 'edit' ? 'Team bearbeiten' : 'Neues Team hinzufügen' ?></h3>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                <ul class="list-disc pl-4">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="teams.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="team_id" value="<?= $formData['id'] ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-gray-700 mb-2">Teamname</label>
                    <input type="text" id="name" name="name" value="<?= e($formData['name']) ?>" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500 text-gray-800" required>
                </div>
                
                <div>
                    <label for="category" class="block text-gray-700 mb-2">Kategorie</label>
                    <select id="category" name="category" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500 text-gray-800" required>
                        <option value="" <?= empty($formData['category']) ? 'selected' : '' ?>>Bitte wählen</option>
                        <option value="Jugend" <?= $formData['category'] === 'Jugend' ? 'selected' : '' ?>>Jugend</option>
                        <option value="Senioren" <?= $formData['category'] === 'Senioren' ? 'selected' : '' ?>>Senioren</option>
                        <option value="Damen" <?= $formData['category'] === 'Damen' ? 'selected' : '' ?>>Damen</option>
                        <option value="Herren" <?= $formData['category'] === 'Herren' ? 'selected' : '' ?>>Herren</option>
                        <option value="Mixed" <?= $formData['category'] === 'Mixed' ? 'selected' : '' ?>>Mixed</option>
                    </select>
                </div>
                
                <div>
                    <label for="coach_id" class="block text-gray-700 mb-2">Trainer</label>
                    <select id="coach_id" name="coach_id" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500 text-gray-800">
                        <option value="">Kein Trainer zugewiesen</option>
                        <?php foreach ($coaches as $coach): ?>
                            <option value="<?= $coach['id'] ?>" <?= $formData['coach_id'] == $coach['id'] ? 'selected' : '' ?>>
                                <?= e($coach['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-between mt-6">
                <a href="teams.php" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
                    Abbrechen
                </a>
                <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    <?= $action === 'edit' ? 'Team aktualisieren' : 'Team hinzufügen' ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Teams-Tabelle -->
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

<?php require_once 'templates/footer.php'; ?>