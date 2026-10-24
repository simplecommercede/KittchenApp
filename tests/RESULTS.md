# Validierung

Am 23.10.2026 lokal ausgeführt:

- `npm run build`: TypeScript-Prüfung und Vite-Produktionsbuild erfolgreich.
- PHP-Syntaxprüfung für API und Migration erfolgreich.
- Docker-Image gebaut; MariaDB-Healthcheck erfolgreich; App auf Port 8087 gestartet.
- `python3 tests/integration.py`: 8 Integrationstests erfolgreich gegen PHP und MariaDB.
- Browser-Smoke-Test: Admin-Anmeldung, Tisch 07 wählen, Hamburger mit zwei Sonderwünschen senden, neuen Bon in Küchenansicht sehen und auf „In Arbeit“ setzen erfolgreich.
- Screenshot: `output/kitchen-running.png`.

Die HTTP-Tests legen zusätzliche lokale Beispieldaten an. Keine produktiven Systeme wurden verändert. Kein automatisierter Browser-Regressionstest enthalten.

## Bestellaufnahme und Kassieren, 23.10.2026

- Docker-Produktionsbuild mit TypeScript-Prüfung erfolgreich; PHP-Syntaxprüfung für API und Migration erfolgreich.
- Migration auf bestehender lokaler Datenbank erfolgreich.
- `python3 tests/integration.py`: 11 Tests erfolgreich. Der neue Test prüft die sichtbare Bestell-ID als Rechnungsreferenz, gespeicherte Einzelpreise, Storno samt Grund und Archiv-Event, blockierte weitere Bearbeitung, den veränderten Zahlbetrag und die Ablehnung eines veralteten Betrags.
- Browserprüfung auf `http://127.0.0.1:8087/`: Speisekarte erscheint nur unter „Bestellung aufnehmen“. Eine mobile Testbestellung erhielt `#000032`, erschien mit 3,50 € unter „Kassieren“ und wurde dort mit Grund storniert; danach betrug die offene Rechnung 0,00 €. Auf Smartphonebreite gab es keinen horizontalen Überlauf; die Browserkonsole zeigte keine Warnungen oder Fehler. Nach der letzten Änderung blieb das Bon-Board in der Kassenansicht verborgen.
- Die Browserprüfung lief über den vorhandenen In-App-Browser, weil auf dem Host weder Playwright noch Node.js verfügbar waren. Es gibt weiterhin keinen automatisierten Browser-Regressionstest.

## Bestellkorrekturen, 23.10.2026

- Docker-Produktionsbuild einschließlich TypeScript-Prüfung erfolgreich; PHP-Syntaxprüfung erfolgreich; Migration auf der vorhandenen lokalen Datenbank erfolgreich.
- `python3 tests/integration.py`: 12 Tests erfolgreich. Der neue Test deckt Notizänderung, Ersatzposition, Nachtrag, einzelne Entfernung, unveränderte Bestellnummer, neue Stationsbons, korrekten Zahlbetrag, Änderungsverlauf, Berechtigungen und wiederholte Kennungen ab.
- Mobile Browserprüfung auf `http://127.0.0.1:8087/`: Kassenansicht zeigt je Position „Korrigieren“ und „Entfernen“ sowie pro Bestellung Nachtrag und Notizänderung. Das Korrekturformular zeigt Produkt, Menge, Hinweis und Pflichtgrund. Im Browser wurde keine bestehende Gastbestellung verändert.

## Korrekturen vor dem Senden, 23.10.2026

- Docker-Produktionsbuild mit TypeScript-Prüfung erfolgreich.
- Mobile Browserprüfung: Produkt und Menge einer Entwurfsposition geändert, Sonderwunsch angepasst und Erhalt nach Neuladen geprüft. Die Position wurde anschließend entfernt; ohne Position bleibt „Bestellung senden“ gesperrt. Keine Bestellung wurde für diesen Test gesendet. Die Browserkonsole zeigte keine Fehler oder Warnungen.

## Mitarbeiterverwaltung, 23.10.2026

- Docker-Produktionsbuild mit TypeScript-Prüfung und PHP-Syntaxprüfung erfolgreich.
- `python3 tests/integration.py`: 13 Tests erfolgreich. Geprüft wurden Mehrfachrollen, Umbenennung, sofort wirksame Rollenänderung, Entfernen und Wiederaktivierung, gesperrte Anmeldung/Sitzung, erhaltene Zuordnung auf Bons sowie Schutz des eigenen und letzten Admin-Zugangs.
- Mobile Browserprüfung: Bearbeiten, getrennte Rollenauswahl, Entfernen und Bereich für entfernte Mitarbeiter sichtbar; mehrere Rollen konnten im Formular gleichzeitig markiert werden. Die Probeänderung wurde verworfen.

