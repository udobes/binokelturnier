<?php
require_once 'config.php';
initTurnierDB();

// Aktuelles Turnier holen für Anzeige im Kopfbereich
$aktuellesTurnier = getAktuellesTurnier();
?>
<div class="header">
    <h1>Binokel Turnier Verwaltung</h1>
    <?php if ($aktuellesTurnier): ?>
        <div class="turnier-info">
            <div class="info-item">
                <div class="info-label">Datum</div>
                <div class="info-value"><?php echo htmlspecialchars($aktuellesTurnier['datum']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Titel</div>
                <div class="info-value"><?php echo htmlspecialchars($aktuellesTurnier['titel']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Veranstalter</div>
                <div class="info-value"><?php echo htmlspecialchars($aktuellesTurnier['veranstalter']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Ort</div>
                <div class="info-value"><?php echo htmlspecialchars($aktuellesTurnier['ort']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Anzahl Spieler</div>
                <div class="info-value"><?php echo htmlspecialchars($aktuellesTurnier['anzahl_spieler'] ?? '0'); ?></div>
            </div>
        </div>
    <?php else: ?>
        <p class="no-turnier">Noch kein Turnier erfasst</p>
    <?php endif; ?>
</div>

<style>
.header {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.header h1 {
    color: #667eea;
    margin-bottom: 15px;
}
.turnier-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}
.info-item {
    padding: 10px;
    background: #f9f9f9;
    border-radius: 5px;
    border-left: 4px solid #667eea;
}
.info-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 5px;
}
.info-value {
    font-size: 16px;
    font-weight: bold;
    color: #333;
}
.no-turnier {
    color: #999;
    font-style: italic;
}
</style>