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

// Alle Spieler und ihre Notfallkontakte für das Team abrufen
$players = db()->fetchAll("
    SELECT p.id, p.first_name, p.last_name, p.jersey_number, p.position
    FROM players p
    WHERE p.team_id = ?
    ORDER BY p.last_name, p.first_name
", [$teamId]);

$playerContacts = [];

foreach ($players as $player) {
    $contacts = db()->fetchAll("
        SELECT contact_name, phone_number, relationship
        FROM emergency_contacts
        WHERE player_id = ?
        ORDER BY id
    ", [$player['id']]);
    
    $playerContacts[$player['id']] = [
        'player' => $player,
        'contacts' => $contacts
    ];
}

// Aktivität protokollieren
logActivity($_SESSION['user_id'], 'export_contacts', "Notfallkontakte für Team $teamId exportiert im Format $format");

// Ausgabe basierend auf Format
switch ($format) {
    case 'csv':
        // CSV-Datei erzeugen
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="notfallkontakte_' . sanitizeFilename($teamName) . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM für Excel UTF-8-Erkennung
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // CSV-Header
        fputcsv($output, ['Spieler', 'Trikotnummer', 'Position', 'Notfallkontakt', 'Telefonnummer', 'Beziehung']);
        
        // Daten
        foreach ($playerContacts as $data) {
            $player = $data['player'];
            $contacts = $data['contacts'];
            
            $playerName = $player['first_name'] . ' ' . $player['last_name'];
            
            if (empty($contacts)) {
                // Spieler ohne Kontakte
                fputcsv($output, [
                    $playerName,
                    $player['jersey_number'],
                    $player['position'],
                    'Kein Notfallkontakt',
                    '',
                    ''
                ]);
            } else {
                // Spieler mit Kontakten
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
        
    case 'pdf':
        // Erfordert eine PDF-Bibliothek wie TCPDF oder FPDF
        // Für dieses Beispiel wird eine einfache HTML-Seite mit Print-CSS angezeigt
        // In einer vollständigen Implementierung würde hier die PDF-Generierung stehen
        header('Content-Type: text/html; charset=utf-8');
        echo generatePrintableHTML($teamName, $playerContacts, true);
        exit;
        
    case 'print':
    default:
        // Druckbare HTML-Seite
        header('Content-Type: text/html; charset=utf-8');
        echo generatePrintableHTML($teamName, $playerContacts);
        exit;
}

// Hilfsfunktion: Dateinamen säubern
function sanitizeFilename($filename) {
    // Umlaute ersetzen
    $filename = str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'], ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'], $filename);
    // Nur alphanumerische Zeichen, Unterstriche und Bindestriche beibehalten
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    return $filename;
}

// Hilfsfunktion: Druckbare HTML-Seite generieren
function generatePrintableHTML($teamName, $playerContacts, $forPDF = false) {
    $html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notfallkontakte: ' . htmlspecialchars($teamName) . '</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <style>
        @media print {
            body { font-size: 12pt; }
            .pagebreak { page-break-before: always; }
            .no-print { display: none !important; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        }
        .print-header { text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body class="bg-white p-8">
    <div class="no-print mb-4 flex justify-between items-center">
        <button onclick="window.print()" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
            <i class="fas fa-print mr-2"></i>Drucken
        </button>
        <a href="dashboard.php?team_id=' . urlencode((int)$_GET['team_id']) . '" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
            <i class="fas fa-arrow-left mr-2"></i>Zurück zum Dashboard
        </a>
    </div>
    
    <div class="print-header">
        <h1 class="text-2xl font-bold">Notfallkontakte: ' . htmlspecialchars($teamName) . '</h1>
        <p>Stand: ' . date('d.m.Y H:i') . ' Uhr</p>
    </div>
    
    <table class="min-w-full border">
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
        $playerNumber = htmlspecialchars($player['jersey_number']);
        $playerPosition = htmlspecialchars($player['position']);
        
        if (empty($contacts)) {
            // Spieler ohne Kontakte
            $html .= '<tr>
                <td class="border px-4 py-2">' . $playerName . ' (' . $playerNumber . ')</td>
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
                        <td class="border px-4 py-2" rowspan="' . count($contacts) . '">' . $playerName . ' (' . $playerNumber . ')</td>
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
    
    <div class="mt-8 text-sm text-gray-600 print-footer">
        <p>Diese Liste enthält vertrauliche Daten und darf nur für Notfälle verwendet werden.</p>
        <p>Generiert durch ' . htmlspecialchars(APP_NAME) . ' am ' . date('d.m.Y') . '.</p>
    </div>
</body>
</html>';

    return $html;
}