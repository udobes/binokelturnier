<?php
require_once 'config.php';
initTurnierDB();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Menü</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            height: 100vh;
        }
        .menu {
            list-style: none;
            padding: 20px 0;
        }
        .menu-item {
            margin: 0;
        }
        .menu-item a {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #ddd;
            transition: background-color 0.3s;
        }
        .menu-item a:hover {
            background-color: #667eea;
            color: white;
        }
        .menu-item a.active {
            background-color: #667eea;
            color: white;
            font-weight: bold;
        }
        h2 {
            padding: 20px;
            background: #667eea;
            color: white;
            margin-bottom: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <h2>Turnier-Menü</h2>
    <ul class="menu">
        <li class="menu-item">
            <a href="turnier_erfassen.php" target="content" class="active">1. Turnier erfassen</a>
        </li>
        <li class="menu-item">
            <a href="turnier_starten.php" target="content">2. Turnier starten</a>
        </li>
        <li class="menu-item">
            <a href="../anmeldung/verwaltung.php" target="content">3. Anmeldung</a>
        </li>
        <li class="menu-item">
            <a href="registrierung.php" target="content">4. Registrierung</a>
        </li>
        <li class="menu-item">
            <a href="spielrunde.php" target="content">5. Spielrunde</a>
        </li>
        <li class="menu-item">
            <a href="auswertung.php" target="content">6. Auswertung</a>
        </li>
    </ul>
    <script>
        // Aktives Menü-Element markieren
        document.querySelectorAll('.menu-item a').forEach(function(link) {
            link.addEventListener('click', function() {
                document.querySelectorAll('.menu-item a').forEach(function(a) {
                    a.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>

