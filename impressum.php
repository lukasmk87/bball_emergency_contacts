<?php
// Impressum für die Basketball Notfallkontakte App
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impressum - <?= APP_NAME ?></title>
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
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-lg shadow-md p-6 sm:p-8">
            <div class="text-center mb-8">
                <i class="fas fa-basketball-ball text-orange-500 text-4xl sm:text-5xl"></i>
                <h1 class="text-2xl sm:text-3xl font-bold mt-4"><?= APP_NAME ?></h1>
                <h2 class="text-xl sm:text-2xl mt-2">Impressum</h2>
                <p class="text-gray-600 text-sm sm:text-base mt-2">Stand: <?= date('d.m.Y') ?></p>
            </div>
            
            <div class="prose max-w-none">
                <h2>Angaben gemäß § 5 TMG</h2>
                <p>
                    [Vereinsname einfügen]<br>
                    [Straße und Hausnummer]<br>
                    [PLZ und Ort]<br>
                    [Land]
                </p>
                
                <p>
                    <strong>Vertreten durch:</strong><br>
                    [Name des Vertreters/Vorstands]
                </p>
                
                <h2>Kontakt</h2>
                <p>
                    Telefon: [Telefonnummer einfügen]<br>
                    E-Mail: [E-Mail-Adresse einfügen]
                </p>
                
                <h2>Registereintrag</h2>
                <p>
                    Eintragung im Vereinsregister.<br>
                    Registergericht: [Registergericht einfügen]<br>
                    Registernummer: [Registernummer einfügen]
                </p>
                
                <h2>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h2>
                <p>
                    [Name]<br>
                    [Straße und Hausnummer]<br>
                    [PLZ und Ort]<br>
                    [Land]
                </p>
                
                <h2>Haftung für Inhalte</h2>
                <p>
                    Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen 
                    Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, 
                    übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, 
                    die auf eine rechtswidrige Tätigkeit hinweisen.
                </p>
                <p>
                    Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen 
                    bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer 
                    konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir 
                    diese Inhalte umgehend entfernen.
                </p>
                
                <h2>Haftung für Links</h2>
                <p>
                    Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. 
                    Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten 
                    Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Die verlinkten Seiten wurden 
                    zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt 
                    der Verlinkung nicht erkennbar.
                </p>
                <p>
                    Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer 
                    Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links 
                    umgehend entfernen.
                </p>
                
                <h2>Urheberrecht</h2>
                <p>
                    Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen 
                    Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der 
                    Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers. 
                    Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen Gebrauch gestattet.
                </p>
                <p>
                    Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte Dritter 
                    beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem auf eine 
                    Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden 
                    von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.
                </p>