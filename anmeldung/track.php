<?php
require_once 'config.php';

// Tracking-Code aus GET-Parameter holen
$trackingCode = $_GET['code'] ?? '';

if (!empty($trackingCode)) {
    try {
        $db = getDB();
        
        // Prüfen, ob die E-Mail bereits als gelesen markiert wurde
        $stmt = $db->prepare("SELECT id, email_gelesen FROM anmeldungen WHERE tracking_code = ?");
        $stmt->execute([$trackingCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Wenn noch nicht als gelesen markiert, jetzt markieren
            if (empty($result['email_gelesen'])) {
                $gelesenDatum = date('Y-m-d H:i:s');
                $updateStmt = $db->prepare("UPDATE anmeldungen SET email_gelesen = ? WHERE id = ?");
                $updateStmt->execute([$gelesenDatum, $result['id']]);
            }
        }
    } catch (PDOException $e) {
        // Fehler ignorieren, Tracking ist optional
    }
}

// 1x1 transparentes GIF-Bild zurückgeben
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Transparentes 1x1 GIF
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit;
?>

