<?php
/**
 * Test-Skript zum Finden verfügbarer Drucker und Ports
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Drucker-Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Drucker und Port-Test</h1>
    
    <h2>Verfügbare Drucker (PowerShell):</h2>
    <pre>
<?php
$psCommand = 'powershell -Command "Get-Printer | Select-Object Name, PortName, DriverName | Format-Table -AutoSize"';
exec($psCommand . ' 2>&1', $output, $returnVar);
echo implode("\n", $output);
?>
    </pre>
    
    <h2>Verfügbare COM-Ports:</h2>
    <pre>
<?php
$psCommand = 'powershell -Command "Get-WmiObject Win32_SerialPort | Select-Object DeviceID, Description | Format-Table -AutoSize"';
exec($psCommand . ' 2>&1', $output, $returnVar);
echo implode("\n", $output);
?>
    </pre>
    
    <h2>Test: Port USB001 öffnen:</h2>
    <pre>
<?php
$portVariants = ['USB001:', 'USB001', '\\.\USB001'];
foreach ($portVariants as $port) {
    echo "Teste: " . $port . " ... ";
    $handle = @fopen($port, 'wb');
    if ($handle !== false) {
        echo "<span class='success'>✓ ÖFFENBAR</span>\n";
        fclose($handle);
    } else {
        $error = error_get_last();
        echo "<span class='error'>✗ FEHLER: " . ($error['message'] ?? 'Unbekannt') . "</span>\n";
    }
}
?>
    </pre>
    
    <h2>Test: Datei an USB001 senden:</h2>
    <pre>
<?php
$testFile = __DIR__ . '/temp/test_' . time() . '.txt';
$testDir = dirname($testFile);
if (!is_dir($testDir)) {
    mkdir($testDir, 0777, true);
}
file_put_contents($testFile, "Test-Druck\n\f");

$command = 'copy /B "' . $testFile . '" "USB001:"';
echo "Befehl: " . $command . "\n";
exec($command . ' 2>&1', $output, $returnVar);
echo "Return Code: " . $returnVar . "\n";
echo "Output: " . implode("\n", $output) . "\n";

if (file_exists($testFile)) {
    unlink($testFile);
}
?>
    </pre>
    
    <p><a href="registrierung.php">Zurück zur Registrierung</a></p>
</body>
</html>

