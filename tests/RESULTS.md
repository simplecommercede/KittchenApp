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
