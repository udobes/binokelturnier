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
            border-right: 2px solid #667eea;
            background: #f5f5f5;
        }
        .content-frame {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        iframe {
            border: none;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <div class="frame-container">
        <div class="menu-frame">
            <iframe src="menu.php" name="menu"></iframe>
        </div>
        <div class="content-frame">
            <iframe src="turnier_erfassen.php" name="content"></iframe>
        </div>
    </div>
</body>
</html>