## Späte Stornos und Zahlungsrücknahme, 23.10.2026

- Docker-Produktionsbuild mit TypeScript-Prüfung, PHP-Syntaxprüfung und Migration auf der lokalen Datenbank erfolgreich.
- `python3 tests/integration.py`: 15 Tests erfolgreich. Neue Fälle prüfen Stornohinweise für bereits gestartete Positionen und Bons, Stationsrechte, Sperre bis zur Quittierung, einmalige Zahlungsrücknahme mit Pflichtgrund und Verlauf, erneute Zahlungsbestätigung sowie die Sperre bei einem neueren Tischbesuch.
- Im In-App-Browser erschien die offene Stornoliste in der mobilen Küchenansicht. Nach „Storno gesehen“ sank der Zähler von drei auf zwei, und der quittierte Hinweis verschwand. Im Service konnte ein bezahlter, bereits deaktivierter Testtisch gewählt und das Rücknahmeformular geöffnet werden; ohne Grund blieb „Rücknahme bestätigen“ gesperrt. Das Formular wurde ohne Rücknahme geschlossen. Die Browserkonsole zeigte keine Fehler oder Warnungen. Keine echte Zahlung oder Erstattung wurde ausgelöst.
- Die Browserprüfung erfolgte im vorhandenen In-App-Browser; eine automatisierte Browser-Suite und Tests auf einem physischen Tablet fehlen weiterhin.

## Kleine Zuverlässigkeitsfixes, 23.10.2026

- Docker-Produktionsbuild mit TypeScript-Prüfung erfolgreich.
- `python3 tests/integration.py`: 18 Tests erfolgreich. Vor den Fixes schlugen die drei neuen Tests fehl: gekürztes Passwort wurde akzeptiert, DB-Session-Zeitzone war `SYSTEM`, und bei nicht erreichbarer Datenbank endete die API mit einem PHP-Fatal-Error statt JSON.
- Neu: `test_15` (Leerzeichen im Passwort bleiben erhalten), `test_16` (Bonzeit passt zu `server_time`, DB-Session und PHP laufen in UTC), `test_17` (DB-Ausfall liefert JSON-Fehler 503). `test_16` und `test_17` laufen per `docker compose exec` im App-Container und werden ohne lokalen Docker-Stack übersprungen.
- `test_01` nutzt jetzt einen zufälligen unbekannten Benutzer; der feste Name `missing` wurde bei wiederholten Läufen von der Login-Drosselung gesperrt (429).
- Browserprüfung im In-App-Browser: als `robin` angemeldet, PHP-Sessions im Container gelöscht. Innerhalb von drei Sekunden erschien die Anmeldung mit „Sitzung beendet. Bitte anmelden.“; die erneute Anmeldung funktionierte ohne Neuladen. Die einzige Konsolenmeldung war der erwartete 401-Request.

## Speisekarte und bereinigte Datenbank, 23.10.2026

- Lokale Datenbank (Volume `kittchenapp_kitchen-db`) gelöscht und neu migriert. Danach: 99 Produkte aus der Speisekarte in 14 Kategorien, die 4 Demo-Konten, Tische 1–12, keine Bestellungen, Bons oder Testdaten.
- `sh tests/run.sh`: 18 Tests erfolgreich im getrennten Stack `kittchenapp-test` (Port 8088, frisches Volume, `MENU_SEED=test`). Der Stack wurde danach samt Volume entfernt, die lokale App blieb unverändert.
- Browserprüfung als `mia`: Tisch 03 gewählt, „Bestellung aufnehmen“ zeigt alle 14 Kategorien in der Reihenfolge der Karte mit korrekten Preisen. Es wurde keine Bestellung gesendet.

## Getränke/Speisen, ml-Angaben und Strohhalm, 23.10.2026

- Lokale Datenbank erneut gelöscht und migriert (vorher geprüft: 0 Bestellungen, nur Seed-Daten). 99 Produkte, keine Mengenangabe mehr in l oder cl.
- `sh tests/run.sh`: 18 Tests erfolgreich im getrennten Teststack.
- Browserprüfung als `mia`: „Getränke“ und „Speisen“ ohne Kategoriefilter, alphabetisch mit Größen untereinander (z. B. Coca-Cola 300/500 ml vor Coca-Cola Zero 300/500 ml). „Mit Strohhalm“ erscheint nur bei Getränken, wird beim Antippen in den Sonderwunsch übernommen und als aktiv markiert. Probeposition danach entfernt; es wurde nichts gesendet.

