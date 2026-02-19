<?php
// registriernummern_erhoehen.php
// Erstellt automatisch ein Backup der Datenbank vor den Änderungen

require_once __DIR__ . '/anmeldung/config.php';

initDB();
$db = getDB();

echo "=== Registriernummern < 1000 um 1000 erhöhen ===\n\n";

// Backup der Datenbank erstellen
echo "Erstelle Backup der Datenbank...\n";
$dbPath = DB_PATH;
$backupPath = $dbPath . '_';

// Datenbankverbindung schließen, damit die Datei kopiert werden kann
$db = null;

if (!file_exists($dbPath)) {
    die("FEHLER: Datenbankdatei nicht gefunden: {$dbPath}\n");
}

// Backup-Datei kopieren
if (copy($dbPath, $backupPath)) {
    echo "Backup erfolgreich erstellt: {$backupPath}\n\n";
} else {
    die("FEHLER: Backup konnte nicht erstellt werden. Bitte prüfen Sie die Dateiberechtigungen.\n");
}

// Datenbankverbindung wiederherstellen
$db = getDB();

// WICHTIG: Foreign Keys temporär deaktivieren, da wir IDs ändern
$db->exec("PRAGMA foreign_keys = OFF");

try {
    $db->beginTransaction();
    
    // 1. Prüfen, ob es Konflikte gibt (wenn ID + 1000 bereits existiert)
    echo "Prüfe auf mögliche Konflikte...\n";
    $stmt = $db->query("
        SELECT a1.id, a1.id + 1000 as neue_id
        FROM anmeldungen a1
        WHERE a1.id < 1000
        AND EXISTS (
            SELECT 1 FROM anmeldungen a2 WHERE a2.id = a1.id + 1000
        )
    ");
    $konflikte = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($konflikte)) {
        echo "WARNUNG: Es gibt Konflikte! Die folgenden IDs würden auf bereits existierende IDs zeigen:\n";
        foreach ($konflikte as $konflikt) {
            echo "  ID {$konflikt['id']} würde zu {$konflikt['neue_id']} werden (existiert bereits!)\n";
        }
        echo "\nMöchten Sie trotzdem fortfahren? (Dies wird die bestehenden Einträge überschreiben!)\n";
        // Für Sicherheit: Script hier stoppen, wenn Konflikte gefunden werden
        // Entfernen Sie die folgende Zeile, wenn Sie trotzdem fortfahren möchten:
        // throw new Exception("Konflikte gefunden - Script gestoppt für Sicherheit");
    } else {
        echo "Keine Konflikte gefunden. Fortfahren...\n\n";
    }
    
    // 2. Alle IDs < 1000 holen (sortiert absteigend, um Konflikte zu vermeiden)
    echo "Suche Anmeldungen mit ID < 1000...\n";
    $stmt = $db->query("SELECT id FROM anmeldungen WHERE id < 1000 ORDER BY id DESC");
    $alleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $anzahl = count($alleIds);
    echo "Gefunden: {$anzahl} Anmeldungen mit ID < 1000\n";
    
    if ($anzahl == 0) {
        echo "Keine Anmeldungen mit ID < 1000 gefunden. Nichts zu tun.\n";
        $db->rollBack();
        $db->exec("PRAGMA foreign_keys = ON");
        exit(0);
    }
    
    // Temporäre Tabelle erstellen für Mapping
    $db->exec("CREATE TEMPORARY TABLE id_mapping (alt_id INTEGER, neu_id INTEGER)");
    
    // Mapping eintragen (nur für IDs < 1000)
    foreach ($alleIds as $altId) {
        $neuId = $altId + 1000;
        $stmt = $db->prepare("INSERT INTO id_mapping (alt_id, neu_id) VALUES (?, ?)");
        $stmt->execute([$altId, $neuId]);
    }
    
    // 3. Neue Tabelle erstellen: IDs < 1000 erhöhen, IDs >= 1000 unverändert lassen
    echo "Erstelle neue Tabelle mit erhöhten IDs...\n";
    $db->exec("
        CREATE TABLE anmeldungen_new AS
        SELECT 
            COALESCE(m.neu_id, a.id) as id,
            a.anmeldedatum,
            a.name,
            a.email,
            a.tracking_code,
            a.email_gesendet,
            a.email_gelesen,
            a.mobilnummer,
            a.turnier_id,
            a.\"alter\",
            a.name_auf_wertungsliste
        FROM anmeldungen a
        LEFT JOIN id_mapping m ON a.id = m.alt_id
    ");
    
    // 4. Alte Tabelle löschen und neue umbenennen
    echo "Ersetze alte Tabelle...\n";
    $db->exec("DROP TABLE anmeldungen");
    $db->exec("ALTER TABLE anmeldungen_new RENAME TO anmeldungen");
    
    // 5. Primary Key wiederherstellen
    echo "Stelle Primary Key wieder her...\n";
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS anmeldungen_id_idx ON anmeldungen(id)");
    
    // 6. Referenzen in turnier_registrierungen aktualisieren (nur für IDs < 1000)
    echo "Aktualisiere Referenzen in turnier_registrierungen...\n";
    $db->exec("
        UPDATE turnier_registrierungen
        SET anmeldung_id = (
            SELECT neu_id FROM id_mapping WHERE alt_id = turnier_registrierungen.anmeldung_id
        )
        WHERE anmeldung_id IN (SELECT alt_id FROM id_mapping)
    ");
    $affectedRegistrierungen = $db->query("SELECT changes()")->fetchColumn();
    echo "  {$affectedRegistrierungen} Referenzen aktualisiert\n";
    
    // 7. Sequence für AUTOINCREMENT zurücksetzen
    echo "Setze AUTOINCREMENT-Sequenz zurück...\n";
    $stmt = $db->query("SELECT MAX(id) FROM anmeldungen");
    $maxId = $stmt->fetchColumn();
    if ($maxId) {
        $db->exec("DELETE FROM sqlite_sequence WHERE name='anmeldungen'");
        $db->exec("INSERT INTO sqlite_sequence (name, seq) VALUES ('anmeldungen', {$maxId})");
    }
    
    // 8. Temporäre Tabelle löschen
    $db->exec("DROP TABLE id_mapping");
    
    // Foreign Keys wieder aktivieren
    $db->exec("PRAGMA foreign_keys = ON");
    
    $db->commit();
    
    echo "\n=== Erfolgreich abgeschlossen! ===\n";
    echo "Alle {$anzahl} Registriernummern < 1000 wurden um 1000 erhöht.\n";
    echo "Beispiel: ID 1 ist jetzt ID 1001, ID 2 ist jetzt ID 1002, etc.\n";
    echo "IDs >= 1000 blieben unverändert.\n";
    
} catch (Exception $e) {
    $db->rollBack();
    $db->exec("PRAGMA foreign_keys = ON");
    echo "\n=== FEHLER ===\n";
    echo "Die Änderungen wurden rückgängig gemacht.\n";
    echo "Fehler: " . $e->getMessage() . "\n";
    exit(1);
}
?>

