<?php
require_once 'config.php';

// Datenbank initialisieren
initDB();

// Prüfen, ob das Formular abgeschickt wurde
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Daten aus dem Formular holen
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$mobilnummer = trim($_POST['mobilnummer'] ?? '');
$turnierId = isset($_POST['turnier_id']) && !empty($_POST['turnier_id']) ? intval($_POST['turnier_id']) : null;
$datenschutz = isset($_POST['datenschutz']) && $_POST['datenschutz'] == '1';

// Falls keine Turnier-ID per POST, versuche Standard-Turnier aus DB zu holen
if (!$turnierId) {
    try {
        require_once __DIR__ . '/../turnier/config.php';
        $db = getDB();
        $stmt = $db->prepare("SELECT wert FROM config WHERE schluessel = ?");
        $stmt->execute(['default_turnier_id']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['wert'])) {
            $turnierId = intval($result['wert']);
        }
    } catch (PDOException $e) {
        // Fehler ignorieren
    }
}

// Prüfen, ob das Turnierdatum überschritten ist
if ($turnierId) {
    require_once __DIR__ . '/../turnier/config.php';
    $turnier = getTurnierById($turnierId);
    if ($turnier && !empty($turnier['datum'])) {
        // Datum parsen (kann im Format YYYY-MM-DD oder DD.MM.YYYY sein)
        $turnierDatumStr = $turnier['datum'];
        $turnierDatum = null;
        
        // Versuche zuerst YYYY-MM-DD Format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $turnierDatumStr)) {
            $turnierDatum = DateTime::createFromFormat('Y-m-d', $turnierDatumStr);
        }
        // Versuche DD.MM.YYYY Format
        elseif (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $turnierDatumStr)) {
            $turnierDatum = DateTime::createFromFormat('d.m.Y', $turnierDatumStr);
        }
        
        if ($turnierDatum) {
            $turnierDatum->setTime(23, 59, 59); // Ende des Tages
            $heute = new DateTime();
            $heute->setTime(0, 0, 0); // Anfang des Tages
            
            if ($turnierDatum < $heute) {
                // Turnierdatum ist überschritten
                $redirectParams = 'error=datum_ueberschritten';
                if ($turnierId) {
                    $redirectParams .= '&turnier=' . $turnierId;
                }
                header('Location: index.php?' . $redirectParams);
                exit;
            }
        }
    }
}

// Validierung
if (empty($name) || empty($email)) {
    $redirectParams = 'error=empty&name=' . urlencode($name) . '&email=' . urlencode($email) . '&mobilnummer=' . urlencode($mobilnummer);
    if ($turnierId) {
        $redirectParams .= '&turnier=' . $turnierId;
    }
    header('Location: index.php?' . $redirectParams);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $redirectParams = 'error=email&name=' . urlencode($name) . '&email=' . urlencode($email) . '&mobilnummer=' . urlencode($mobilnummer);
    if ($turnierId) {
        $redirectParams .= '&turnier=' . $turnierId;
    }
    header('Location: index.php?' . $redirectParams);
    exit;
}

if (!$datenschutz) {
    $redirectParams = 'error=datenschutz&name=' . urlencode($name) . '&email=' . urlencode($email) . '&mobilnummer=' . urlencode($mobilnummer);
    if ($turnierId) {
        $redirectParams .= '&turnier=' . $turnierId;
    }
    header('Location: index.php?' . $redirectParams);
    exit;
}