## Größenauswahl und Strohhalm nur bei Kindergetränken, 23.10.2026

- Migration auf der bestehenden lokalen Datenbank ergänzte `menu.straw` und markierte 39 Getränke (Softgetränke, Säfte, Saftschorlen, Malzbier). Die Seed-Datei liefert für frische Installationen dieselben 39.
- `sh tests/run.sh`: 18 Tests erfolgreich im getrennten Teststack.
- Browserprüfung als `mia`: Coca-Cola erscheint als eine Karte mit „300 · 500 ml“ und „ab 3,40 €“; nach dem Antippen „300 ml“ gewählt. König Pilsener vom Fass ebenso mit 500 ml. Im Entwurf hat nur die Cola „Mit Strohhalm“, das Pils nicht. Beide Probepositionen entfernt; es wurde nichts gesendet.

## Malzbier ohne Strohhalm, 23.10.2026

- Strohhalm-Regel in Seed und Migration ohne Malzbier; lokale Datenbank einmalig per SQL korrigiert. Danach 38 Getränke mit Strohhalm-Option, Malzbier ohne.
- PHP-Syntaxprüfung erfolgreich; `sh tests/run.sh`: 18 Tests erfolgreich.

## Admin: Getränke und Speisen getrennt, 23.10.2026

- `sh tests/run.sh`: 19 Tests erfolgreich. Neu `test_18`: Getränk ohne ml und mit Menge im Namen wird abgelehnt (400); mit `volume_ml` 250 entsteht „… 250 ml“ an der Station `bar` mit Strohhalm-Option; bei Speisen wird `straw` ignoriert.
- Browserprüfung als `admin`: Speisekarte mit „Getränke“ und „Speisen“, Liste alphabetisch mit Kategorie und Strohhalm-Hinweis. Das Getränkeformular hat das Pflichtfeld „Menge in ml“ und „Strohhalm-Option (Kindergetränk)“, das Speisenformular beides nicht. In der lokalen Datenbank wurde nichts angelegt.

## Admin: Produkte bearbeiten, 23.10.2026

- `sh tests/run.sh`: 19 Tests erfolgreich. `test_18` prüft zusätzlich: Bearbeiten eines Getränks ohne ml wird abgelehnt (400); mit neuer Menge, neuem Preis und ohne Strohhalm entsteht „… 400 ml“ mit 4,20 € und `straw=0`; unbekannte ID ergibt 404.
- Browserprüfung als `admin`: „Bearbeiten“ bei „Apfelsaft 300 ml“ öffnet das Formular mit „Apfelsaft“, 300 ml, 3,60 €, Kategorie „Säfte“ und gesetztem Strohhalm. Unverändert gespeichert, Hinweis „Produkt gespeichert …“ erschien, Datenbankwerte danach unverändert.

## Admin: ein Eintrag pro Getränk mit Portionsgrößen, 23.10.2026

- `sh tests/run.sh`: 20 Tests erfolgreich. Neu `test_19`: `admin_drink` legt ein Getränk mit 300 und 500 ml samt eigenen Preisen an; leere oder doppelte Größen und Menge im Namen ergeben 400, ein zweites gleichnamiges Getränk 409, fehlende vorhandene Größe beim Ändern 400, Tresen-Rolle 403. Umbenennen, Preisänderung, Deaktivieren einer Größe und neue 1000-ml-Größe werden gemeinsam gespeichert; IDs der vorhandenen Größen bleiben erhalten.
- Browserprüfung als `admin`: Apfelschorle erscheint einmal mit „300 ml · 3,50 € | 500 ml · 5,20 €“; das Bearbeitungsformular zeigt beide Größen mit eigenem Preis und „Verfügbar“. Auf 375 px Breite kein horizontaler Überlauf. Nichts gespeichert, die Karte blieb unverändert.

## Login-Limit pro Adresse, Anfragegröße, aktive Bons, 23.10.2026

- `sh tests/run.sh`: 23 Tests erfolgreich. Neu: `test_20` (über 64 KB → 413, zu tief verschachteltes JSON → 400, gültige Bestellung weiter 201), `test_21` (101 Bestellungen mit je zwei Bons; über 200 aktive Bons, die neueste Bestellung ist bei Service und Küche sichtbar), `test_22` (Fehlversuche mit wechselnden Namen führen zur Sperre der Adresse, auch ein gültiger Login bekommt dann 429; Einträge älter als ein Tag werden gelöscht; danach Aufräumen).
- Gegenprobe: Mit dem vorherigen API-Code schlagen `test_20`, `test_21` und `test_22` fehl.
- Migration auf der lokalen Datenbank ergänzte `login_attempts.updated_at`.

