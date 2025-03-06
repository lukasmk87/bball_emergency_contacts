<?php
require_once 'functions.php';
require_once 'templates/header.php';

// Überprüfen, ob Benutzer angemeldet ist und Admin-Rechte hat
if (!isLoggedIn() || !hasRole('admin')) {
    setMessage('red', 'Sie haben keine Berechtigung, diese Seite aufzurufen.');
    redirect('dashboard.php');
    exit;
}

// Team-ID aus der URL abrufen
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// Überprüfen, ob das Team existiert
$team = db()->fetchOne("SELECT * FROM teams WHERE id = ?", [$teamId]);

if (!$team) {
    setMessage('red', 'Team nicht gefunden.');
    redirect("teams.php");
    exit;
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        // Bestehende Zuordnungen löschen
        db()->execute("DELETE FROM user_team WHERE team_id = ?", [$teamId]);
        
        // Ausgewählte Benutzer-IDs
        $selectedUserIds = $_POST['user_ids'] ?? [];
        
        // Neue Zuordnungen erstellen
        foreach ($selectedUserIds as $userId) {
            db()->execute(
                "INSERT INTO user_team (user_id, team_id) VALUES (?, ?)",
                [(int)$userId, $teamId]
            );
        }
        
        setMessage('green', 'Benutzerzuordnungen wurden erfolgreich aktualisiert.');
        logActivity($_SESSION['user_id'], 'team_users_update', "Benutzerzuordnungen für Team $teamId aktualisiert");
        redirect("team_users.php?team_id=$teamId");
        exit;
    }
}

