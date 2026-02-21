<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/AKS-Logo.ico">
    <title>Binokel Turnier Verwaltung</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            overflow: hidden;
        }
        .frame-container {
            display: flex;
            height: 100vh;
        }
        .menu-frame {
            width: 200px;
            min-width: 200px;
            border-right: 2px solid #667eea;
            background: #f5f5f5;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .content-frame {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .content-frame iframe {
            flex: 1;
        }
        iframe {
            border: none;
            width: 100%;
            height: 100%;
        }
        /* Burger-Button: nur bei schmalem Viewport (Hochkant Smartphone) */
        .burger-btn {
            display: none;
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 1001;
            width: 44px;
            height: 44px;
            padding: 0;
            border: none;
            border-radius: 6px;
            background: #667eea;
            color: white;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .burger-btn:hover {
            background: #5568d3;
        }
        .menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 999;
        }
        @media (max-width: 768px), (orientation: portrait) and (max-width: 900px) {
            .burger-btn {
                display: block;
            }
            .menu-frame {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                width: 240px;
                max-width: 85vw;
                z-index: 1000;
                transform: translateX(-100%);
                box-shadow: none;
            }
            .menu-frame.open {
                transform: translateX(0);
                box-shadow: 4px 0 20px rgba(0,0,0,0.2);
            }
            .menu-frame.open + .content-frame {
                margin-left: 0;
            }
            .menu-overlay.open {
                display: block;
            }
        }
    </style>
</head>
<body>
    <button type="button" class="burger-btn" id="burger-btn" aria-label="Menü öffnen">☰</button>
    <div class="menu-overlay" id="menu-overlay"></div>
    <div class="frame-container">
        <div class="menu-frame" id="menu-frame">
            <iframe src="menu.php" name="menu"></iframe>
        </div>
        <div class="content-frame">
            <iframe src="turnier_erfassen.php" name="content"></iframe>
        </div>
    </div>
    <script>
        (function() {
            var btn = document.getElementById('burger-btn');
            var frame = document.getElementById('menu-frame');
            var overlay = document.getElementById('menu-overlay');
            function toggleMenu() {
                frame.classList.toggle('open');
                overlay.classList.toggle('open');
                btn.setAttribute('aria-label', frame.classList.contains('open') ? 'Menü schließen' : 'Menü öffnen');
            }
            function closeMenu() {
                frame.classList.remove('open');
                overlay.classList.remove('open');
                btn.setAttribute('aria-label', 'Menü öffnen');
            }
            if (btn) btn.addEventListener('click', toggleMenu);
            if (overlay) overlay.addEventListener('click', closeMenu);
            window.addEventListener('message', function(e) {
                if (e.data === 'turnierMenuClose') closeMenu();
            });
        })();
    </script>
</body>
</html>