## Service-Vertretung für den unbesetzten Tresen, 23.10.2026

- `sh tests/run.sh`: 24 Tests erfolgreich. Neu `test_23`: Hat der Tresen seine Ansicht offen, bekommt Service 403 beim Starten eines Getränkebons und `bar_cover: false`. Nach 1 Minute ohne Tresen-Abruf: `bar_cover: true`, Service setzt den Bon auf In Arbeit, Abholbereit und Abgeholt; der Bon bleibt `bar`, der Verlauf zeigt „Mia“. Küchenbons bleiben gesperrt (403). Ein später Storno eines vertretenen Getränks erzeugt einen Hinweis, den Service quittieren kann. Ist der Tresen zurück, gibt es wieder 403 für Service, der Tresen selbst darf weiterarbeiten.
- Migration auf der lokalen Datenbank ergänzte `users.last_seen` und `users.last_view`.
- Browserprüfung als `mia` ohne Probebestellung: Hinweis „Tresen ist gerade nicht besetzt …“ erscheint. Nach simulierter Tresen-Anwesenheit (per SQL) verschwand er innerhalb von 3 Sekunden; die Simulation wurde danach zurückgesetzt.

## Erreichbarkeit im lokalen WLAN, 23.10.2026

- `compose.yaml` bindet über `APP_BIND` (Standard `127.0.0.1`); lokal mit `.env` `APP_BIND=0.0.0.0` gestartet, Port danach `0.0.0.0:8087`. `curl http://192.168.178.30:8087/` lieferte 200.
- Browserprüfung über `http://192.168.178.30:8087/`: Seite ist kein sicherer Kontext (`isSecureContext` false, `crypto.randomUUID` nicht vorhanden). Als `mia` angemeldet, Tisch 02 gewählt, Astra in den Entwurf gelegt: Die Entwurfskennung ist eine gültige UUID v4, keine Konsolenfehler. Probeposition danach entfernt, nichts gesendet.
- `sh tests/run.sh`: 24 Tests erfolgreich; der Teststack lief nur auf 127.0.0.1 und wurde danach entfernt.
- Apache-Log: Alle Zugriffe, auch über die WLAN-Adresse, erscheinen als 192.168.65.1 (Docker Desktop).
- Nicht geprüft: Zugriff von einem echten iPad oder Smartphone.

## Timer bei Start anhalten, getrennte Zahlung pro Bestellung, 23.10.2026

- `sh tests/run.sh`: 25 Tests erfolgreich. Neu `test_24`: Einzelzahlung vor Abholung 409, falscher Betrag 409; danach 3,50 € bezahlt, Besuch bleibt offen, offener Betrag 7,00 €; bezahlte Bestellung lässt sich weder erneut bezahlen noch ändern oder stornieren (409). Mit der zweiten Bestellung schließt der Besuch (Summe 10,50 €, zwei `order_paid`-Ereignisse). Rücknahme öffnet beide Bestellungen, danach `table_paid` über 10,50 €.
- Migration auf der lokalen Datenbank ergänzte `orders.paid_at`, `orders.paid_by` und `payment_events.order_id`.
- Browserprüfung im kurzzeitig gestarteten Teststack (Port 8088, Bestellungen per Skript, lokale Datenbank unberührt): Neuer Bon zählt (00:14 → 00:18), Bon in Arbeit bleibt auf „00:00 bis Start“. Kassieren an Tisch 03: Einzelknöpfe der abgeholten Getränkebestellungen aktiv, offene Speisen und „Alles“ gesperrt. Nach Einzelzahlung von #000001 (per API, da der Bestätigungsdialog nicht automatisierbar ist) „Bezahlt“-Markierung ohne Knöpfe, offener Betrag 39,80 €, Hauptknopf „Restbetrag“. Teststack danach entfernt.

## Hinweise mit Ton, Vibration und Benachrichtigung, 23.10.2026

