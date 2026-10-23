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
