<?php
require_once 'functions.php';

// Sitzung starten
startSecureSession();

// Überprüfen, ob Benutzer angemeldet ist
if (!isLoggedIn()) {
    redirect('index.php');
    exit;
}

// Team-ID aus der URL abrufen
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

// Format (PDF, CSV, Print) bestimmen
$format = isset($_GET['format']) ? $_GET['format'] : 'print';

// Überprüfen, ob das Team existiert und der Benutzer Zugriff hat
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

if (!$hasAccess && !hasRole('admin')) {
    setMessage('red', 'Sie haben keinen Zugriff auf dieses Team.');
    redirect('dashboard.php');
    exit;
}

try {
    // Alle Spieler für das Team abrufen
    $players = db()->fetchAll("
        SELECT p.id, p.first_name, p.last_name, p.jersey_number, p.position
        FROM players p
        WHERE p.team_id = ?
        ORDER BY p.last_name, p.first_name
    ", [$teamId]);
    
    // Sammle alle Spieler-IDs für eine optimierte Abfrage
    $playerIds = array_column($players, 'id');
    
    // Initialisiere playerContacts Array
    $playerContacts = [];
    
    if (!empty($playerIds)) {
        // Hole alle Kontakte in einer Abfrage
        $allContacts = db()->fetchAll("
            SELECT player_id, contact_name, phone_number, relationship
            FROM emergency_contacts
            WHERE player_id IN (" . implode(',', $playerIds) . ")
            ORDER BY player_id, id
        ");
        
        // Organisiere nach Spieler-ID
        $contactsByPlayer = [];
        foreach ($allContacts as $contact) {
            $contactsByPlayer[$contact['player_id']][] = $contact;
        }
        
        // Baue das endgültige Array
        foreach ($players as $player) {
            $playerContacts[$player['id']] = [
                'player' => $player,
                'contacts' => $contactsByPlayer[$player['id']] ?? []
            ];
        }
    }
    
    // Aktivität protokollieren
    logActivity($_SESSION['user_id'], 'export_contacts', "Notfallkontakte für Team $teamId exportiert im Format $format");
    
    // Ausgabe basierend auf Format
    switch ($format) {
        case 'csv':
            outputCSV($teamName, $playerContacts);
            break;
            
        case 'pdf':
            outputPDF($teamName, $playerContacts);
            break;
            
        case 'print':
        default:
            outputPrintHTML($teamName, $playerContacts);
            break;
    }
} catch (Exception $e) {
    handleError($e, 'Fehler beim Exportieren der Kontakte.');
    redirect("dashboard.php?team_id=$teamId");
    exit;
}

/**
 * CSV-Export mit korrekten Headers
 */
function outputCSV($teamName, $playerContacts) {
    // Correct headers for CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="notfallkontakte_' . sanitizeFilename($teamName) . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM for Excel UTF-8 recognition
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // CSV header
    fputcsv($output, ['Spieler', 'Trikotnummer', 'Position', 'Notfallkontakt', 'Telefonnummer', 'Beziehung']);
    
    // Output data
    foreach ($playerContacts as $data) {
        $player = $data['player'];
        $contacts = $data['contacts'];
        
        $playerName = $player['first_name'] . ' ' . $player['last_name'];
        
        if (empty($contacts)) {
            // Player without contacts
            fputcsv($output, [
                $playerName,
                $player['jersey_number'],
                $player['position'],
                'Kein Notfallkontakt',
                '',
                ''
            ]);
        } else {
            // Player with contacts
            foreach ($contacts as $index => $contact) {
                fputcsv($output, [
                    $playerName,
                    $player['jersey_number'],
                    $player['position'],
                    $contact['contact_name'],
                    $contact['phone_number'],
                    $contact['relationship']
                ]);
            }
        }
    }
    
    fclose($output);
    exit;
}

/**
 * PDF-Export (Fall-back to HTML with print CSS)
 */
function outputPDF($teamName, $playerContacts) {
    // Set proper content type header
    header('Content-Type: text/html; charset=utf-8');
    echo generatePrintableHTML($teamName, $playerContacts, true);
    exit;
}

/**
 * HTML-Ausgabe mit Druck-CSS
 */
function outputPrintHTML($teamName, $playerContacts) {
    // Set proper content type header
    header('Content-Type: text/html; charset=utf-8');
    echo generatePrintableHTML($teamName, $playerContacts);
    exit;
}

/**
 * Dateinamen säubern
 */
function sanitizeFilename($filename) {
    // Replace umlauts and special characters
    $replacements = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
        'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        'ß' => 'ss', ' ' => '_'
    ];
    
    $filename = str_replace(array_keys($replacements), array_values($replacements), $filename);
    
    // Keep only alphanumeric characters, underscores, and hyphens
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    
    return $filename;
}

/**
 * Mobile-freundliche druckbare HTML-Seite generieren
 */
function generatePrintableHTML($teamName, $playerContacts, $forPDF = false) {
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notfallkontakte: ' . htmlspecialchars($teamName) . '</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @media print {
            @page { margin: 1cm; }
            body { font-size: 12pt; font-family: Arial, sans-serif; }
            .pagebreak { page-break-before: always; }
            .no-print { display: none !important; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            a { color: inherit; text-decoration: none; }
        }
        .print-header { text-align: center; margin-bottom: 20px; }
        
        /* Mobile-friendly styles */
        .table-container {
            overflow-x: auto;
            width: 100%;
        }
        
        /* Responsive styles */
        @media (max-width: 640px) {
            table { font-size: 14px; }
            th, td { padding: 6px 4px; }
            .print-header h1 { font-size: 20px; }
            .print-header p { font-size: 14px; }
            button { min-height: 44px; }
        }
        
        /* Better accessibility */
        a:focus, button:focus {
            outline: 2px solid #e65100;
            outline-offset: 2px;
        }
        
        button {
            transition: background-color 0.2s;
        }
        
        .btn-print {
            background-color: #e65100;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
        }
        
        .btn-print:hover {
            background-color: #ff6d00;
        }
        
        .btn-back {
            background-color: #64748b;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background-color: #475569;
        }
    </style>
</head>
<body class="bg-white p-4 sm:p-8">
    <div class="no-print flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 gap-3">
        <button id="print-button" onclick="window.print()" class="btn-print w-full sm:w-auto">
            <i class="fas fa-print mr-2" aria-hidden="true"></i>Drucken
        </button>
        <a href="dashboard.php?team_id=' . urlencode((int)$_GET['team_id']) . '" class="btn-back w-full sm:w-auto text-center">
            <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i>Zurück zum Dashboard
        </a>
    </div>
    
    <div class="print-header">
        <h1 class="text-xl sm:text-2xl font-bold">Notfallkontakte: ' . htmlspecialchars($teamName) . '</h1>
        <p class="text-sm sm:text-base">Stand: ' . date('d.m.Y H:i') . ' Uhr</p>
    </div>
    
    <div class="table-container">
        <table class="min-w-full border" aria-label="Notfallkontakte">
            <thead>
                <tr>
                    <th class="border px-4 py-2">Spieler</th>
                    <th class="border px-4 py-2">Position</th>
                    <th class="border px-4 py-2">Notfallkontakt</th>
                    <th class="border px-4 py-2">Telefonnummer</th>
                    <th class="border px-4 py-2">Beziehung</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($playerContacts as $data) {
        $player = $data['player'];
        $contacts = $data['contacts'];
        
        $playerName = htmlspecialchars($player['first_name'] . ' ' . $player['last_name']);
        $playerNumber = htmlspecialchars($player['jersey_number'] ?? '');
        $playerPosition = htmlspecialchars($player['position'] ?? '');
        
        if (empty($contacts)) {
            // Spieler ohne Kontakte
            $html .= '<tr>
                <td class="border px-4 py-2">' . $playerName . (!empty($playerNumber) ? ' (' . $playerNumber . ')' : '') . '</td>
                <td class="border px-4 py-2">' . $playerPosition . '</td>
                <td class="border px-4 py-2 text-red-600" colspan="3">Kein Notfallkontakt vorhanden</td>
            </tr>';
        } else {
            // Spieler mit Kontakten
            foreach ($contacts as $index => $contact) {
                $html .= '<tr>';
                if ($index === 0) {
                    // Nur in der ersten Zeile des Spielers den Namen anzeigen
                    $html .= '
                        <td class="border px-4 py-2" rowspan="' . count($contacts) . '">' . $playerName . (!empty($playerNumber) ? ' (' . $playerNumber . ')' : '') . '</td>
                        <td class="border px-4 py-2" rowspan="' . count($contacts) . '">' . $playerPosition . '</td>';
                }
                $html .= '
                    <td class="border px-4 py-2">' . htmlspecialchars($contact['contact_name']) . '</td>
                    <td class="border px-4 py-2"><a href="tel:' . htmlspecialchars($contact['phone_number']) . '" class="text-orange-600">' . htmlspecialchars($contact['phone_number']) . '</a></td>
                    <td class="border px-4 py-2">' . htmlspecialchars($contact['relationship']) . '</td>
                </tr>';
            }
        }
    }
    
    $html .= '</tbody>
    </table>
    </div>
    
    <div class="mt-8 text-sm text-gray-600 print-footer">
        <p>Diese Liste enthält vertrauliche Daten und darf nur für Notfälle verwendet werden.</p>
        <p>Generiert durch ' . htmlspecialchars(APP_NAME) . ' am ' . date('d.m.Y') . '.</p>
    </div>
    
    <script>
        // Fix for iOS Safari printing
        document.addEventListener("DOMContentLoaded", function() {
            const printButton = document.getElementById("print-button");
            printButton.addEventListener("click", function() {
                setTimeout(function() {
                    window.print();
                }, 250);
            });
        });
    </script>
</body>
</html>';

    return $html;
}