// Trainer-Änderung verarbeiten
if (isset($_GET['action']) && $_GET['action'] === 'set_coach' && isset($_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];
    
    if (!validateCSRFToken($_GET['token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        // Benutzer prüfen
        $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        
        if ($user && ($user['role'] === 'trainer' || $user['role'] === 'admin')) {
            // Trainer aktualisieren
            $updated = db()->execute(
                "UPDATE teams SET coach_id = ? WHERE id = ?",
                [$userId, $teamId]
            );
            
            if ($updated) {
                setMessage('green', 'Trainer wurde erfolgreich aktualisiert.');
                logActivity($_SESSION['user_id'], 'team_coach_update', "Trainer für Team $teamId auf Benutzer $userId gesetzt");
            } else {
                setMessage('red', 'Trainer konnte nicht aktualisiert werden.');
            }
        } else {
            setMessage('red', 'Ungültiger Benutzer für die Trainerrolle.');
        }
    }
    
    redirect("team_users.php?team_id=$teamId");
    exit;
}

// Trainer entfernen
if (isset($_GET['action']) && $_GET['action'] === 'remove_coach') {
    if (!validateCSRFToken($_GET['token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        // Trainer entfernen
        $updated = db()->execute(
            "UPDATE teams SET coach_id = NULL WHERE id = ?",
            [$teamId]
        );
        
        if ($updated) {
            setMessage('green', 'Trainer wurde erfolgreich entfernt.');
            logActivity($_SESSION['user_id'], 'team_coach_remove', "Trainer für Team $teamId entfernt");
        } else {
            setMessage('red', 'Trainer konnte nicht entfernt werden.');
        }
    }
    
    redirect("team_users.php?team_id=$teamId");
    exit;
}

// Alle Benutzer laden
$users = db()->fetchAll("
    SELECT u.*, 
           CASE WHEN ut.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_assigned,
           CASE WHEN u.id = t.coach_id THEN 1 ELSE 0 END AS is_coach
    FROM users u
    LEFT JOIN user_team ut ON u.id = ut.user_id AND ut.team_id = ?
    LEFT JOIN teams t ON t.id = ? AND t.coach_id = u.id
    WHERE u.role != 'admin' 
    ORDER BY u.name
", [$teamId, $teamId]);

// Aktuellen Trainer finden
$coach = null;
if ($team['coach_id']) {
    $coach = db()->fetchOne("SELECT id, name FROM users WHERE id = ?", [$team['coach_id']]);
}

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold">Benutzer für Team: <?= e($team['name']) ?></h2>
            <p class="text-gray-600">Weisen Sie Benutzer diesem Team zu</p>
        </div>
        <div>
            <a href="teams.php" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
                <i class="fas fa-arrow-left mr-2"></i>Zurück zu Teams
            </a>
        </div>
    </div>
    
    <!-- Trainer-Bereich -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-xl font-bold mb-4">Teamtrainer</h3>
        
        <?php if ($coach): ?>
            <div class="flex items-center justify-between bg-gray-100 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="h-12 w-12 rounded-full bg-orange-200 flex items-center justify-center mr-4">
                        <i class="fas fa-user text-orange-600 text-lg"></i>
                    </div>
                    <div>
                        <div class="font-medium"><?= e($coach['name']) ?></div>
                        <div class="text-sm text-gray-500">Aktueller Trainer</div>
                    </div>
                </div>
                <a href="team_users.php?team_id=<?= $teamId ?>&action=remove_coach&token=<?= $csrf_token ?>" 
                   class="bg-red-100 text-red-600 px-3 py-1 rounded hover:bg-red-200 delete-confirm">
                    <i class="fas fa-user-minus mr-1"></i>Trainer entfernen
                </a>
            </div>
        <?php else: ?>
            <div class="text-gray-600 italic mb-4">Kein Trainer zugewiesen</div>
        <?php endif; ?>
    </div>
    
    <!-- Benutzer-Formular -->
    <form method="POST" action="team_users.php?team_id=<?= $teamId ?>">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b">
                <h3 class="text-lg font-semibold">Benutzer diesem Team zuweisen</h3>
                <p class="text-sm text-gray-600">Wählen Sie die Benutzer aus, die Zugriff auf dieses Team haben sollen</p>
            </div>
            
            <div class="p-6">
                <?php if (empty($users)): ?>
                    <div class="text-gray-500 text-center py-4">Keine Benutzer verfügbar</div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($users as $user): ?>
                            <div class="border rounded-lg p-4 <?= $user['is_assigned'] ? 'bg-orange-50 border-orange-200' : '' ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="user_ids[]" value="<?= $user['id'] ?>" id="user_<?= $user['id'] ?>"
                                               class="h-5 w-5 text-orange-500 focus:ring-orange-400" <?= $user['is_assigned'] ? 'checked' : '' ?>>
                                        <label for="user_<?= $user['id'] ?>" class="ml-2 block">
                                            <span class="font-medium text-gray-900"><?= e($user['name']) ?></span>
                                            <span class="text-sm text-gray-500 block"><?= e($user['email']) ?></span>
                                            <span class="inline-block px-2 py-0.5 mt-1 text-xs rounded-full 
                                                  <?= $user['role'] === 'trainer' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' ?>">
                                                <?= ucfirst(e($user['role'])) ?>
                                            </span>
                                            <?php if ($user['is_coach']): ?>
                                                <span class="inline-block px-2 py-0.5 ml-1 text-xs rounded-full bg-green-100 text-green-800">
                                                    Trainer
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    
                                    <?php if ($user['role'] === 'trainer' && !$user['is_coach']): ?>
                                        <a href="team_users.php?team_id=<?= $teamId ?>&action=set_coach&user_id=<?= $user['id'] ?>&token=<?= $csrf_token ?>" 
                                           class="text-blue-600 hover:text-blue-900 text-sm" title="Als Trainer festlegen">
                                            <i class="fas fa-user-check"></i> Als Trainer festlegen
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="px-6 py-4 bg-gray-50 border-t flex justify-end">
                <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    <i class="fas fa-save mr-2"></i>Zuordnungen speichern
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once 'templates/footer.php'; ?>