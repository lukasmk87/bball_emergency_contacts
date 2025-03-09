<?php
require_once __DIR__ . '/../functions.php';

// Sitzung starten
startSecureSession();

// Überprüfen, ob Benutzer angemeldet ist (außer auf der Login-Seite)
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page !== 'index.php' && 
    $current_page !== 'reset_password.php' && 
    $current_page !== 'player_registration.php' && 
    $current_page !== 'emergency_access.php' && 
    !isLoggedIn()) {
    // Bevor wir eine Weiterleitung durchführen, stellen wir sicher, dass keine Ausgabe erfolgt ist
    // Wenn die Weiterleitung hier stattfindet, muss sie vor jeglicher HTML-Ausgabe erfolgen
    redirect('index.php');
    exit;
}

// Benutzerinformationen nur abrufen, wenn der Benutzer angemeldet ist
$userName = '';
$userRole = '';
$isAdmin = false;
$userId = 0;
$selectedTeamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;

if (isLoggedIn()) {
    $userId = $_SESSION['user_id'] ?? 0;
    $userName = $_SESSION['user_name'] ?? '';
    $userEmail = $_SESSION['user_email'] ?? '';
    $userRole = $_SESSION['user_role'] ?? '';
    $isAdmin = hasRole('admin');
}

// Nachrichten für den Benutzer abrufen
$messages = getMessages();

// Hier beginnt die HTML-Ausgabe - WICHTIG: Vor dieser Zeile darf keine Ausgabe stattfinden!
// Alles in diesem Block wird erst ausgeführt, nachdem alle potenziellen Weiterleitungen überprüft wurden
?>
<!DOCTYPE html>
<html lang="de" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="description" content="Notfallkontakte für Basketballteams - sicher und einfach verwalten">
    <meta name="theme-color" content="#e65100">
    <title><?= APP_NAME ?></title>
    
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Application CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/dark-mode.css">
    
    <!-- Preload important resources -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
</head>
<body class="bg-gray-100 min-h-screen page-transition">
    <!-- Skip to content link for accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:px-4 focus:py-2 focus:bg-orange-500 focus:text-white focus:top-4 focus:left-4 focus:rounded">
        Zum Hauptinhalt springen
    </a>

    <div id="app" class="flex flex-col min-h-screen">
        <?php if (isLoggedIn()): ?>
        <!-- Mobile-Optimized Navigation Header -->
        <header class="bg-orange-500 text-white shadow-md">
            <div class="container mx-auto px-2 sm:px-4 py-3">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <a href="dashboard.php" class="flex items-center" aria-label="Zur Startseite">
                            <i class="fas fa-basketball-ball text-xl sm:text-2xl" aria-hidden="true"></i>
                            <h1 class="text-lg sm:text-xl font-bold ml-2"><?= APP_NAME ?></h1>
                        </a>
                    </div>
                    <div class="flex items-center">
                        <div class="hidden md:block mr-4">
                            <span id="user-name"><?= e($userName) ?></span>
                            <span id="user-role" class="text-sm bg-orange-600 px-2 py-1 rounded ml-2" aria-label="Rolle">
                                <?= ucfirst(e($userRole)) ?>
                            </span>
                        </div>
                        
                        <!-- Dark mode toggle button -->
                        <button id="theme-toggle" class="ml-2 p-2 rounded-full hover:bg-orange-600 focus:outline-none" aria-label="Dunkelmodus umschalten">
                            <i class="fas fa-moon" id="dark-icon" aria-hidden="true"></i>
                            <i class="fas fa-sun hidden" id="light-icon" aria-hidden="true"></i>
                        </button>
                        
                        <div class="relative" id="user-menu-container">
                            <button class="flex items-center focus:outline-none focus:ring-2 focus:ring-white p-2" 
                                    id="user-menu-button"
                                    aria-expanded="false"
                                    aria-haspopup="true"
                                    aria-controls="user-menu">
                                <img src="assets/images/default-avatar.png" alt="Profilbild" class="h-8 w-8 rounded-full bg-orange-300">
                                <span class="md:hidden text-sm ml-2"><?= e($userName) ?></span>
                                <i class="fas fa-chevron-down ml-2" aria-hidden="true"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 hidden" 
                                 id="user-menu"
                                 role="menu"
                                 aria-orientation="vertical"
                                 aria-labelledby="user-menu-button">
                                <a href="profile.php" class="block px-4 py-3 text-gray-800 hover:bg-orange-100" role="menuitem" tabindex="-1">
                                    <i class="fas fa-user-circle mr-2" aria-hidden="true"></i>Profil
                                </a>
                                <a href="logout.php" class="block px-4 py-3 text-gray-800 hover:bg-orange-100" role="menuitem" tabindex="-1">
                                    <i class="fas fa-sign-out-alt mr-2" aria-hidden="true"></i>Abmelden
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Mobile Bottom Navigation -->
        <div class="fixed bottom-0 left-0 right-0 bg-white border-t z-10 md:hidden">
            <div class="flex justify-around items-center p-2">
                <a href="dashboard.php" class="flex flex-col items-center p-2 text-sm">
                    <i class="fas fa-home text-lg" aria-hidden="true"></i>
                    <span>Startseite</span>
                </a>
                <?php if ($isAdmin): ?>
                <a href="teams.php" class="flex flex-col items-center p-2 text-sm">
                    <i class="fas fa-users text-lg" aria-hidden="true"></i>
                    <span>Teams</span>
                </a>
                <a href="users.php" class="flex flex-col items-center p-2 text-sm">
                    <i class="fas fa-user-cog text-lg" aria-hidden="true"></i>
                    <span>Benutzer</span>
                </a>
                <?php elseif (hasRole('trainer')): ?>
                <a href="players.php?team_id=<?= $selectedTeamId ?? '' ?>" class="flex flex-col items-center p-2 text-sm">
                    <i class="fas fa-user-plus text-lg" aria-hidden="true"></i>
                    <span>Spieler</span>
                </a>
                <?php endif; ?>
                <a href="profile.php" class="flex flex-col items-center p-2 text-sm">
                    <i class="fas fa-user-circle text-lg" aria-hidden="true"></i>
                    <span>Profil</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Flash Messages - Mobile Optimized -->
        <?php if (!empty($messages)): ?>
            <div class="container mx-auto px-2 sm:px-4 py-3 sm:py-4">
                <?php foreach ($messages as $msg): ?>
                    <div class="mb-3 bg-<?= $msg['type'] ?>-100 border-l-4 border-<?= $msg['type'] ?>-500 text-<?= $msg['type'] ?>-700 p-3 sm:p-4 rounded notification relative" role="alert">
                        <p><?= e($msg['text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Main Content Container starts in specific pages -->
        <main id="main-content">
            <!-- Page specific content starts here -->