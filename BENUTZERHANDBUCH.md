# Benutzerhandbuch - Binokel Turnier-Management-System

## Inhaltsverzeichnis

1. [Einführung](#einführung)
2. [Systemübersicht](#systemübersicht)
3. [Hauptfunktionen](#hauptfunktionen)
   - [1. Turnier erfassen](#1-turnier-erfassen)
   - [2. Turnier starten](#2-turnier-starten)
   - [3. Anmeldung](#3-anmeldung)
   - [4. Registrierung](#4-registrierung)
   - [5. Spielrunde](#5-spielrunde)
   - [6. Auswertung](#6-auswertung)
4. [Arbeitsablauf](#arbeitsablauf)
5. [Häufige Fragen](#häufige-fragen)

---

## Einführung

Das Binokel Turnier-Management-System ist eine webbasierte Anwendung zur Verwaltung von Binokel-Turnieren. Es ermöglicht die komplette Organisation von der Turnierplanung über die Anmeldung der Teilnehmer bis hin zur Erfassung der Ergebnisse und der finalen Auswertung.

### Systemanforderungen

- Moderne Webbrowser (Chrome, Firefox, Edge, Safari)
- Internetverbindung
- Zugriffsrechte auf das System

---

## Systemübersicht

Das System besteht aus sechs Hauptmodulen, die über ein Menü im linken Bereich erreichbar sind:

1. **Turnier erfassen** - Erstellung und Verwaltung von Turnieren
2. **Turnier starten** - Berechnung der Turnierpaarungen und Startnummern
3. **Anmeldung** - Verwaltung der Online-Anmeldungen
4. **Registrierung** - Registrierung der Teilnehmer am Turniertag
5. **Spielrunde** - Erfassung der Punkte für jede Runde
6. **Auswertung** - Gesamtauswertung und Rangliste

---

## Hauptfunktionen

### 1. Turnier erfassen

**Zweck:** Erstellung und Verwaltung von Turnierdaten

#### Funktionen:

- **Neues Turnier erstellen**
  - Klicken Sie auf "Neues Turnier"
  - Füllen Sie alle Pflichtfelder aus:
    - **Datum:** Turnierdatum (Format: JJJJ-MM-TT)
    - **Titel:** Name des Turniers (z.B. "2. Binokelturnier Heroldstatt")
    - **Veranstalter:** Name des Veranstalters (z.B. "AKS Heroldstatt")
    - **Ort:** Veranstaltungsort (z.B. "Berghalle, 72535 Heroldstatt")
    - **Einlasszeit:** (optional) z.B. "17 Uhr"
    - **Startzeit:** (optional) z.B. "18 Uhr"
    - **Google Maps Link:** (optional) Link zur Veranstaltungsstätte
    - **Anzahl der Spieler:** Gesamtzahl der erwarteten Teilnehmer
    - **Anzahl der Runden:** Anzahl der Spielrunden (Standard: 3)
    - **Anzahl Spieler pro Runde:** Spieler pro Tisch (Standard: 3)
  - Wichtig: Die Anzahl der Spieler muss durch die Anzahl Spieler pro Runde teilbar sein!
  - Klicken Sie auf "Turnier speichern"

- **Turnier bearbeiten**
  - In der Übersichtstabelle auf das Bearbeiten-Symbol (grünes Stift-Symbol) klicken
  - Daten ändern und "Turnier aktualisieren" klicken

- **Turnier aktivieren**
  - In der Übersichtstabelle auf das Aktivieren-Symbol (blaues Häkchen-Symbol) klicken
  - Bestätigen Sie die Aktivierung
  - Hinweis: Beim Aktivieren werden alle anderen Turniere automatisch deaktiviert
  - Alle An- und Eingaben in nachfolgenden Modulen beziehen sich auf das aktivierte Turnier

- **Turnier löschen**
  - In der Übersichtstabelle auf das Löschen-Symbol (rotes Mülleimer-Symbol) klicken
  - Bestätigen Sie die Löschung

#### Übersichtstabelle:

Die Tabelle zeigt alle erfassten Turniere mit folgenden Informationen:
- ID, Datum, Titel, Veranstalter, Ort
- Einlass- und Startzeit
- Google Maps Link
- Anzahl Spieler, Runden, Spieler pro Tisch
- Status (Aktiv/Inaktiv)

---

### 2. Turnier starten

**Zweck:** Berechnung der Turnierpaarungen und Zuordnung der Startnummern

#### Voraussetzungen:

- Ein aktives Turnier muss vorhanden sein
- Die Anzahl der Spieler muss festgelegt sein
- Die Anzahl der Spieler muss durch die Anzahl Spieler pro Runde teilbar sein

#### Funktionen:

- **Turnierplan berechnen**
  - Das System zeigt die Turnierdaten an
  - Klicken Sie auf "Turnierplan berechnen"
  - Das System erstellt automatisch:
    - Zufällige Zuordnung der Spieler zu den Runden
    - Gruppierung der Spieler pro Tisch
    - Vermeidung von Wiederholungen (Spieler sollen möglichst nicht mehrmals zusammen spielen)

- **Startnummern zuweisen**
  - Nach der Berechnung werden den registrierten Teilnehmern automatisch Startnummern zugewiesen
  - Die Startnummern werden in der Reihenfolge der Registrierung vergeben

- **Laufzettel drucken**
  - Nach dem Start können Laufzettel für die Teilnehmer gedruckt werden
  - Die Laufzettel enthalten:
    - Turnierinformationen
    - Startnummer
    - Name des Teilnehmers
    - Raum, damit der Teilnehmer sich seine Ergebnisse pro Runde notieren kann

#### Wichtige Hinweise:

- Ein Turnier sollte nur einmal gestartet werden
- Nach dem Start stehen die Paarungen fest und sollten nicht mehr geändert werden
- Stellen Sie sicher, dass alle Teilnehmer registriert sind, bevor Sie das Turnier starten
- Ist die erste Runde am Turnierabend gestartet, darf auf keinesfalls neu berechnet werden

---

### 3. Anmeldung

**Zweck:** Verwaltung der Online-Anmeldungen der Teilnehmer

#### Funktionen:

- **Anmeldungen anzeigen**
  - Alle Anmeldungen werden in einer Tabelle angezeigt
  - Spalten: ID, Name, E-Mail, Mobilnummer, Anmeldedatum, E-Mail-Status

- **Turnierauswahl**
  - Wählen Sie das Turnier aus, für das Anmeldungen registriert und angezeigt werden sollen
  - Die Anmeldungen werden automatisch gefiltert

- **Anmeldungen bearbeiten**
  - Klicken Sie auf eine Anmeldung, um Details zu sehen
  - Name, E-Mail und Mobilnummer können bearbeitet werden

- **Anmeldungen löschen**
  - Anmeldungen können gelöscht werden, wenn nötig

- **CSV-Export**
  - Klicken Sie auf "Als Excel exportieren"
  - Die Anmeldungen werden als CSV-Datei heruntergeladen
  - Enthält: ID, Name, E-Mail, Mobilnummer, Anmeldedatum, E-Mail-Status

- **E-Mail-Status**
  - **E-Mail gesendet:** Zeigt an, ob eine Bestätigungsmail gesendet wurde
  - **E-Mail gelesen:** Zeigt an, ob die E-Mail geöffnet wurde (via Tracking-Pixel)

#### E-Mail-Funktionen:

- Automatische Versendung von Bestätigungsmails bei Anmeldung
- E-Mail enthält:
  - Anmeldebestätigung
  - Registriernummer
  - Turnierinformationen (Datum, Ort, Zeit)
  - Google Maps Link (falls vorhanden)
  - Anhang: Spielregeln (PDF)

---

### 4. Registrierung

**Zweck:** Registrierung der Teilnehmer am Turniertag und Zuordnung von Startnummern

#### Funktionen:

- **Registrierung über Registriernummer**
  - Geben Sie die Registriernummer ein (aus der Anmeldebestätigung)
  - Klicken Sie auf "Daten laden"
  - Die Anmeldedaten werden automatisch geladen
  - Geben Sie die gewünschte Startnummer ein
  - Klicken Sie auf "Registrieren"
  - Der Teilnehmer wird dem Turnier zugeordnet

- **Manuelle Registrierung**
  - Wenn keine Anmeldung vorhanden ist, können Teilnehmer manuell registriert werden
  - Geben Sie Name, E-Mail (optional) und Mobilnummer (optional) ein
  - Wählen Sie eine freie Startnummer
  - Klicken Sie auf "Registrieren"

- **Laufzettel anzeigen**
  - Nach der Registrierung kann der Laufzettel angezeigt werden
  - Der Laufzettel zeigt:
    - Turnierinformationen
    - Startnummer
    - Name
    - Raum, damit der Teilnehmer sich seine Ergebnisse pro Runde notieren kann

- **Registrierte Teilnehmer anzeigen**
  - Liste aller registrierten Teilnehmer
  - Spalten: Startnummer, Name, E-Mail, Mobilnummer, Registriert am
  - Sortierung nach Startnummer oder Registrierungszeitpunkt

#### Wichtige Hinweise:

- Jede Startnummer kann nur einmal vergeben werden
- Teilnehmer können auch ohne vorherige Online-Anmeldung registriert werden
- Wenn ein Teilnehmer einem anderen Turnier zugeordnet ist, wird eine Warnung angezeigt. Der Teilnehmer kann übernommen werden.

---

### 5. Spielrunde

**Zweck:** Erfassung der Punkte für jede Spielrunde

#### Funktionen:

- **Runde auswählen**
  - Wählen Sie die Runde aus dem Dropdown-Menü aus
  - Die Ansicht wechselt automatisch zur gewählten Runde

- **Punkteeingabe**
  - Geben Sie die **Spielernummer** (Startnummer) ein
  - Der Name wird automatisch geladen
  - Geben Sie die **Gesamtpunktzahl** für diese Runde ein
  - Klicken Sie auf "Speichern"
  - Die Punkte werden sofort in der Tabelle angezeigt

- **Ergebnis-Tabelle**
  - Zeigt alle Teilnehmer mit ihren Punkten für die aktuelle Runde
  - Spalten:
    - **Startnummer**
    - **Name**
    - **Punktzahl** (Gesamtpunkte der Runde)
    - **Platzierung** (automatisch berechnet)
  - Sortierung möglich nach:
    - **Punktzahl** (höchste zuerst)
    - **Startnummer** (aufsteigend)
    - **Reihenfolge der Eingabe** (neueste zuerst)

- **Punkte bearbeiten**
  - Geben Sie erneut die Spielernummer ein
  - Die bereits eingegebenen Punkte werden angezeigt (rot hinterlegt)
  - Ändern Sie die Punkte und speichern Sie erneut

- **Status-Anzeige**
  - Anzahl der Spieler im Turnier
  - Anzahl der bereits eingetragenen Ergebnisse
  - Fortschrittsanzeige

- **Druckfunktion**
  - Klicken Sie auf "Drucken", um die Ergebnis-Tabelle zu drucken
  - Die Tabelle wird in einem neuen Fenster geöffnet und kann gedruckt werden

#### Wichtige Hinweise:

- Punkte können jederzeit geändert werden
- Die Platzierung wird automatisch nach Punktzahl berechnet
- Bei gleicher Punktzahl erhalten Spieler die gleiche Platzierung
- Die Tabelle aktualisiert sich automatisch nach jeder Eingabe

---

### 6. Auswertung

**Zweck:** Gesamtauswertung des Turniers mit Rangliste

#### Funktionen:

- **Gesamtübersicht**
  - Zeigt alle Teilnehmer mit ihren Gesamtpunkten
  - Spalten:
    - **Startnummer**
    - **Name**
    - **Runde 1, Runde 2, Runde 3** (Punkte pro Runde)
    - **Gesamtpunkte** (Summe aller Runden)
    - **Platzierung** (automatisch berechnet)

- **Sortierung**
  - Nach **Punktzahl** (höchste zuerst) - Standard
  - Nach **Startnummer** (aufsteigend)

- **Runde aktivieren/deaktivieren**
  - Runden können für die Auswertung aktiviert oder deaktiviert werden
  - Nur aktive Runden werden in die Gesamtpunktzahl einberechnet
  - Nützlich, wenn eine Runde noch nicht abgeschlossen ist

- **Druckfunktion**
  - Klicken Sie auf "Drucken", um die Rangliste zu drucken
  - Die Rangliste wird in einem neuen Fenster geöffnet

- **Status-Anzeige**
  - Anzahl der Spieler mit vollständigen Ergebnissen
  - Anzahl der Spieler gesamt

#### Platzierungslogik:

- Die Platzierung wird nach Gesamtpunkten berechnet
- Höhere Punktzahl = bessere Platzierung
- Bei gleicher Punktzahl erhalten Spieler die gleiche Platzierung
- Die nächste Platzierung wird entsprechend übersprungen (z.B. zwei Spieler auf Platz 1, nächster auf Platz 3)

#### Wichtige Hinweise:

- Die Auswertung wird automatisch aktualisiert, wenn neue Punkte eingegeben werden
- Die Gesamtpunktzahl ist die Summe aller aktiven Runden

#### Infoseite für Teilnehmer
- Unter <Turnierhomepage>/info wird eine HTML-Seite generiert, auf welcher die wichtigsten Infos an die Teilnehmer weiter gegeben werden können. 
- Die Teilnehmer starten vor Beginn des Turniers diese Seite und erhalten automatisch die zum Turnierverlauf passende Information angezeigt, die von der Turnierorganisation gesteuert wird.
- Im Modul Turnier starten kann die Anzeige der Tischzuordnung aktiviert oder deaktiviert werden
- Im Modul Auswertung kann die Rangliste pro Runde und Gesamtergebnis aktiviert oder deaktiviert werden

- **Angezeigte Informationen sind**
  - Tischzuordnung pro Spieler für die jeweilige Runde
  - Spielernummern pro Tisch für die jeweilige Runde
  - Ergebnisliste pro Runde
  - Gesamtergebnisliste

---

## Arbeitsablauf

### Empfohlener Ablauf für ein Turnier:

1. **Vorbereitung (vor dem Turnier)**
   - Turnier erfassen (Modul 1)
   - Turnier aktivieren
   - Online-Anmeldungen verwalten (Modul 3)

2. **Am Turniertag - Vorbereitung**
   - Registrierung der Teilnehmer (Modul 4)
   - Laufzettel für Teilnehmer drucken
   - Turnier starten (Modul 2) - Paarungen berechnen

3. **Während des Turniers**
   - Für jede Runde:
     - Punkteeingabe (Modul 5)
     - Ergebnisse kontrollieren
     - Bei Bedarf Ergebnisse drucken

4. **Nach dem Turnier**
   - Auswertung anzeigen (Modul 6)
   - Rangliste drucken
   - Ergebnisse exportieren (falls nötig)


### **Neben des digitalen Turnier-Managements wird außerdem noch benötigt**
- Markierung der Tische mit eindeutigen Nummern
- Ein Formblatt für die Dokumentation der Spielergebnisse pro Runde und Tisch
- Textilaufkleber mit den Spielernummern (und Namen), den sich die Teilnehmer auf die Brust kleben
- Plakate mit QR-Codes für: 
  - die Anmeldung
  - die Infoseite
- Preise für die Teilnehmer

---

## Häufige Fragen

### Allgemein

**F: Kann ich mehrere Turniere gleichzeitig verwalten?**
A: Ja, Sie können mehrere Turniere erfassen. Es kann jedoch nur ein Turnier aktiv sein. Beim Aktivieren eines Turniers werden alle anderen automatisch deaktiviert.

**F: Was passiert, wenn ich einen Fehler gemacht habe?**
A: Die meisten Daten können bearbeitet werden:
- Turnierdaten können geändert werden (Modul 1)
- Punkte können korrigiert werden (Modul 5)
- Anmeldungen können bearbeitet werden (Modul 3)

**F: Kann ich ein Turnier löschen?**
A: Ja, über das Löschen-Symbol in der Turnier-Übersicht. Achtung: Alle zugehörigen Daten werden gelöscht!

### Turnier erfassen

**F: Warum muss die Anzahl der Spieler durch die Anzahl Spieler pro Runde teilbar sein?**
A: Damit alle Spieler gleichmäßig auf die Tische verteilt werden können. Beispiel: 16 Spieler bei 4 Spielern pro Tisch = 4 Tische.

**F: Kann ich die Anzahl der Runden nachträglich ändern?**
A: Ja, solange das Turnier noch nicht gestartet wurde. Nach dem Start sollten die Runden nicht mehr geändert werden.

### Registrierung

**F: Was ist der Unterschied zwischen Anmeldung und Registrierung?**
A: 
- **Anmeldung:** Online-Anmeldung vor dem Turnier (Modul 3)
- **Registrierung:** Anmeldung am Turniertag mit Startnummernzuweisung (Modul 4)

**F: Kann ein Teilnehmer ohne Online-Anmeldung teilnehmen?**
A: Ja, über die manuelle Registrierung in Modul 4.

**F: Was passiert, wenn eine Startnummer bereits vergeben ist?**
A: Das System zeigt eine Fehlermeldung. Wählen Sie eine andere Startnummer.

### Spielrunde

**F: Kann ich Punkte nachträglich ändern?**
A: Ja, geben Sie einfach die Spielernummer erneut ein und ändern Sie die Punkte.

**F: Wie wird die Platzierung berechnet?**
A: Die Platzierung wird automatisch nach Punktzahl berechnet. Höhere Punktzahl = bessere Platzierung.

**F: Was bedeutet "Reihenfolge der Eingabe" bei der Sortierung?**
A: Die Tabelle wird nach dem Zeitpunkt der Punkteeingabe sortiert. Nützlich, um zu sehen, wer zuletzt Punkte eingegeben hat.

### Auswertung

**F: Was bedeutet "Runde aktivieren/deaktivieren"?**
A: Wenn eine Runde aktiviert ist, dann werden die Tischzuordnungen den Teilnehmern auf der Infoseite angezeigt.

**F: Wie wird die Gesamtpunktzahl berechnet?**
A: Summe aller Punkte aus den aktiven Runden.

**F: Was passiert bei gleicher Punktzahl?**
A: Spieler mit gleicher Punktzahl erhalten die gleiche Platzierung. Die nächste Platzierung wird übersprungen (z.B. zwei Spieler auf Platz 1, nächster auf Platz 3).

---

## Technische Hinweise

### Browser-Kompatibilität

Das System funktioniert mit allen modernen Browsern:
- Google Chrome (empfohlen)
- Mozilla Firefox
- Microsoft Edge
- Safari

### Druckfunktionen

- Verwenden Sie die Druckfunktionen im System für optimale Formatierung
- Die Tabellen werden automatisch für den Druck formatiert
- Bei Problemen: Browser-Druckvorschau verwenden

### Datenexport

- CSV-Export für Anmeldungen verfügbar
- Daten können in Excel oder anderen Tabellenkalkulationsprogrammen geöffnet werden

---

## Support

Bei Fragen oder Problemen wenden Sie sich bitte an den Systemadministrator.

**Version:** 1.0  
**Letzte Aktualisierung:** 2025

---

*Ende des Benutzerhandbuchs*

