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
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Benutzer-Formular-Daten
$formData = [
    'id' => 0,
    'name' => '',
    'email' => '',
    'role' => 'trainer',
    'password' => '',
    'confirm_password' => ''
];

// Benutzer zum Bearbeiten laden oder Löschen
if ($action === 'edit' && $userId > 0) {
    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    
    if ($user) {
        $formData = $user;
        $formData['password'] = '';
        $formData['confirm_password'] = '';
    } else {
        setMessage('red', 'Benutzer nicht gefunden.');
        redirect("users.php");
        exit;
    }
} elseif ($action === 'delete' && $userId > 0) {
    // CSRF-Token überprüfen
    if (!validateCSRFToken($_GET['token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        // Aktuellen Benutzer nicht löschen lassen
        if ($userId === (int)$_SESSION['user_id']) {
            setMessage('red', 'Sie können Ihren eigenen Account nicht löschen.');
        } else {
            // Benutzer-Team-Zuordnungen löschen
            db()->execute("DELETE FROM user_team WHERE user_id = ?", [$userId]);
            
            // Coach-Zuordnungen aktualisieren
            db()->execute("UPDATE teams SET coach_id = NULL WHERE coach_id = ?", [$userId]);
            
            // Benutzer löschen
            $deleted = db()->execute("DELETE FROM users WHERE id = ?", [$userId]);
            
            if ($deleted) {
                setMessage('green', 'Benutzer wurde erfolgreich gelöscht.');
                logActivity($_SESSION['user_id'], 'user_delete', "Benutzer mit ID $userId gelöscht");
            } else {
                setMessage('red', 'Benutzer konnte nicht gelöscht werden.');
            }
        }
    }
    
    redirect("users.php");
    exit;
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'trainer';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        // Validierung
        if (empty($name)) {
            $errors[] = 'Name ist erforderlich.';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        } else {
            // Prüfen, ob E-Mail bereits von einem anderen Benutzer verwendet wird
            $existingUser = db()->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            if ($existingUser) {
                $errors[] = 'Diese E-Mail-Adresse wird bereits verwendet.';
            }
        }
        
        // Passwort validieren (nur bei neuem Benutzer oder wenn geändert)
        $updatePassword = false;
        if ($userId === 0 || !empty($password)) {
            if (empty($password)) {
                $errors[] = 'Passwort ist erforderlich.';
            } elseif (strlen($password) < 8) {
                $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
            }
            
            if ($password !== $confirmPassword) {
                $errors[] = 'Die Passwörter stimmen nicht überein.';
            }
            
            $updatePassword = true;
        }
        
        if (!in_array($role, ['admin', 'trainer', 'manager'])) {
            $errors[] = 'Ungültige Rolle ausgewählt.';
        }
        
        if (empty($errors)) {
            if ($userId > 0) {
                // Benutzer aktualisieren
                if ($updatePassword) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
                    $updated = db()->execute(
                        "UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?",
                        [$name, $email, $role, $hashedPassword, $userId]
                    );
                } else {
                    $updated = db()->execute(
                        "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?",
                        [$name, $email, $role, $userId]
                    );
                }
                
                if ($updated) {
                    setMessage('green', 'Benutzer wurde erfolgreich aktualisiert.');
                    logActivity($_SESSION['user_id'], 'user_update', "Benutzer mit ID $userId aktualisiert");
                } else {
                    setMessage('red', 'Benutzer konnte nicht aktualisiert werden.');
                }
            } else {
                // Neuen Benutzer hinzufügen
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
                $newUserId = db()->insert(
                    "INSERT INTO users (name, email, role, password) VALUES (?, ?, ?, ?)",
                    [$name, $email, $role, $hashedPassword]
                );
                
                if ($newUserId) {
                    setMessage('green', 'Benutzer wurde erfolgreich hinzugefügt.');
                    logActivity($_SESSION['user_id'], 'user_add', "Neuer Benutzer mit ID $newUserId hinzugefügt");
                } else {
                    setMessage('red', 'Benutzer konnte nicht hinzugefügt werden.');
                }
            }
            
            redirect("users.php");
            exit;
        }
    }
}