try {
    // Datenbankverbindung
    $db = getDB();
    
    // Aktuelles Datum und Uhrzeit
    $anmeldedatum = date('Y-m-d H:i:s');
    
    // Daten in die Datenbank einfügen
    $stmt = $db->prepare("INSERT INTO anmeldungen (anmeldedatum, name, email, mobilnummer, turnier_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$anmeldedatum, $name, $email, $mobilnummer ?: null, $turnierId ?: null]);
    
    // ID der eingefügten Anmeldung holen
    $anmeldungId = $db->lastInsertId();
    
    // Sicherstellen, dass die ID gesetzt ist (falls lastInsertId() fehlschlägt, aus DB holen)
    if (empty($anmeldungId) || $anmeldungId == 0 || $anmeldungId === false) {
        // Alternative: ID direkt nach dem INSERT holen
        $stmt = $db->prepare("SELECT id FROM anmeldungen WHERE name = ? AND email = ? AND anmeldedatum = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$name, $email, $anmeldedatum]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['id']) && !empty($result['id'])) {
            $anmeldungId = $result['id'];
        } else {
            // Letzter Versuch: neueste ID für diese E-Mail holen
            $stmt = $db->prepare("SELECT id FROM anmeldungen WHERE email = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['id']) && !empty($result['id'])) {
                $anmeldungId = $result['id'];
            }
        }
    }
    
    // ID als String konvertieren und sicherstellen, dass sie nicht leer ist
    if (!empty($anmeldungId) && $anmeldungId != 0 && $anmeldungId !== false) {
        $anmeldungId = (string)$anmeldungId;
    } else {
        $anmeldungId = '';
    }
    
    // Debug: Konsolen-Ausgabe für Registriernummer (vor E-Mail-Versand)
    error_log("=== Process.php Debug ===");
    error_log("Anmeldung ID (lastInsertId): " . var_export($db->lastInsertId(), true));
    error_log("Anmeldung ID (final): " . var_export($anmeldungId, true));
    error_log("E-Mail: " . var_export($email, true));
    
    // Turnierdaten für E-Mail holen
    $turnier = null;
    if ($turnierId) {
        require_once __DIR__ . '/../turnier/config.php';
        $turnier = getTurnierById($turnierId);
    }
    
    // E-Mails senden (mit @ unterdrücken, um sicherzustellen, dass keine Ausgabe erfolgt)
    // 1. Bestätigungsmail an den Teilnehmer (an die E-Mail-Adresse aus dem Formular)
    // Mit Tracking-Code für Lesebestätigung
    @sendConfirmationEmail($name, $email, $anmeldungId, $turnier);
    // 2. Info-Mail an den Administrator
    @sendAdminNotification($name, $email, $anmeldungId, $mobilnummer);
    
    // Erfolgreich weiterleiten mit E-Mail-Adresse und Registriernummer
    // Verwende absolute URL oder relative URL ohne Probleme
    $redirectUrl = 'index.php?success=1&email=' . urlencode($email) . '&id=' . urlencode($anmeldungId);
    if ($turnierId) {
        $redirectUrl .= '&turnier=' . $turnierId;
    }
    
    error_log("Redirect URL: " . $redirectUrl);
    error_log("Headers sent: " . (headers_sent() ? 'JA' : 'NEIN'));
    error_log("===========================");
    
    // Alle Output-Buffer leeren, falls vorhanden
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Sicherstellen, dass keine Ausgabe vor der Weiterleitung erfolgt
    if (!headers_sent()) {
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        // Fallback: JavaScript-Weiterleitung
        // WICHTIG: json_encode() verwendet, nicht htmlspecialchars(), damit & nicht zu &amp; wird
        echo '<script>console.log("=== Process.php Debug ===");';
        echo 'console.log("Anmeldung ID:", ' . json_encode($anmeldungId) . ');';
        echo 'console.log("Redirect URL:", ' . json_encode($redirectUrl) . ');';
        echo 'console.log("Headers bereits gesendet - verwende JavaScript-Weiterleitung");';
        echo 'console.log("===========================");';
        echo 'window.location.href=' . json_encode($redirectUrl) . ';</script>';
        exit;
    }
    
} catch (PDOException $e) {
    // Bei Fehler weiterleiten
    $redirectParams = 'error=database&name=' . urlencode($name) . '&email=' . urlencode($email) . '&mobilnummer=' . urlencode($mobilnummer);
    if ($turnierId) {
        $redirectParams .= '&turnier=' . $turnierId;
    }
    header('Location: index.php?' . $redirectParams);
    exit;
} catch (Exception $e) {
    // Bei anderen Fehlern weiterleiten
    $redirectParams = 'error=database&name=' . urlencode($name) . '&email=' . urlencode($email) . '&mobilnummer=' . urlencode($mobilnummer);
    if ($turnierId) {
        $redirectParams .= '&turnier=' . $turnierId;
    }
    header('Location: index.php?' . $redirectParams);
    exit;
}
