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

// Generieren oder Abrufen eines eindeutigen Registrierungslinks für Spieler
$registrationKey = getOrCreatePlayerRegistrationKey($teamId, $_SESSION['user_id']);

// Generieren der QR-Code-URL und Registrierungs-URL
$qrCodeUrl = APP_URL . '/emergency_access.php?key=' . urlencode($accessKey);
$registrationUrl = APP_URL . '/player_registration.php?key=' . urlencode($registrationKey);

// Für PDF-Download
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    generateTeamAccessPDF($teamId, $teamName, $qrCodeUrl);
    exit;
}

// CSRF-Token für das Formular generieren
$csrf_token = generateCSRFToken();

// Anfragen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setMessage('red', 'Ungültige Anfrage. Bitte versuchen Sie es erneut.');
    } else {
        // QR-Code neu generieren
        if (isset($_POST['action']) && $_POST['action'] === 'regenerate') {
            // Alten Schlüssel löschen und neuen erstellen
            invalidateTeamAccessKey($teamId);
            $newAccessKey = getOrCreateTeamAccessKey($teamId, $_SESSION['user_id'], true);
            
            setMessage('green', 'QR-Code wurde erfolgreich neu generiert.');
            logActivity($_SESSION['user_id'], 'qrcode_regenerate', "QR-Code für Team $teamId neu generiert");
            
            redirect("team_qrcode.php?team_id=$teamId");
            exit;
        }
        
        // Registrierungslink neu generieren
        if (isset($_POST['action']) && $_POST['action'] === 'regenerate_registration') {
            // Alten Schlüssel löschen und neuen erstellen
            invalidatePlayerRegistrationKey($teamId);
            $newRegistrationKey = getOrCreatePlayerRegistrationKey($teamId, $_SESSION['user_id'], true);
            
            setMessage('green', 'Registrierungslink wurde erfolgreich neu generiert.');
            logActivity($_SESSION['user_id'], 'registration_link_regenerate', "Registrierungslink für Team $teamId neu generiert");
            
            redirect("team_qrcode.php?team_id=$teamId");
            exit;
        }
    }
}
?>

<div class="container mx-auto px-2 sm:px-4 py-4 sm:py-6">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 sm:mb-6">
        <div class="mb-3 sm:mb-0">
            <h2 class="text-xl sm:text-2xl font-bold">Team-Links für <?= e($teamName) ?></h2>
            <p class="text-gray-600 text-sm sm:text-base">Generieren und verwalten Sie Zugangslinks für Ihr Team</p>
        </div>
        <div>
            <a href="dashboard.php?team_id=<?= $teamId ?>" class="flex items-center justify-center bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400 w-full sm:w-auto">
                <i class="fas fa-arrow-left mr-2"></i>Zurück zum Dashboard
            </a>
        </div>
    </div>
    
    <!-- QR-Code für Notfallkontakte -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <h3 class="text-lg sm:text-xl font-bold mb-4">Notfallkontakte-QR-Code</h3>
        
        <div class="text-center">
            <div class="mb-6">
                <p class="text-base mb-4">Scannen Sie diesen QR-Code, um auf die Notfallkontakte zuzugreifen</p>
                
                <!-- QR-Code Anzeige - optimiert für mobile Geräte -->
                <div class="flex justify-center">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?= urlencode($qrCodeUrl) ?>" 
                         alt="QR-Code für <?= e($teamName) ?>" 
                         class="border p-2 bg-white max-w-full h-auto" 
                         style="min-width: 200px; max-width: 250px;" />
                </div>
                
                <div class="mt-4">
                    <p class="text-xs sm:text-sm text-gray-600">Der QR-Code ist mit dem aktuellen Trainer-Account verknüpft.</p>
                    <p class="text-xs sm:text-sm text-gray-600 mt-1">Er bietet direkten Zugriff auf die Notfallkontakte dieses Teams ohne Login.</p>
                </div>
            </div>
            
            <!-- Mobile-optimierte Buttons: Stack vertically on mobile -->
            <div class="flex flex-col sm:flex-row justify-center gap-3 sm:space-x-4">
                <a href="team_qrcode.php?team_id=<?= $teamId ?>&download=pdf" 
                   class="flex items-center justify-center bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    <i class="fas fa-file-pdf mr-2"></i>PDF herunterladen
                </a>
                
                <form method="POST" action="team_qrcode.php?team_id=<?= $teamId ?>" class="w-full sm:w-auto">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="regenerate">
                    <button type="submit" class="w-full flex items-center justify-center bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        <i class="fas fa-sync-alt mr-2"></i>QR-Code neu generieren
                    </button>
                </form>
            </div>
            
            <div class="mt-4">
                <p class="text-orange-600 text-sm">Hinweis: Bei Neugenerierung wird der alte QR-Code ungültig!</p>
            </div>
        </div>
    </div>
    
    <!-- Spieler-Registrierungslink -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
        <h3 class="text-lg sm:text-xl font-bold mb-4">Spieler-Registrierungslink</h3>
        
        <div class="text-center">
            <div class="mb-6">
                <p class="text-base mb-4">Teilen Sie diesen Link mit Ihren Spielern, damit diese sich selbst registrieren können</p>
                
                <!-- Link in Box mit Copy-Button -->
                <div class="relative bg-gray-100 rounded p-3 mb-4">
                    <div class="overflow-x-auto text-left text-sm break-all mb-2">
                        <a href="<?= $registrationUrl ?>" class="text-orange-600"><?= e($registrationUrl) ?></a>
                    </div>
                    <button type="button" onclick="copyRegistrationLink()" class="absolute right-2 top-2 text-gray-500 hover:text-orange-500">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <p class="text-xs sm:text-sm text-gray-600">Spieler können über diesen Link ihre eigenen Daten und Notfallkontakte eingeben.</p>
                    <p class="text-xs sm:text-sm text-gray-600 mt-1">Der Link ist 30 Tage gültig.</p>
                </div>
                
                <!-- QR-Code für Registrierungslink -->
                <div class="mb-6">
                    <p class="text-sm mb-2">Alternativ: QR-Code für die Registrierung</p>
                    <div class="flex justify-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($registrationUrl) ?>" 
                             alt="Registrierungs-QR-Code für <?= e($teamName) ?>" 
                             class="border p-2 bg-white max-w-full h-auto" 
                             style="min-width: 150px; max-width: 200px;" />
                    </div>
                </div>
            </div>
            
            <!-- Regenerate Registration Link Button -->
            <form method="POST" action="team_qrcode.php?team_id=<?= $teamId ?>" class="mt-4">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="regenerate_registration">
                <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 flex items-center justify-center mx-auto">
                    <i class="fas fa-sync-alt mr-2"></i>Registrierungslink neu generieren
                </button>
            </form>
            
            <div class="mt-4">
                <p class="text-orange-600 text-sm">Hinweis: Bei Neugenerierung wird der alte Registrierungslink ungültig!</p>
            </div>
        </div>
    </div>
</div>

<script>
    function copyRegistrationLink() {
        const registrationUrl = "<?= $registrationUrl ?>";
        
        // Create a temporary textarea element to copy from
        const textarea = document.createElement('textarea');
        textarea.value = registrationUrl;
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            // Copy the text to clipboard
            document.execCommand('copy');
            alert('Registrierungslink wurde in die Zwischenablage kopiert!');
        } catch (err) {
            console.error('Fehler beim Kopieren:', err);
            alert('Konnte den Link nicht kopieren. Bitte markieren und kopieren Sie den Link manuell.');
        }
        
        document.body.removeChild(textarea);
    }
</script>

<?php require_once 'templates/footer.php'; ?>