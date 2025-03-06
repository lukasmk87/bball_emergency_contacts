<?php
require_once __DIR__ . '/../functions.php';

// Sitzung starten
startSecureSession();

// Überprüfen, ob Benutzer angemeldet ist (außer auf der Login-Seite)
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page !== 'index.php' && $current_page !== 'reset_password.php' && !isLoggedIn()) {
    // Bevor wir eine Weiterleitung durchführen, stellen wir sicher, dass keine Ausgabe erfolgt ist
    // Wenn die Weiterleitung hier stattfindet, muss sie vor jeglicher HTML-Ausgabe erfolgen
    redirect('index.php');
    exit;
}

// Benutzerinformationen nur abrufen, wenn der Benutzer angemeldet ist
$userName = '';
$userRole = '';
$isAdmin = false;

if (isLoggedIn()) {
    $userName = $_SESSION['user_name'] ?? '';
    $userRole = $_SESSION['user_role'] ?? '';
    $isAdmin = hasRole('admin');
}

// Nachrichten für den Benutzer abrufen
$messages = getMessages();

// Hier beginnt die HTML-Ausgabe - WICHTIG: Vor dieser Zeile darf keine Ausgabe stattfinden!
// Alles in diesem Block wird erst ausgeführt, nachdem alle potenziellen Weiterleitungen überprüft wurden
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <!-- Dark Mode Styles -->
    <style>
        /* Dark Mode Grundeinstellungen */
        body {
            background-color: #121212 !important;
            color: #e0e0e0 !important;
        }

        /* Dark Mode für Hauptcontainer */
        .bg-white, .bg-gray-50, .bg-gray-100 {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
        }

        /* Dark Mode für Navigation */
        .bg-orange-500 {
            background-color: #e65100 !important;
        }

        /* Dark Mode für Text */
        .text-gray-600, .text-gray-700, .text-gray-800, .text-gray-900 {
            color: #b0b0b0 !important;
        }

        /* Dark Mode für Tabellenzeilen */
        table tbody tr {
            background-color: #1e1e1e !important;
            border-color: #333 !important;
        }

        table tbody tr:hover {
            background-color: #262626 !important;
        }

        thead {
            background-color: #262626 !important;
        }

        th {
            color: #b0b0b0 !important;
        }

        td {
            border-color: #333 !important;
        }

        /* Dark Mode für Formulare */
        input, select, textarea {
            background-color: #262626 !important;
            color: #e0e0e0 !important;
            border-color: #444 !important;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #e65100 !important;
        }

        ::placeholder {
            color: #808080 !important;
        }

        select option {
            background-color: #262626 !important;
            color: #e0e0e0 !important;
        }

        /* Dark Mode für Buttons */
        .bg-gray-300, .bg-gray-400 {
            background-color: #383838 !important;
            color: #e0e0e0 !important;
        }

        .bg-gray-300:hover, .bg-gray-400:hover {
            background-color: #444444 !important;
        }

        /* User Menu Dark Mode Anpassung */
        #user-menu {
            background-color: #262626 !important;
            border-color: #444 !important;
        }

        #user-menu a {
            color: #e0e0e0 !important;
        }

        #user-menu a:hover {
            background-color: #333 !important;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div id="app" class="flex flex-col min-h-screen">
        <?php if (isLoggedIn()): ?>
        <!-- Navigation Header -->
        <header class="bg-orange-500 text-white shadow-md">
            <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-basketball-ball text-2xl"></i>
                    <h1 class="text-xl font-bold"><?= APP_NAME ?></h1>
                </div>
                <div class="flex items-center">
                    <div class="hidden md:block mr-4">
                        <span id="user-name"><?= e($userName) ?></span>
                        <span id="user-role" class="text-sm bg-orange-600 px-2 py-1 rounded ml-2">
                            <?= ucfirst(e($userRole)) ?>
                        </span>
                    </div>
                    <div class="relative" id="user-menu-container">
                        <button class="flex items-center focus:outline-none" id="user-menu-button">
                            <img src="assets/images/default-avatar.png" alt="Profilbild" class="h-8 w-8 rounded-full bg-orange-300">
                            <i class="fas fa-chevron-down ml-2"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 hidden" id="user-menu">
                            <a href="profile.php" class="block px-4 py-2 text-gray-800 hover:bg-orange-100">Profil</a>
                            <a href="logout.php" class="block px-4 py-2 text-gray-800 hover:bg-orange-100">Abmelden</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <?php endif; ?>

        <!-- Flash Messages -->
        <?php if (!empty($messages)): ?>
            <div class="container mx-auto px-4 py-4">
                <?php foreach ($messages as $msg): ?>
                    <div class="mb-4 bg-<?= $msg['type'] ?>-100 border-l-4 border-<?= $msg['type'] ?>-500 text-<?= $msg['type'] ?>-700 p-4">
                        <p><?= e($msg['text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>