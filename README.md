# Zum Stimbeck · Kitchen Manager

Für die Weiterentwicklung mit einer anderen Person oder KI zuerst [UEBERGABE.md](UEBERGABE.md) lesen.

Lokal testbare erste Version: React/TypeScript SPA, PHP 8.4 API, MariaDB 11.4. Keine externen Backenddienste. Beispielkarte und Preise sind keine bestätigten Restaurantdaten.

## Start mit Docker

```sh
docker compose up -d --build
docker compose exec app php /var/www/html/api/migrate.php
```

Öffnen: http://localhost:8087

Alle Demo-Zugänge verwenden **Stimbeck-Demo-2026!**:

| Benutzer | Rolle |
| --- | --- |
| admin | Admin und alle Betriebsrollen |
| robin | Küche |
| mia | Service |
| tresen | Tresen |

Für parallele Rollen unterschiedliche Browserprofile oder private Fenster verwenden. Browser-Tabs teilen ihre Anmeldung.

## Tests

Nach dem Start und der Migration:

```sh
python3 tests/integration.py
npm ci
npm run build
```

Die Integrationstests rufen die echte PHP-API mit separaten Sitzungen auf und schreiben in die lokale Datenbank. Geprüft werden Anmeldung, CSRF, Rollen, Stationsisolation, wiederholtes Senden, Sonderwünsche, vollständiger Statusablauf, Archivverlauf, Validierung, Adminfunktionen, Logout und Login-Drosselung. Testbenutzer und Testdaten bleiben lokal erhalten. Nicht gegen Produktion ausführen.

## Bedienung

1. Als `mia` Tisch wählen, Positionen hinzufügen und Sonderwünsche eintragen.
2. Bestellung senden: Ein atomarer Datenbankvorgang erzeugt separate Küchen- und Tresenbons.
3. Als `robin` bzw. `tresen` Bon starten und abholbereit melden.
4. Service bestätigt die Abholung. Der Bon erscheint im Archiv mit Änderungsverlauf.
5. Admin kann Mitarbeiter mit mehreren Rollen, Gerichte und Tische anlegen. Existierende Tischnummern werden umbenannt.

Die Oberfläche fragt alle zwei Sekunden Änderungen ab. Timer verwenden die Serverzeit und laufen lokal weiter. Der Verbindungsstatus zeigt fehlgeschlagene Abfragen. Bestellentwürfe werden pro Nutzer im lokalen Browser gespeichert; ungesendete Entwürfe sind keine Küchenbestellungen. Bei einem unklaren Sendefehler unverändert erneut senden, damit dieselbe Bestellkennung verwendet wird.

## Architektur

- `frontend/src/main.tsx`: rollenabhängige Oberfläche, Bestellaufnahme, Stationsboards, Archiv, Admin.
- `api/index.php`: JSON-Endpunkte, Authentifizierung, Rollenprüfung, CSRF, Workflow.
- `api/db.php`: PDO-Verbindung und vorbereitete SQL-Abfragen.
- `api/migrate.php`: wiederholbar ausführbare Schemaanlage und lokale Beispieldaten.
- `tests/integration.py`: unabhängige HTTP-Integrationstests, nur Python-Standardbibliothek.

API-Routing über `/api/index.php?action=...` benötigt keine Rewrite-Regeln. Schreibzugriffe sind POST und benötigen einen CSRF-Token. Benutzerrollen werden bei jedem Zugriff aus der Datenbank gelesen. Bons haben die Zustände `new → working → ready → collected`. Statusänderungen sperren den Bon innerhalb einer Transaktion. Die eindeutige Bestellkennung verhindert doppelte Speicherung. Gesendete Positionen speichern Name und Preis als Snapshot. Alle Zeitstempel sind UTC; die Oberfläche zeigt lokale Uhrzeit.

## Hetzner Managed Server

Frontend mit `npm ci && npm run build` erzeugen. Inhalt von `dist/` sowie das Verzeichnis `api/` in das Document Root der eigenen Subdomain laden. PHP 8.4 mit PDO MySQL vorausgesetzt. DB_HOST, DB_NAME, DB_USER, DB_PASSWORD und APP_ENV=production sicher in der Serverumgebung setzen. Migration per PHP-CLI ausführen. Bei production werden keine Demo-Benutzer erzeugt und Session-Cookies erfordern HTTPS. Vor echtem Einsatz den initialen Administrator per `ADMIN_USERNAME` und `ADMIN_PASSWORD` in der CLI-Umgebung mit `php api/create-admin.php` anlegen; die öffentliche Registrierung ist bewusst nicht vorhanden. Automatisierte Backups müssen am Hosting eingerichtet werden.

## Umfang dieser ersten Version

Enthalten sind Login, Rollen, Mehrfachrollen, Speisekarte, Bestellungen, Sonderwünsche, Stationsbons, Statuswechsel, Abholhinweise, Archiv und grundlegende Adminanlage.

Noch nicht enthalten: Mitarbeiter sperren/Rollen bearbeiten/Passwort zurücksetzen in der Oberfläche, vollständige Speisekartenbearbeitung, Storno/Nachkorrektur, Offline-Synchronisierung zwischen Geräten, Push/Audio, Drag-and-drop, PWA-Service-Worker, Kassensystem, automatisierte Deployment-/Backupjobs. Archiv zeigt zunächst die letzten 200 Bons. Nicht als fertiges Kassensystem verwenden.

## Stoppen

```sh
docker compose down
```

Das benannte Volume behält die Daten. `docker compose down -v` löscht ausdrücklich die lokale Demo-Datenbank.
