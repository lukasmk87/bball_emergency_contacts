<?php
require_once 'functions.php';
require_once 'templates/header.php';

// Überprüfen, ob Benutzer angemeldet ist und Trainer- oder Admin-Rechte hat
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
    if ($team['id'] == $teamId) {
        $hasAccess = true;
        $teamName = $team['name'];
        break;
    }
}

if (!$hasAccess && !hasRole('admin')) {
    setMessage('red', 'Sie haben keinen Zugriff auf dieses Team.');
    redirect('dashboard.php');
    exit;
}

// Generieren oder Abrufen eines eindeutigen Zugangsschlüssels für das Team
$accessKey = getOrCreateTeamAccessKey($teamId, $_SESSION['user_id']);

// Generieren der QR-Code-URL
$qrCodeUrl = APP_URL . '/emergency_access.php?key=' . urlencode($accessKey);

// Für PDF-Download
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    generateTeamAccessPDF($teamId, $teamName, $qrCodeUrl);
    exit;
}

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold">QR-Code für <?= e($teamName) ?></h2>
            <p class="text-gray-600">Dieser QR-Code ermöglicht den direkten Zugriff auf die Notfallkontakte ohne Login</p>
        </div>
        <div>
            <a href="dashboard.php?team_id=<?= $teamId ?>" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 mr-2">
                <i class="fas fa-arrow-left mr-2"></i>Zurück zum Dashboard
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="text-center">
            <div class="mb-6">
                <p class="text-lg mb-4">Scannen Sie diesen QR-Code, um auf die Notfallkontakte zuzugreifen</p>
                
                <!-- QR-Code Anzeige -->
                <div class="flex justify-center">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($qrCodeUrl) ?>" 
                         alt="QR-Code für <?= e($teamName) ?>" 
                         class="border p-2 bg-white" />
                </div>
                
                <div class="mt-4">
                    <p class="text-sm text-gray-600">Der QR-Code ist mit dem aktuellen Trainer-Account verknüpft.</p>
                    <p class="text-sm text-gray-600 mt-1">Er bietet direkten Zugriff auf die Notfallkontakte dieses Teams ohne Login.</p>
                </div>
            </div>
            
            <div class="mt-8 flex justify-center space-x-4">
                <a href="team_qrcode.php?team_id=<?= $teamId ?>&download=pdf" 
                   class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    <i class="fas fa-file-pdf mr-2"></i>PDF herunterladen
                </a>
                
                <form method="POST" action="team_qrcode.php?team_id=<?= $teamId ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="regenerate">
                    <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        <i class="fas fa-sync-alt mr-2"></i>QR-Code neu generieren
                    </button>
                </form>
            </div>
            
            <div class="mt-6">
                <p class="text-orange-600">Hinweis: Bei Neugenerierung wird der alte QR-Code ungültig!</p>
                <p class="text-sm mt-2">Direkter Link: <a href="<?= $qrCodeUrl ?>" class="text-orange-600 break-all"><?= e($qrCodeUrl) ?></a></p>
            </div>
        </div>
    </div>
</div>

<?php
// QR-Code neu generieren, wenn angefordert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'regenerate') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        // Alten Schlüssel löschen und neuen erstellen
        invalidateTeamAccessKey($teamId);
        $newAccessKey = getOrCreateTeamAccessKey($teamId, $_SESSION['user_id'], true);
        
        setMessage('green', 'QR-Code wurde erfolgreich neu generiert.');
        logActivity($_SESSION['user_id'], 'qrcode_regenerate', "QR-Code für Team $teamId neu generiert");
        
        redirect("team_qrcode.php?team_id=$teamId");
        exit;
    }
}

require_once 'templates/footer.php';
?>