// Alle Benutzer laden
$users = db()->fetchAll("
    SELECT u.*, 
           COUNT(DISTINCT ut.team_id) AS team_count
    FROM users u
    LEFT JOIN user_team ut ON u.id = ut.user_id
    GROUP BY u.id
    ORDER BY u.name
");

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold">Benutzerverwaltung</h2>
            <p class="text-gray-600">Verwalten Sie alle Benutzer des Systems</p>
        </div>
        <div>
            <a href="dashboard.php" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 mr-2">
                <i class="fas fa-arrow-left mr-2"></i>Zurück zum Dashboard
            </a>
            <a href="users.php?action=new" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                <i class="fas fa-plus mr-2"></i>Neuer Benutzer
            </a>
        </div>
    </div>
    
    <?php if ($action === 'edit' || $action === 'new'): ?>
    <!-- Benutzer-Formular -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h3 class="text-xl font-bold mb-4"><?= $action === 'edit' ? 'Benutzer bearbeiten' : 'Neuen Benutzer hinzufügen' ?></h3>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                <ul class="list-disc pl-4">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="users.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="user_id" value="<?= $formData['id'] ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-gray-700 mb-2">Name</label>
                    <input type="text" id="name" name="name" value="<?= e($formData['name']) ?>" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500 text-gray-800" required>
                </div>
                
                <div>
                    <label for="email" class="block text-gray-700 mb-2">E-Mail</label>
                    <input type="email" id="email" name="email" value="<?= e($formData['email']) ?>" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500 text-gray-800" required>
                </div>
                
                <div>
                    <label for="role" class="block text-gray-700 mb-2">Rolle</label>
                    <select id="role" name="role" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500 text-gray-800" required>
                        <option value="trainer" <?= $formData['role'] === 'trainer' ? 'selected' : '' ?>>Trainer</option>
                        <option value="manager" <?= $formData['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="admin" <?= $formData['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>
                
                <div class="col-span-2">
                    <h4 class="font-medium mb-2"><?= $action === 'edit' ? 'Passwort ändern (optional)' : 'Passwort' ?></h4>
                    <p class="text-sm text-gray-600 mb-2">
                        <?= $action === 'edit' 
                            ? 'Lassen Sie diese Felder leer, wenn Sie das Passwort nicht ändern möchten.' 
                            : 'Vergeben Sie ein sicheres Passwort für den neuen Benutzer.' ?>
                    </p>
                </div>
                
                <div>
                    <label for="password" class="block text-gray-700 mb-2">Passwort</label>
                    <input type="password" id="password" name="password" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500 text-gray-800"
                        <?= $action === 'new' ? 'required' : '' ?>>
                    <p class="text-sm text-gray-500 mt-1">Mindestens 8 Zeichen</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-gray-700 mb-2">Passwort bestätigen</label>
                    <input type="password" id="confirm_password" name="confirm_password" 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-orange-500 text-gray-800"
                        <?= $action === 'new' ? 'required' : '' ?>>
                </div>
            </div>
            
            <div class="flex justify-between mt-6">
                <a href="users.php" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
                    Abbrechen
                </a>
                <button type="submit" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    <?= $action === 'edit' ? 'Benutzer aktualisieren' : 'Benutzer hinzufügen' ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Benutzer-Tabelle -->
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
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="italic">Alle Teams</span>
                                <?php else: ?>
                                    <?= (int)$user['team_count'] ?> Team<?= (int)$user['team_count'] !== 1 ? 's' : '' ?>
                                    <?php if ((int)$user['team_count'] > 0): ?>
                                        <a href="team_users.php?user_id=<?= $user['id'] ?>" class="text-orange-600 hover:text-orange-900 ml-2">
                                            <i class="fas fa-users"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="users.php?action=edit&user_id=<?= $user['id'] ?>" class="text-orange-600 hover:text-orange-900 mr-3" title="Bearbeiten">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($user['id'] !== (int)$_SESSION['user_id']): ?>
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

<?php require_once 'templates/footer.php'; ?>