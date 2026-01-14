<?php
// Datenbankkonfiguration - verwendet die gleiche DB wie anmeldung
// getDB() wird von anmeldung/config.php bereitgestellt
// Falls getDB() noch nicht existiert, lade anmeldung/config.php
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../anmeldung/config.php';
}

// Datenbank initialisieren - Turnier-Tabelle erstellen
function initTurnierDB() {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS turnier (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        datum TEXT NOT NULL,
        titel TEXT NOT NULL,
        veranstalter TEXT NOT NULL,
        ort TEXT NOT NULL,
        anzahl_spieler INTEGER DEFAULT 0,
        anzahl_runden INTEGER DEFAULT 3,
        spieler_pro_runde INTEGER DEFAULT 3,
        erstellt_am TEXT DEFAULT CURRENT_TIMESTAMP,
        aktiv INTEGER DEFAULT 1
    )");
    
    // Spalten hinzufügen, falls sie noch nicht existieren (für bestehende Datenbanken)
    try {
        $db->exec("ALTER TABLE turnier ADD COLUMN anzahl_spieler INTEGER DEFAULT 0");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE turnier ADD COLUMN anzahl_runden INTEGER DEFAULT 3");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE turnier ADD COLUMN spieler_pro_runde INTEGER DEFAULT 3");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE turnier ADD COLUMN aktive_runde INTEGER DEFAULT NULL");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE turnier ADD COLUMN aktive_ergebnis_runde INTEGER DEFAULT NULL");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE turnier ADD COLUMN googlemaps_link TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE turnier ADD COLUMN startzeit TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    try {
        $db->exec("ALTER TABLE turnier ADD COLUMN einlasszeit TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    
    // Tabelle für Spieler im Turnier erstellen
    $db->exec("CREATE TABLE IF NOT EXISTS turnier_spieler (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        turnier_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        spieler_nummer INTEGER NOT NULL,
        FOREIGN KEY (turnier_id) REFERENCES turnier(id)
    )");
    
    // Tabelle für Tischzuordnungen erstellen
    // spieler_id ist jetzt eine Teilnehmernummer (1, 2, 3, ...) und kein Foreign Key mehr
    $db->exec("CREATE TABLE IF NOT EXISTS turnier_zuordnungen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        turnier_id INTEGER NOT NULL,
        runde INTEGER NOT NULL,
        tisch INTEGER NOT NULL,
        spieler_id INTEGER NOT NULL,
        FOREIGN KEY (turnier_id) REFERENCES turnier(id)
    )");
    
    // Tabelle für Turnier-Registrierungen erstellen (OHNE name, email, mobilnummer - kommen aus anmeldungen)
    $db->exec("CREATE TABLE IF NOT EXISTS turnier_registrierungen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        turnier_id INTEGER NOT NULL,
        anmeldung_id INTEGER,
        startnummer INTEGER NOT NULL,
        registriert_am TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (turnier_id) REFERENCES turnier(id)
    )");
    
    // Foreign Keys aktivieren (SQLite)
    try {
        $db->exec("PRAGMA foreign_keys = ON");
    } catch (PDOException $e) {
        // Kann bei einigen SQLite-Versionen fehlschlagen, ignorieren
    }
    
    // Index für anmeldung_id hinzufügen
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_registrierungen_anmeldung ON turnier_registrierungen(anmeldung_id)");
    } catch (PDOException $e) {
        // Index existiert bereits, ignorieren
    }
    
    // Index für bessere Performance
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_spieler_turnier ON turnier_spieler(turnier_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_zuordnungen_turnier ON turnier_zuordnungen(turnier_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_zuordnungen_spieler ON turnier_zuordnungen(spieler_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_registrierungen_turnier ON turnier_registrierungen(turnier_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_registrierungen_startnummer ON turnier_registrierungen(turnier_id, startnummer)");
    } catch (PDOException $e) {
        // Index existiert bereits, ignorieren
    }
    
    // Gesamtpunkte-Spalte hinzufügen (falls noch nicht vorhanden)
    try {
        $db->exec("ALTER TABLE turnier_registrierungen ADD COLUMN gesamtpunkte INTEGER DEFAULT NULL");
    } catch (PDOException $e) {
        // Spalte existiert bereits, ignorieren
    }
    
    // Migration: Überflüssige Spalten entfernen (rundeX_punkte, rundeX_eingetragen_am, name, email, mobilnummer)
    // SQLite unterstützt DROP COLUMN erst ab Version 3.35.0, daher Migration durchführen
    try {
        // Prüfen, welche Spalten noch existieren
        $stmt = $db->query("PRAGMA table_info(turnier_registrierungen)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasOldRundenColumns = false;
        $hasOldDataColumns = false;
        $columnNames = [];
        foreach ($columns as $col) {
            $columnNames[] = $col['name'];
            if (preg_match('/^runde\d+_(punkte|eingetragen_am)$/', $col['name'])) {
                $hasOldRundenColumns = true;
            }
            if (in_array($col['name'], ['name', 'email', 'mobilnummer'])) {
                $hasOldDataColumns = true;
            }
        }
        
        if ($hasOldRundenColumns || $hasOldDataColumns) {
            // Neue Tabelle ohne die überflüssigen Spalten erstellen
            $db->exec("CREATE TABLE turnier_registrierungen_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                turnier_id INTEGER NOT NULL,
                anmeldung_id INTEGER,
                startnummer INTEGER NOT NULL,
                registriert_am TEXT DEFAULT CURRENT_TIMESTAMP,
                gesamtpunkte INTEGER DEFAULT NULL,
                UNIQUE(turnier_id, startnummer),
                FOREIGN KEY (turnier_id) REFERENCES turnier(id)
            )");
            
            // Daten kopieren (nur die Spalten, die in der neuen Tabelle existieren)
            // Prüfen welche Spalten in der alten Tabelle existieren
            $selectColumns = [];
            $newColumns = ['id', 'turnier_id', 'anmeldung_id', 'startnummer', 'registriert_am'];
            
            // Gesamtpunkte nur kopieren, wenn es existiert
            if (in_array('gesamtpunkte', $columnNames)) {
                $newColumns[] = 'gesamtpunkte';
            }
            
            foreach ($newColumns as $col) {
                if (in_array($col, $columnNames)) {
                    $selectColumns[] = $col;
                }
            }
            
            if (!empty($selectColumns)) {
                $selectCols = implode(', ', $selectColumns);
                $insertCols = implode(', ', $selectColumns);
                $db->exec("INSERT INTO turnier_registrierungen_new ($insertCols) SELECT $selectCols FROM turnier_registrierungen");
            }
            
            // Alte Tabelle löschen
            $db->exec("DROP TABLE turnier_registrierungen");
            
            // Neue Tabelle umbenennen
            $db->exec("ALTER TABLE turnier_registrierungen_new RENAME TO turnier_registrierungen");
            
            // Indizes neu erstellen
            try {
                $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_registrierungen_turnier ON turnier_registrierungen(turnier_id)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_registrierungen_startnummer ON turnier_registrierungen(turnier_id, startnummer)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_registrierungen_anmeldung ON turnier_registrierungen(anmeldung_id)");
            } catch (PDOException $e) {
                // Index existiert bereits, ignorieren
            }
        }
    } catch (PDOException $e) {
        // Fehler bei Migration ignorieren (Tabelle könnte bereits migriert sein)
        error_log("Migration Fehler (kann ignoriert werden): " . $e->getMessage());
    }
    
    // Tabelle für Turnier-Ergebnisse erstellen
    $db->exec("CREATE TABLE IF NOT EXISTS turnier_ergebnisse (
        erg_id INTEGER PRIMARY KEY AUTOINCREMENT,
        turnier_id INTEGER NOT NULL,
        runde INTEGER NOT NULL,
        spieler INTEGER NOT NULL,
        punkte INTEGER DEFAULT NULL,
        geaendert_am TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (turnier_id) REFERENCES turnier(id),
        UNIQUE(turnier_id, runde, spieler)
    )");
    
    // Index für bessere Performance
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_ergebnisse_turnier ON turnier_ergebnisse(turnier_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_ergebnisse_spieler ON turnier_ergebnisse(turnier_id, spieler)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_turnier_ergebnisse_runde ON turnier_ergebnisse(turnier_id, runde)");
    } catch (PDOException $e) {
        // Index existiert bereits, ignorieren
    }
    
    // Migration: Bestehende Daten aus turnier_registrierungen migrieren
    try {
        // Prüfen, ob Migration bereits durchgeführt wurde
        $stmt = $db->query("SELECT COUNT(*) as count FROM turnier_ergebnisse");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            // Migration durchführen
            for ($i = 1; $i <= 10; $i++) {
                $spaltenName = 'runde' . $i . '_punkte';
                try {
                    // Prüfen, ob Spalte existiert
                    $stmt = $db->query("PRAGMA table_info(turnier_registrierungen)");
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $columnExists = false;
                    foreach ($columns as $col) {
                        if ($col['name'] === $spaltenName) {
                            $columnExists = true;
                            break;
                        }
                    }
                    
                    if ($columnExists) {
                        // Daten migrieren
                        $stmt = $db->prepare("
                            INSERT INTO turnier_ergebnisse (turnier_id, runde, spieler, punkte, geaendert_am)
                            SELECT turnier_id, ?, startnummer, " . $spaltenName . ", 
                                   COALESCE(runde" . $i . "_eingetragen_am, registriert_am)
                            FROM turnier_registrierungen
                            WHERE " . $spaltenName . " IS NOT NULL
                        ");
                        $stmt->execute([$i]);
                    }
                } catch (PDOException $e) {
                    // Fehler bei Migration ignorieren
                    error_log("Migration Fehler für Runde $i: " . $e->getMessage());
                }
            }
        }
    } catch (PDOException $e) {
        // Fehler ignorieren
    }
}

// Aktuelles Turnier holen
function getAktuellesTurnier() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM turnier WHERE aktiv = 1 ORDER BY id DESC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Aktive Runde für ein Turnier setzen
function setzeAktiveRunde($turnierId, $runde) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE turnier SET aktive_runde = ? WHERE id = ?");
    $stmt->execute([$runde, $turnierId]);
}

// Alle Runden deaktivieren
function deaktiviereAlleRunden($turnierId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE turnier SET aktive_runde = NULL WHERE id = ?");
    $stmt->execute([$turnierId]);
}

// Aktive Ergebnisrunde setzen (0 = Ergebnisse, 1-10 = Runde, NULL = keine)
function setzeAktiveErgebnisRunde($turnierId, $runde) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE turnier SET aktive_ergebnis_runde = ? WHERE id = ?");
    $stmt->execute([$runde, $turnierId]);
}

// Aktive Ergebnisrunde deaktivieren
function deaktiviereAktiveErgebnisRunde($turnierId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE turnier SET aktive_ergebnis_runde = NULL WHERE id = ?");
    $stmt->execute([$turnierId]);
}

// Ergebnis speichern oder aktualisieren
function speichereErgebnis($turnierId, $runde, $spieler, $punkte) {
    $db = null;
    try {
        $db = getDB();
        $geaendertAm = date('Y-m-d H:i:s');
        
        // Transaktion starten für atomare Operationen
        $db->beginTransaction();
        
        // Prüfen, ob Ergebnis bereits existiert
        $stmt = $db->prepare("SELECT erg_id FROM turnier_ergebnisse WHERE turnier_id = ? AND runde = ? AND spieler = ?");
        $stmt->execute([$turnierId, $runde, $spieler]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Aktualisieren
            $stmt = $db->prepare("UPDATE turnier_ergebnisse SET punkte = ?, geaendert_am = ? WHERE erg_id = ?");
            $stmt->execute([$punkte, $geaendertAm, $existing['erg_id']]);
        } else {
            // Neu anlegen - verwende INSERT OR IGNORE mit nachfolgendem UPDATE falls nötig
            $stmt = $db->prepare("INSERT OR IGNORE INTO turnier_ergebnisse (turnier_id, runde, spieler, punkte, geaendert_am) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$turnierId, $runde, $spieler, $punkte, $geaendertAm]);
            
            // Prüfen, ob INSERT erfolgreich war (wenn nicht, existiert bereits ein Eintrag)
            if ($stmt->rowCount() == 0) {
                // Eintrag existiert bereits, aktualisieren
                $stmt = $db->prepare("UPDATE turnier_ergebnisse SET punkte = ?, geaendert_am = ? WHERE turnier_id = ? AND runde = ? AND spieler = ?");
                $stmt->execute([$punkte, $geaendertAm, $turnierId, $runde, $spieler]);
            }
        }
        
        // Gesamtpunkte neu berechnen (innerhalb der Transaktion)
        $stmt = $db->prepare("SELECT SUM(punkte) as gesamt FROM turnier_ergebnisse WHERE turnier_id = ? AND spieler = ? AND punkte IS NOT NULL");
        $stmt->execute([$turnierId, $spieler]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $gesamtpunkte = $result['gesamt'] ?? 0;
        
        $stmt = $db->prepare("UPDATE turnier_registrierungen SET gesamtpunkte = ? WHERE turnier_id = ? AND startnummer = ?");
        $stmt->execute([$gesamtpunkte, $turnierId, $spieler]);
        
        // Transaktion committen
        $db->commit();
    } catch (PDOException $e) {
        // Bei Fehler Transaktion zurückrollen
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Fehler beim Speichern des Ergebnisses: " . $e->getMessage());
        throw $e;
    }
}

// Gesamtpunkte für einen Spieler berechnen (wird jetzt in speichereErgebnis integriert)
function berechneGesamtpunkte($turnierId, $spieler) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT SUM(punkte) as gesamt FROM turnier_ergebnisse WHERE turnier_id = ? AND spieler = ? AND punkte IS NOT NULL");
        $stmt->execute([$turnierId, $spieler]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $gesamtpunkte = $result['gesamt'] ?? 0;
        
        $stmt = $db->prepare("UPDATE turnier_registrierungen SET gesamtpunkte = ? WHERE turnier_id = ? AND startnummer = ?");
        $stmt->execute([$gesamtpunkte, $turnierId, $spieler]);
    } catch (PDOException $e) {
        error_log("Fehler beim Berechnen der Gesamtpunkte: " . $e->getMessage());
        throw $e;
    }
}

// Ergebnis für einen Spieler in einer Runde abrufen
function getErgebnis($turnierId, $runde, $spieler) {
    $db = getDB();
    $stmt = $db->prepare("SELECT punkte, geaendert_am FROM turnier_ergebnisse WHERE turnier_id = ? AND runde = ? AND spieler = ?");
    $stmt->execute([$turnierId, $runde, $spieler]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Alle Ergebnisse für einen Spieler abrufen
function getErgebnisseFuerSpieler($turnierId, $spieler) {
    $db = getDB();
    $stmt = $db->prepare("SELECT runde, punkte, geaendert_am FROM turnier_ergebnisse WHERE turnier_id = ? AND spieler = ? ORDER BY runde");
    $stmt->execute([$turnierId, $spieler]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Alle Ergebnisse für ein Turnier abrufen (mit JOIN zu turnier_registrierungen)
function getErgebnisseFuerTurnier($turnierId, $anzahlRunden) {
    $db = getDB();
    
    // Basis-Daten aus turnier_registrierungen (mit JOIN zu anmeldungen für Name)
    $stmt = $db->prepare("SELECT tr.startnummer, a.name, tr.gesamtpunkte 
        FROM turnier_registrierungen tr 
        LEFT JOIN anmeldungen a ON tr.anmeldung_id = a.id 
        WHERE tr.turnier_id = ? 
        ORDER BY tr.startnummer");
    $stmt->execute([$turnierId]);
    $registrierungen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ergebnisse für jede Runde hinzufügen
    foreach ($registrierungen as &$reg) {
        for ($i = 1; $i <= $anzahlRunden; $i++) {
            $stmt = $db->prepare("SELECT punkte FROM turnier_ergebnisse WHERE turnier_id = ? AND runde = ? AND spieler = ?");
            $stmt->execute([$turnierId, $i, $reg['startnummer']]);
            $ergebnis = $stmt->fetch(PDO::FETCH_ASSOC);
            $reg['runde' . $i . '_punkte'] = $ergebnis ? $ergebnis['punkte'] : null;
        }
    }
    unset($reg);
    
    return $registrierungen;
}

// Alle Turniere holen (sortiert nach Datum)
function getAllTurniere() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM turnier ORDER BY datum DESC, id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Einzelnes Turnier nach ID holen
function getTurnierById($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM turnier WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Spieler für ein Turnier holen
function getTurnierSpieler($turnierId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM turnier_spieler WHERE turnier_id = ? ORDER BY spieler_nummer");
    $stmt->execute([$turnierId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Spieler zu Turnier hinzufügen
function addTurnierSpieler($turnierId, $name, $spielerNummer) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO turnier_spieler (turnier_id, name, spieler_nummer) VALUES (?, ?, ?)");
    return $stmt->execute([$turnierId, $name, $spielerNummer]);
}

// Spieler aus Turnier entfernen
function removeTurnierSpieler($spielerId) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM turnier_spieler WHERE id = ?");
    return $stmt->execute([$spielerId]);
}

// Alle Spieler eines Turniers löschen
function clearTurnierSpieler($turnierId) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM turnier_spieler WHERE turnier_id = ?");
    return $stmt->execute([$turnierId]);
}

// Tischzuordnungen für ein Turnier holen
function getTurnierZuordnungen($turnierId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM turnier_zuordnungen WHERE turnier_id = ? ORDER BY runde, tisch, spieler_id");
    $stmt->execute([$turnierId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Nächste verfügbare Startnummer für ein Turnier holen
function getNextStartnummer($turnierId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT MAX(startnummer) as max_nummer FROM turnier_registrierungen WHERE turnier_id = ?");
    $stmt->execute([$turnierId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $maxNummer = $result['max_nummer'] ?? 0;
    return $maxNummer + 1;
}

// Registrierung über Registriernummer erstellen
function registriereDurchNummer($turnierId, $registrierNummer) {
    $db = getDB();
    
    // Prüfen ob Anmeldung existiert
    $stmt = $db->prepare("SELECT id, name, email FROM anmeldungen WHERE id = ?");
    $stmt->execute([$registrierNummer]);
    $anmeldung = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anmeldung) {
        return ['success' => false, 'error' => 'Registriernummer nicht gefunden.'];
    }
    
    // Prüfen ob bereits registriert
    $stmt = $db->prepare("SELECT id FROM turnier_registrierungen WHERE turnier_id = ? AND anmeldung_id = ?");
    $stmt->execute([$turnierId, $registrierNummer]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Diese Person ist bereits für dieses Turnier registriert.'];
    }
    
    // Startnummer vergeben
    $startnummer = getNextStartnummer($turnierId);
    
    // Registrierung speichern (nur anmeldung_id, Name und Email kommen aus anmeldungen)
    $stmt = $db->prepare("INSERT INTO turnier_registrierungen (turnier_id, anmeldung_id, startnummer) VALUES (?, ?, ?)");
    $stmt->execute([$turnierId, $registrierNummer, $startnummer]);
    
    return ['success' => true, 'startnummer' => $startnummer, 'name' => $anmeldung['name']];
}

// Registrierung über Registriernummer mit angepassten Daten erstellen
function registriereDurchNummerMitDaten($turnierId, $registrierNummer, $name, $email = null, $mobilnummer = null) {
    $db = getDB();
    
    // Prüfen ob Anmeldung existiert
    $stmt = $db->prepare("SELECT id FROM anmeldungen WHERE id = ?");
    $stmt->execute([$registrierNummer]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'error' => 'Registriernummer nicht gefunden.'];
    }
    
    // Prüfen ob bereits registriert
    $stmt = $db->prepare("SELECT id FROM turnier_registrierungen WHERE turnier_id = ? AND anmeldung_id = ?");
    $stmt->execute([$turnierId, $registrierNummer]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Diese Person ist bereits für dieses Turnier registriert.'];
    }
    
    // Startnummer vergeben
    $startnummer = getNextStartnummer($turnierId);
    
    // Daten in anmeldungen aktualisieren, falls sie geändert wurden
    $stmt = $db->prepare("UPDATE anmeldungen SET name = ?, email = ?, mobilnummer = ? WHERE id = ?");
    $stmt->execute([$name, $email, $mobilnummer, $registrierNummer]);
    
    // Registrierung speichern (nur anmeldung_id, Name, Email und Mobilnummer kommen aus anmeldungen)
    $stmt = $db->prepare("INSERT INTO turnier_registrierungen (turnier_id, anmeldung_id, startnummer) VALUES (?, ?, ?)");
    $stmt->execute([$turnierId, $registrierNummer, $startnummer]);
    
    return ['success' => true, 'startnummer' => $startnummer, 'name' => $name];
}

// Neue Person registrieren
function registriereNeuePerson($turnierId, $name, $email = null, $mobilnummer = null) {
    $db = getDB();
    
    if (empty($name)) {
        return ['success' => false, 'error' => 'Name ist erforderlich.'];
    }
    
    // Zuerst in anmeldungen-Tabelle eintragen
    // Sicherstellen, dass anmeldung/config.php geladen ist
    if (!function_exists('initDB')) {
        require_once __DIR__ . '/../anmeldung/config.php';
    }
    initDB(); // Sicherstellen, dass Tabelle existiert
    
    // E-Mail ist erforderlich für anmeldungen, falls nicht vorhanden, verwenden wir eine Platzhalter-E-Mail
    $emailFuerAnmeldung = !empty($email) ? $email : 'keine-email-' . time() . '@turnier.local';
    
    $anmeldedatum = date('Y-m-d H:i:s');
    $stmt = $db->prepare("INSERT INTO anmeldungen (anmeldedatum, name, email, mobilnummer) VALUES (?, ?, ?, ?)");
    $stmt->execute([$anmeldedatum, $name, $emailFuerAnmeldung, $mobilnummer]);
    $anmeldungId = $db->lastInsertId();
    
    // Falls lastInsertId() fehlschlägt, ID aus DB holen
    if (empty($anmeldungId) || $anmeldungId == 0) {
        $stmt = $db->prepare("SELECT id FROM anmeldungen WHERE name = ? AND email = ? AND anmeldedatum = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$name, $emailFuerAnmeldung, $anmeldedatum]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['id'])) {
            $anmeldungId = $result['id'];
        }
    }
    
    // Startnummer vergeben
    $startnummer = getNextStartnummer($turnierId);
    
    // Registrierung speichern (nur anmeldung_id, Name, Email und Mobilnummer kommen aus anmeldungen)
    $stmt = $db->prepare("INSERT INTO turnier_registrierungen (turnier_id, anmeldung_id, startnummer) VALUES (?, ?, ?)");
    $stmt->execute([$turnierId, $anmeldungId, $startnummer]);
    
    return ['success' => true, 'startnummer' => $startnummer, 'name' => $name, 'anmeldung_id' => $anmeldungId];
}

// Alle Registrierungen für ein Turnier holen (mit JOIN zu anmeldungen für Name, Email, Mobilnummer)
function getTurnierRegistrierungen($turnierId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 
            tr.id,
            tr.turnier_id,
            tr.anmeldung_id,
            tr.startnummer,
            tr.registriert_am,
            tr.gesamtpunkte,
            a.name,
            a.email,
            a.mobilnummer
        FROM turnier_registrierungen tr
        LEFT JOIN anmeldungen a ON tr.anmeldung_id = a.id
        WHERE tr.turnier_id = ? 
        ORDER BY tr.startnummer
    ");
    $stmt->execute([$turnierId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}