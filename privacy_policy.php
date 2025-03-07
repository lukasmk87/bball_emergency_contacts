<?php
// Datenschutzerklärung für die Basketball Notfallkontakte App
// Diese Datei als privacy_policy.php speichern
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenschutzerklärung - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Dark Mode Grundeinstellungen */
        body {
            background-color: #121212;
            color: #e0e0e0;
        }

        .bg-white {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }

        /* Dark Mode für Text */
        .text-gray-600, .text-gray-700, .text-gray-800, .text-gray-900 {
            color: #b0b0b0;
        }
        
        h2 {
            color: #ff9800;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        
        h3 {
            color: #e0e0e0;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }
        
        p, ul, ol {
            margin-bottom: 1rem;
        }
        
        ul, ol {
            padding-left: 1.5rem;
        }
        
        ul {
            list-style-type: disc;
        }
        
        ol {
            list-style-type: decimal;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-lg shadow-md p-6 sm:p-8">
            <div class="text-center mb-8">
                <i class="fas fa-basketball-ball text-orange-500 text-4xl sm:text-5xl"></i>
                <h1 class="text-2xl sm:text-3xl font-bold mt-4"><?= APP_NAME ?></h1>
                <h2 class="text-xl sm:text-2xl mt-2">Datenschutzerklärung</h2>
                <p class="text-gray-600 text-sm sm:text-base mt-2">Stand: <?= date('d.m.Y') ?></p>
            </div>
            
            <div class="prose max-w-none">
                <p>
                    Der Schutz Ihrer personenbezogenen Daten ist uns ein wichtiges Anliegen. In dieser Datenschutzerklärung informieren wir Sie darüber, 
                    welche Daten wir erheben, wie wir diese verarbeiten und welche Rechte Ihnen zustehen.
                </p>
                
                <h2>1. Verantwortliche Stelle</h2>
                <p>
                    Verantwortlich für die Datenverarbeitung auf dieser Webseite im Sinne der Datenschutz-Grundverordnung (DSGVO) ist:
                </p>
                <p>
                    <strong>[Vereinsname einfügen]</strong><br>
                    [Straße und Hausnummer]<br>
                    [PLZ und Ort]<br>
                    [Land]<br>
                    E-Mail: [E-Mail-Adresse einfügen]<br>
                    Telefon: [Telefonnummer einfügen]
                </p>
                
                <h2>2. Arten der verarbeiteten Daten</h2>
                <p>
                    Je nach Nutzergruppe erheben und verarbeiten wir unterschiedliche personenbezogene Daten:
                </p>
                
                <h3>2.1 Für Benutzer (Trainer, Manager, Administratoren)</h3>
                <ul>
                    <li>Name</li>
                    <li>E-Mail-Adresse</li>
                    <li>Passwort (in verschlüsselter Form)</li>
                    <li>Benutzerrolle (Admin, Trainer, Manager)</li>
                    <li>Teamzugehörigkeit</li>
                    <li>Login-Aktivitäten</li>
                </ul>
                
                <h3>2.2 Für Spieler</h3>
                <ul>
                    <li>Vor- und Nachname</li>
                    <li>Trikotnummer</li>
                    <li>Position</li>
                    <li>Teamzugehörigkeit</li>
                </ul>
                
                <h3>2.3 Für Notfallkontakte</h3>
                <ul>
                    <li>Name des Kontakts</li>
                    <li>Telefonnummer</li>
                    <li>Beziehung zum Spieler</li>
                </ul>
                
                <h2>3. Zweck der Datenverarbeitung</h2>
                <p>
                    Wir verarbeiten Ihre personenbezogenen Daten zu folgenden Zwecken:
                </p>
                <ul>
                    <li>Zur Bereitstellung unserer Anwendung "Basketball Notfallkontakte"</li>
                    <li>Zur Verwaltung von Benutzerkonten</li>
                    <li>Zur Verwaltung von Teams, Spielern und deren Notfallkontakten</li>
                    <li>Zur Bereitstellung von Notfallkontaktinformationen im Bedarfsfall</li>
                    <li>Zur Gewährleistung der Sicherheit unserer Anwendung</li>
                    <li>Zur Erfüllung gesetzlicher Verpflichtungen</li>
                </ul>
                
                <h2>4. Rechtsgrundlage der Verarbeitung</h2>
                <p>
                    Die Rechtsgrundlage für die Verarbeitung Ihrer personenbezogenen Daten ist:
                </p>
                <ul>
                    <li>Für Benutzer: Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung), da die Datenverarbeitung zur Nutzung der Anwendung erforderlich ist.</li>
                    <li>Für Spieler und Notfallkontakte: Art. 6 Abs. 1 lit. a DSGVO (Einwilligung), sofern die betroffenen Personen in die Datenverarbeitung eingewilligt haben.</li>
                    <li>Für die Bereitstellung von Notfallkontaktinformationen im Notfall: Art. 6 Abs. 1 lit. d DSGVO (lebenswichtige Interessen), da die Verarbeitung zum Schutz lebenswichtiger Interessen der betroffenen Person erforderlich sein kann.</li>
                    <li>In bestimmten Fällen: Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse), sofern die Verarbeitung zur Wahrung der berechtigten Interessen des Verantwortlichen oder eines Dritten erforderlich ist.</li>
                </ul>
                
                <h2>5. Speicherdauer</h2>
                <p>
                    Wir speichern Ihre personenbezogenen Daten nur so lange, wie es für die Erfüllung der Zwecke, für die sie erhoben wurden, erforderlich ist oder solange gesetzliche Aufbewahrungsfristen bestehen:
                </p>
                <ul>
                    <li>Benutzerdaten werden für die Dauer der Nutzung der Anwendung gespeichert und nach Kündigung des Kontos gelöscht.</li>
                    <li>Spieler- und Notfallkontaktdaten werden gespeichert, solange der Spieler Mitglied des Teams ist und werden gelöscht, wenn der Spieler das Team verlässt oder auf Anfrage.</li>
                    <li>Protokolldaten zu Sicherheitszwecken werden in der Regel nach 90 Tagen gelöscht.</li>
                </ul>
                
                <h2>6. Empfänger der Daten</h2>
                <p>
                    Ihre personenbezogenen Daten werden grundsätzlich nur innerhalb unserer Anwendung verarbeitet und nicht an unbefugte Dritte weitergegeben. 
                    Folgende berechtigte Personen haben im Rahmen ihrer Funktionen Zugriff auf bestimmte Daten:
                </p>
                <ul>
                    <li>Administratoren haben Zugriff auf Benutzerdaten und alle Team-, Spieler- und Notfallkontaktdaten.</li>
                    <li>Trainer haben Zugriff auf die Daten ihrer eigenen Teams, inkl. Spieler und Notfallkontakte.</li>
                    <li>Über den QR-Code-Zugang können Notfallkontaktinformationen im Bedarfsfall auch ohne Login abgerufen werden.</li>
                </ul>
                
                <h2>7. Ihre Rechte</h2>
                <p>
                    Nach der DSGVO stehen Ihnen folgende Rechte zu:
                </p>
                <ul>
                    <li><strong>Auskunftsrecht (Art. 15 DSGVO):</strong> Sie haben das Recht, Auskunft über Ihre gespeicherten personenbezogenen Daten zu erhalten.</li>
                    <li><strong>Recht auf Berichtigung (Art. 16 DSGVO):</strong> Sie haben das Recht, unrichtige personenbezogene Daten berichtigen zu lassen.</li>
                    <li><strong>Recht auf Löschung (Art. 17 DSGVO):</strong> Sie haben das Recht, die Löschung Ihrer personenbezogenen Daten zu verlangen.</li>
                    <li><strong>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO):</strong> Sie haben das Recht, die Einschränkung der Verarbeitung Ihrer personenbezogenen Daten zu verlangen.</li>
                    <li><strong>Recht auf Datenübertragbarkeit (Art. 20 DSGVO):</strong> Sie haben das Recht, die Sie betreffenden personenbezogenen Daten in einem strukturierten, gängigen und maschinenlesbaren Format zu erhalten.</li>
                    <li><strong>Widerspruchsrecht (Art. 21 DSGVO):</strong> Sie haben das Recht, jederzeit gegen die Verarbeitung Ihrer personenbezogenen Daten Widerspruch einzulegen.</li>
                    <li><strong>Recht auf Widerruf der Einwilligung (Art. 7 Abs. 3 DSGVO):</strong> Sie haben das Recht, Ihre erteilte Einwilligung jederzeit zu widerrufen.</li>
                    <li><strong>Beschwerderecht bei einer Aufsichtsbehörde (Art. 77 DSGVO):</strong> Sie haben das Recht, sich bei einer Datenschutz-Aufsichtsbehörde zu beschweren.</li>
                </ul>
                
                <h2>8. Datensicherheit</h2>
                <p>
                    Wir treffen technische und organisatorische Sicherheitsmaßnahmen, um Ihre personenbezogenen Daten gegen zufällige oder vorsätzliche Manipulationen, Verlust, Zerstörung oder gegen den Zugriff unberechtigter Personen zu schützen. Unsere Sicherheitsmaßnahmen werden entsprechend der technologischen Entwicklung fortlaufend verbessert. Dazu gehören unter anderem:
                </p>
                <ul>
                    <li>Verschlüsselung von Passwörtern</li>
                    <li>Verwendung von HTTPS/SSL für die sichere Übertragung der Daten</li>
                    <li>Regelmäßige Sicherheitsupdates der verwendeten Software</li>
                    <li>Zugriffsbeschränkungen und Rollen-/Rechtemanagement</li>
                    <li>Protokollierung von Zugriffen zu Sicherheitszwecken</li>
                </ul>
                
                <h2>9. Verarbeitung von Daten Minderjähriger</h2>
                <p>
                    Unsere Anwendung kann auch Daten von minderjährigen Spielern verarbeiten. Die Eingabe dieser Daten erfolgt in der Regel durch die verantwortlichen Trainer oder durch die Einwilligung der Erziehungsberechtigten. Wir empfehlen, dass bei minderjährigen Spielern unter 16 Jahren die Erziehungsberechtigten der Datenverarbeitung zustimmen.
                </p>
                
                <h2>10. Änderungen dieser Datenschutzerklärung</h2>
                <p>
                    Wir behalten uns vor, diese Datenschutzerklärung anzupassen, damit sie stets den aktuellen rechtlichen Anforderungen entspricht oder um Änderungen unserer Leistungen in der Datenschutzerklärung umzusetzen, z.B. bei der Einführung neuer Funktionen. Für Ihren erneuten Besuch gilt dann die neue Datenschutzerklärung.
                </p>
                
                <h2>11. Kontakt</h2>
                <p>
                    Wenn Sie Fragen zum Datenschutz oder zur Ausübung Ihrer Rechte haben, kontaktieren Sie uns bitte unter folgender Adresse:
                </p>
                <p>
                    <strong>[Vereinsname einfügen]</strong><br>
                    [Straße und Hausnummer]<br>
                    [PLZ und Ort]<br>
                    [Land]<br>
                    E-Mail: [E-Mail-Adresse einfügen]<br>
                    Telefon: [Telefonnummer einfügen]
                </p>
            </div>
            
            <div class="mt-8 text-center">
                <a href="index.php" class="inline-block bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                    <i class="fas fa-arrow-left mr-2"></i>Zurück zur Startseite
                </a>
            </div>
        </div>
        
        <div class="mt-4 text-center text-sm text-gray-500">
            &copy; <?= date('Y') ?> <?= APP_NAME ?>
        </div>
    </div>
</body>
</html>