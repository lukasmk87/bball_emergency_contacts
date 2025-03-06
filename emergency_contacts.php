<?php
require_once 'functions.php';
require_once 'templates/header.php';

// Überprüfen, ob Benutzer angemeldet ist
if (!isLoggedIn()) {
    redirect('index.php');
    exit;
}

// Spieler-ID aus der URL abrufen
$playerId = isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0;

// Überprüfen, ob der Spieler existiert und der Benutzer Zugriff hat
$player = db()->fetchOne("
    SELECT p.*, t.name AS team_name, t.id AS team_id
    FROM players p
    JOIN teams t ON p.team_id = t.id
    WHERE p.id = ?
", [$playerId]);

if (!$player) {
    setMessage('red', 'Spieler nicht gefunden.');
    redirect('dashboard.php');
    exit;
}

$teamId = $player['team_id'];

// Überprüfen, ob der Benutzer Zugriff auf dieses Team hat
$teams = getTeamsForUser($_SESSION['user_id']);
$hasAccess = false;

foreach ($teams as $team) {
    if ($team['id'] == $teamId) {
        $hasAccess = true;
        break;
    }
}

if (!hasRole('admin') && !$hasAccess) {
    setMessage('red', 'Sie haben keinen Zugriff auf diesen Spieler.');
    redirect('dashboard.php');
    exit;
}

// Aktion basierend auf GET-Parameter
$action = $_GET['action'] ?? '';
$contactId = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;

// Kontakt-Formular-Daten
$formData = [
    'id' => 0,
    'contact_name' => '',
    'phone_number' => '',
    'relationship' => ''
];

// Kontakt zum Bearbeiten laden oder Löschen
if ($action === 'edit' && $contactId > 0) {
    $contact = db()->fetchOne("SELECT * FROM emergency_contacts WHERE id = ? AND player_id = ?", [$contactId, $playerId]);
    
    if ($contact) {
        $formData = $contact;
    } else {
        setMessage('red', 'Notfallkontakt nicht gefunden.');
        redirect("emergency_contacts.php?player_id=$playerId");
        exit;
    }
} elseif ($action === 'delete' && $contactId > 0) {
    // CSRF-Token überprüfen
    if (!validateCSRFToken($_GET['token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        // Notfallkontakt löschen
        $deleted = db()->execute("DELETE FROM emergency_contacts WHERE id = ? AND player_id = ?", [$contactId, $playerId]);
        
        if ($deleted) {
            setMessage('green', 'Notfallkontakt wurde erfolgreich gelöscht.');
            logActivity($_SESSION['user_id'], 'contact_delete', "Notfallkontakt mit ID $contactId gelöscht");
        } else {
            setMessage('red', 'Notfallkontakt konnte nicht gelöscht werden.');
        }
    }
    
    redirect("emergency_contacts.php?player_id=$playerId");
    exit;
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        $contactId = isset($_POST['contact_id']) ? (int)$_POST['contact_id'] : 0;
        $contactName = trim($_POST['contact_name'] ?? '');
        $phoneNumber = trim($_POST['phone_number'] ?? '');
        $relationship = trim($_POST['relationship'] ?? '');
        
        $errors = [];
        
        // Validierung
        if (empty($contactName)) {
            $errors[] = 'Name des Kontakts ist erforderlich.';
        }
        
        if (empty($phoneNumber)) {
            $errors[] = 'Telefonnummer ist erforderlich.';
        }
        
        if (empty($relationship)) {
            $errors[] = 'Beziehung zum Spieler ist erforderlich.';
        }
        
        if (empty($errors)) {
            if ($contactId > 0) {
                // Kontakt aktualisieren
                $updated = db()->execute(
                    "UPDATE emergency_contacts SET contact_name = ?, phone_number = ?, relationship = ? WHERE id = ? AND player_id = ?",
                    [$contactName, $phoneNumber, $relationship, $contactId, $playerId]
                );
                
                if ($updated) {
                    setMessage('green', 'Notfallkontakt wurde erfolgreich aktualisiert.');
                    logActivity($_SESSION['user_id'], 'contact_update', "Notfallkontakt mit ID $contactId aktualisiert");
                } else {
                    setMessage('red', 'Notfallkontakt konnte nicht aktualisiert werden.');
                }
            } else {
                // Neuen Kontakt hinzufügen
                $newContactId = db()->insert(
                    "INSERT INTO emergency_contacts (player_id, contact_name, phone_number, relationship) VALUES (?, ?, ?, ?)",
                    [$playerId, $contactName, $phoneNumber, $relationship]
                );
                
                if ($newContactId) {
                    setMessage('green', 'Notfallkontakt wurde erfolgreich hinzugefügt.');
                    logActivity($_SESSION['user_id'], 'contact_add', "Neuer Notfallkontakt mit ID $newContactId hinzugefügt");
                } else {
                    setMessage('red', 'Notfallkontakt konnte nicht hinzugefügt werden.');
                }
            }
            
            redirect("emergency_contacts.php?player_id=$playerId");
            exit;
        }
    }
}

// Notfallkontakte für den Spieler abrufen
$contacts = getEmergencyContactsForPlayer($playerId);

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold">Notfallkontakte: <?= e($player['first_name']) ?> <?= e($player['last_name']) ?></h2>
            <p class="text-gray-600">Team: <?= e($player['team_name']) ?></p>
        </div>
        <div>
            <a href="players.php?team_id=<?= $teamId ?>" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 mr-2">
                <i class="fas fa-arrow-left mr-2"></i>Zurück zu Spielern
            </a>
            <a href="emergency_contacts.php?player_id=<?= $playerId ?>&action=new" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                <i class="fas fa-plus mr-2"></i>Neuer Kontakt
            </a>
        </div>
    </div>
    
    <?php if ($action === 'edit' || $action === 'new'): ?>
    <!-- Kontakt-Formular -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-xl font-bold mb-4"><?= $action === 'edit' ? 'Notfallkontakt bearbeiten' : 'Neuen Notfallkontakt hinzufügen' ?></h3>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                <ul class="list-disc pl-4">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="emergency_contacts.php?player_id=<?= $playerId ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="contact_id" value="<?= $formData['id'] ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="contact_name" class="block text-gray-700 mb-2">Name des Kontakts</label>
                    <input type="text" id="contact_name" name="contact_name" value="<?= e($formData['contact_name']) ?>" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>
                
                <div>
                    <label for="phone_number" class="block text-gray-700 mb-2">Telefonnummer</label>
                    <input type="text" id="phone_number" name="phone_number" value="<?= e($formData['phone_number']) ?>" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                </div>
                
                <div>
                    <label for="relationship" class="block text-gray-700 mb-2">Beziehung zum Spieler</label>
                    <select id="relationship" name="relationship" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500" required>
                        <option value="" <?= empty($formData['relationship']) ? 'selected' : '' ?>>Bitte wählen</option>
                        <option value="Elternteil" <?= $formData['relationship'] === 'Elternteil' ? 'selected' : '' ?>>Elternteil</option>
                        <option value="Mutter" <?= $formData['relationship'] === 'Mutter' ? 'selected' : '' ?>>Mutter</option>
                        <option value="Vater" <?= $formData['relationship'] === 'Vater' ? 'selected' : '' ?>>Vater</option>
                        <option value="Geschwister" <?= $formData['relationship'] === 'Geschwister' ? 'selected' : '' ?>>Geschwister</option>
                        <option value="Großeltern" <?= $formData['relationship'] === 'Großeltern' ? 'selected' : '' ?>>Großeltern</option>
                        <option value="Partner" <?= $formData['relationship'] === 'Partner' ? 'selected' : '' ?>>Partner</option>
                        <option value="Freund" <?= $formData['relationship'] === 'Freund' ? 'selected' : '' ?>>Freund</option>
                        <option value="Sonstige" <?= $formData['relationship'] === 'Sonstige' ? 'selected' : '' ?>>Sonstige</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-between mt-6">
                <a href="emergency_contacts.php?player_id=<?= $playerId ?>" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
                    Abbrechen
                </a>
                <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    <?= $action === 'edit' ? 'Kontakt aktualisieren' : 'Kontakt hinzufügen' ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Kontakte-Tabelle -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefonnummer</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Beziehung</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($contacts)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">Keine Notfallkontakte vorhanden</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= e($contact['contact_name']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="tel:<?= e($contact['phone_number']) ?>" class="text-sm text-orange-600 hover:text-orange-900">
                                    <?= e($contact['phone_number']) ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= e($contact['relationship']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="emergency_contacts.php?player_id=<?= $playerId ?>&action=edit&contact_id=<?= $contact['id'] ?>" 
                                   class="text-orange-600 hover:text-orange-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="emergency_contacts.php?player_id=<?= $playerId ?>&action=delete&contact_id=<?= $contact['id'] ?>&token=<?= $csrf_token ?>" 
                                   class="text-red-600 hover:text-red-900 delete-confirm">
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