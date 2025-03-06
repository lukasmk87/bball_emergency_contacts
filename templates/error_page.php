<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zugriff verweigert - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Dark Mode Grundeinstellungen */
        body {
            background-color: #121212 !important;
            color: #e0e0e0 !important;
        }

        .bg-white {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
        }

        .text-gray-600, .text-gray-700, .text-gray-800, .text-gray-900 {
            color: #b0b0b0 !important;
        }

        .bg-red-100 {
            background-color: #4a1d1a !important;
            border-color: #e53e3e !important;
        }

        .text-red-700 {
            color: #feb2b2 !important;
        }

        .bg-orange-500 {
            background-color: #e65100 !important;
        }

        .bg-orange-500:hover {
            background-color: #ff6d00 !important;
        }

        .shadow-md {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.25) !important;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-6 max-w-md">
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-red-600 mb-4">
                <i class="fas fa-exclamation-circle text-6xl"></i>
            </div>
            
            <h1 class="text-2xl font-bold mb-4">Zugriff verweigert</h1>
            
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p>Der angegebene Zugriffslink ist ungültig oder abgelaufen.</p>
            </div>
            
            <p class="text-gray-600 mb-6">
                Bitte wenden Sie sich an Ihren Teamverantwortlichen, um einen gültigen Zugriffslink zu erhalten.
            </p>
            
            <div class="flex justify-center">
                <a href="index.php" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    <i class="fas fa-arrow-left mr-2"></i>Zurück zur Startseite
                </a>
            </div>
        </div>
    </div>
</body>
</html>