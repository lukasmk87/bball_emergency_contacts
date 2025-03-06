<?php
header('Content-Type: application/json');

// Notwendige Dateien einbinden
require_once '../functions.php';

// CORS-Header für API-Zugriff
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS-Request für CORS-Preflight beantworten
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// API-Key aus dem Header holen
$headers = getallheaders();
$apiKey = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

// Einfache API-Key-Validierung (in einer produktiven Umgebung sollte dies sicherer sein)
$validApiKey = '4fd8d7be9a6b6ca1d07e4fd0f13a5e8e'; // Dies sollte in einer Konfigurationsdatei gespeichert werden

if ($apiKey !== $validApiKey) {
    outputError('Unauthorized', 401);
    exit;
}

// Route bestimmen
$route = isset($_GET['route']) ? $_GET['route'] : '';
$method = $_SERVER['REQUEST_METHOD'];

// Routing
switch ($route) {
    case 'teams':
        handleTeamsRoute($method);
        break;
        
    case 'players':
        handlePlayersRoute($method);
        break;
        
    case 'contacts':
        handleContactsRoute($method);
        break;
        
    default:
        outputError('Route not found', 404);
        break;
}

// Handler für Team-Routen
function handleTeamsRoute($method) {
    switch ($method) {
        case 'GET':
            // Team-ID aus der URL abrufen
            $teamId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($teamId > 0) {
                // Einzelnes Team abrufen
                $team = db()->fetchOne("SELECT * FROM teams WHERE id = ?", [$teamId]);
                
                if ($team) {
                    outputSuccess($team);
                } else {
                    outputError('Team not found', 404);
                }
            } else {
                // Alle Teams abrufen
                $teams = db()->fetchAll("SELECT * FROM teams ORDER BY name");
                outputSuccess($teams);
            }
            break;
            
        default:
            outputError('Method not allowed', 405);
            break;
    }
}

// Handler für Spieler-Routen
function handlePlayersRoute($method) {
    switch ($method) {
        case 'GET':
            // Team-ID aus der URL abrufen
            $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
            $playerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($playerId > 0) {
                // Einzelnen Spieler abrufen
                $player = db()->fetchOne("SELECT * FROM players WHERE id = ?", [$playerId]);
                
                if ($player) {
                    outputSuccess($player);
                } else {
                    outputError('Player not found', 404);
                }
            } elseif ($teamId > 0) {
                // Alle Spieler eines Teams abrufen
                $players = db()->fetchAll("
                    SELECT * FROM players 
                    WHERE team_id = ? 
                    ORDER BY last_name, first_name
                ", [$teamId]);
                
                outputSuccess($players);
            } else {
                outputError('Missing team_id parameter', 400);
            }
            break;
            
        default:
            outputError('Method not allowed', 405);
            break;
    }
}

// Handler für Kontakt-Routen
function handleContactsRoute($method) {
    switch ($method) {
        case 'GET':
            // Spieler-ID aus der URL abrufen
            $playerId = isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0;
            $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
            
            if ($playerId > 0) {
                // Kontakte für einen Spieler abrufen
                $contacts = db()->fetchAll("
                    SELECT * FROM emergency_contacts
                    WHERE player_id = ?
                    ORDER BY id
                ", [$playerId]);
                
                outputSuccess($contacts);
            } elseif ($teamId > 0) {
                // Alle Kontakte für ein Team mit Spielerinformationen abrufen
                $contacts = db()->fetchAll("
                    SELECT p.id AS player_id, p.first_name, p.last_name, p.jersey_number, 
                           ec.id, ec.contact_name, ec.phone_number, ec.relationship
                    FROM players p
                    JOIN emergency_contacts ec ON p.id = ec.player_id
                    WHERE p.team_id = ?
                    ORDER BY p.last_name, p.first_name, ec.id
                ", [$teamId]);
                
                outputSuccess($contacts);
            } else {
                outputError('Missing player_id or team_id parameter', 400);
            }
            break;
            
        default:
            outputError('Method not allowed', 405);
            break;
    }
}

// Hilfsfunktion für erfolgreiche API-Antwort
function outputSuccess($data) {
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
    exit;
}

// Hilfsfunktion für Fehlerantwort
function outputError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
    exit;
}