- Reine Frontend-Änderung; Docker-Build mit TypeScript-Prüfung erfolgreich, `sh tests/run.sh`: 25 Tests erfolgreich.
- Browserprüfung im kurzzeitig gestarteten Teststack (Port 8088, lokale Datenbank unberührt): Als `robin` zeigt das 🔔-Feld Ton und Vibration aktiv, Gerätebenachrichtigung aus. Nach einer per Skript angelegten Bestellung mit Speise und Getränk erschien in der Küche „Neue Bestellung · Küche · Tisch 05 · 2× Hamburger mit Bacon“ (ohne das Getränk), die Vibrationsfunktion wurde einmal mit [200, 100, 200] aufgerufen (im Browser abgefangen). Als `mia` kam beim Anmelden kein Hinweis für schon vorhandene Bons; nachdem die Küche den Bon per Skript auf abholbereit setzte, erschien „Abholbereit · Küche · Tisch 05 · Bestellung #000001“. Teststack danach entfernt.
- Nicht geprüft: hörbarer Ton, echte Vibration und Gerätebenachrichtigung auf echten Geräten.

## Logo als Favicon und App-Icon, 23.10.2026

- Icons aus `output/brand/stimbeck-logo.png` mit ImageMagick in einem Docker-Container erzeugt (16/32/48 px ICO, 32 px PNG, 180/192/512 px PNG auf Weiß).
- `Dockerfile` kopiert jetzt `public/`; nach dem Neubau lieferten alle sechs Dateien HTTP 200 mit passendem Typ. Im Browser bindet die Seite Favicon, Apple-Touch-Icon und Manifest ein, beide Manifest-Icons laden (192 und 512 px).
- `sh tests/run.sh`: 25 Tests erfolgreich.
- Nicht geprüft: Homescreen-Symbol auf echtem iPad/iPhone und Android.

## Fokus-Modus, 23.10.2026

- Reine Frontend-Änderung; Docker-Build mit TypeScript-Prüfung erfolgreich.
- Browserprüfung als `mia`: Nach „⤢ Fokus“ sind Kopfzeile, Begrüßung, Fußzeile und Zahlungsbestätigungen ausgeblendet (`display: none`), Tresen-Hinweis, Tischleiste und Bon-Board bleiben, unten rechts erscheint „● Service · Fokus beenden“. Nach Neuladen bleibt der Modus aktiv; auf 375 px Breite kein horizontaler Überlauf. „Fokus beenden“ stellt die Kopfzeile wieder her. Echtes Vollbild wurde im eingebetteten Browser nicht gewährt (erwartet).
- Nicht geprüft: Vollbild auf echten Tablets.

## PHP-Refactoring (23.10.2026)

- Composer-PSR-4-Namespace `Kitchen\`, Models, Services und separate SQL-Repositories eingeführt.
- PHPUnit 11: **10 Tests, 25 Assertions**, erfolgreich lokal und im Docker-Target `quality`.
- PHPStan 2: **Level 5, keine Fehler**, ohne Baseline; lokal und in Docker geprüft.
- `sh tests/run.sh`: **25 HTTP-/MariaDB-Integrationstests erfolgreich** nach dem Refactoring. Eigener Teststack; lokale App-Daten unverändert.
- Produktions-Docker-Target `runtime` und separates Entwicklungstarget `quality` erfolgreich gebaut.
- `git diff --check` erfolgreich. Bestehende Frontend-Dateien wurden nicht geändert.

## Service rufen aus Küche und Tresen, 24.10.2026

- Neue Tabelle `service_calls`, `ServiceCallService` und `ServiceCallRepository` im Muster der neuen PHP-Struktur, Routen im `Kernel`.
- `docker compose --profile tools run --build --rm quality`: PHPUnit 11 Tests, 30 Assertions (neu: Platzhalter-Filter der Stationen); PHPStan Level 5 ohne Fehler.
- `sh tests/run.sh`: 26 Tests erfolgreich. Neu `test_25`: Service und Tresen dürfen nicht für die Küche rufen (403); Küchenruf 201, zweiter Ruf aktualisiert nur den Grund; Service und Küche sehen den Ruf, der Tresen nicht; Tresen darf nicht antworten (403), Service antwortet, Doppelantwort ist unschädlich; beantworteter Ruf lässt sich nicht zurücknehmen (409) und zeigt „Mia“; zurückgenommener Ruf verschwindet und lässt sich nicht mehr beantworten.
- Browserprüfung im kurzzeitig gestarteten Teststack: Als `robin` „Bitte in die Küche“ gerufen, Anzeige mit Wartezeit und „Ruf zurücknehmen“; nach Antwort von Mia (per API) „✓ Mia kommt“ und Hinweis „Service kommt · Mia ist unterwegs“. Als `mia` erschien ein neuer Küchenruf in Orange mit Hinweis „Küche ruft den Service · Essen abholen · Robin“; nach „Ich komme“ stand „✓ Mia ist unterwegs · Küche“. Teststack danach entfernt, lokale Datenbank unberührt.
