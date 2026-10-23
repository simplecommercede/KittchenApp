# Zum Stimbeck · Kitchen Manager

Für die Weiterentwicklung mit einer anderen Person oder KI zuerst [UEBERGABE.md](UEBERGABE.md) lesen.

Lokal testbare erste Version: React/TypeScript SPA, PHP 8.4 API, MariaDB 11.4. Keine externen Backenddienste. Beispielkarte und Preise sind keine bestätigten Restaurantdaten.

## Start mit Docker

Voraussetzung: Docker Engine oder Docker Desktop muss laufen; Docker Compose ist erforderlich. Node.js und PHP müssen für diesen Weg nicht auf dem Host installiert sein. Port `127.0.0.1:8087` muss frei sein.

```sh
docker compose up -d --build
docker compose exec -T app php /var/www/html/api/migrate.php
docker compose ps
```

Die Migration ist beim ersten Start ein eigener, notwendiger Schritt. Danach die App unter http://127.0.0.1:8087/ öffnen. `db` sollte in `docker compose ps` als `healthy` erscheinen; `app` sollte laufen. Die Migration kann erneut ausgeführt werden und ergänzt bei bestehenden Datenbanken die Spalten für Tischaktivierung, Tischbesuche und Stornierungen.

Alle Demo-Zugänge verwenden **Stimbeck-Demo-2026!**:

| Benutzer | Rolle |
| --- | --- |
| admin | Admin und alle Betriebsrollen |
| robin | Küche |
| mia | Service |
| tresen | Tresen |

Für parallele Rollen unterschiedliche Browserprofile oder private Fenster verwenden. Browser-Tabs teilen ihre Anmeldung.

## Tests

Optional nach dem Start und der Migration; die Integrationstests benötigen Python 3 auf dem Host und schreiben Testdaten in die **lokale** Datenbank:

```sh
python3 tests/integration.py
```

Für einen zusätzlichen Host-Build sind Node.js und npm erforderlich. Der Docker-Build führt denselben Frontend-Build bereits aus:

```sh
npm ci
npm run build
```

Die Integrationstests rufen die echte PHP-API mit separaten Sitzungen auf und schreiben in die lokale Datenbank. Geprüft werden Anmeldung, CSRF, Rollen, Stationsisolation, wiederholtes Senden, Sonderwünsche, Statusablauf, Archivverlauf, Tischrechnung, Storno, Zahlbetrag, Validierung, Adminfunktionen, Logout und Login-Drosselung. Testbenutzer und Testdaten bleiben lokal erhalten. Nicht gegen Produktion ausführen.

## Bedienung

1. Als `mia` Tisch wählen und „Bestellung aufnehmen“ öffnen. Nur dann erscheinen Speisekarte und Entwurf.
2. Positionen und Sonderwünsche eintragen und Bestellung senden. Jede gesendete Bestellung erhält eine eigene Nummer; ein atomarer Datenbankvorgang erzeugt separate Küchen- und Tresenbons.
3. Als `robin` bzw. `tresen` Bon starten und abholbereit melden.
4. Service bestätigt die Abholung. Unter „Kassieren“ stehen alle noch zu bezahlenden Speisen und Getränke des Tischbesuchs nach Bestellnummer mit Preisen. Nach Abholung aller Bons kann Service den angezeigten Gesamtbetrag als bezahlt markieren.
5. Eine unbezahlte Bestellung kann dort mit Stornogrund komplett storniert werden. Sie wird aus dem Zahlbetrag entfernt und bleibt mit ihrem Verlauf im Archiv sichtbar. Bereits bezahlte Bestellungen sind hier nicht stornierbar.
6. Admin kann Mitarbeiter mit mehreren Rollen, Gerichte und Tische anlegen. Existierende Tischnummern werden umbenannt.

Die Oberfläche fragt alle zwei Sekunden Änderungen ab. Timer verwenden die Serverzeit und laufen lokal weiter. Der Verbindungsstatus zeigt fehlgeschlagene Abfragen. Bestellentwürfe werden pro Nutzer im lokalen Browser gespeichert; ungesendete Entwürfe sind keine Küchenbestellungen. Bei einem unklaren Sendefehler unverändert erneut senden, damit dieselbe Bestellkennung verwendet wird.

## Architektur

- `frontend/src/main.tsx`: rollenabhängige Oberfläche, Bestellaufnahme, Stationsboards, Archiv, Admin.
- `api/index.php`: JSON-Endpunkte, Authentifizierung, Rollenprüfung, CSRF, Workflow.
- `api/db.php`: PDO-Verbindung und vorbereitete SQL-Abfragen.
- `api/migrate.php`: wiederholbar ausführbare Schemaanlage und lokale Beispieldaten.
- `tests/integration.py`: unabhängige HTTP-Integrationstests, nur Python-Standardbibliothek.

API-Routing über `/api/index.php?action=...` benötigt keine Rewrite-Regeln. Schreibzugriffe sind POST und benötigen einen CSRF-Token. Benutzerrollen werden bei jedem Zugriff aus der Datenbank gelesen. Bons haben die Zustände `new → working → ready → collected`; beim Storno erhalten alle Bons der Bestellung den Zustand `cancelled`. Statusänderungen sperren den Bon innerhalb einer Transaktion. Die eindeutige technische Bestellkennung verhindert doppelte Speicherung; die sichtbare Bestellnummer leitet sich aus der Datenbank-ID ab. Gesendete Positionen speichern Name und Preis als Snapshot. Die Zahlungsbestätigung prüft den zuletzt angezeigten Betrag erneut auf dem Server. Alle Zeitstempel sind UTC; die Oberfläche zeigt lokale Uhrzeit.

## Hetzner Managed Server

Frontend mit `npm ci && npm run build` erzeugen. Inhalt von `dist/` sowie das Verzeichnis `api/` in das Document Root der eigenen Subdomain laden. PHP 8.4 mit PDO MySQL vorausgesetzt. DB_HOST, DB_NAME, DB_USER, DB_PASSWORD und APP_ENV=production sicher in der Serverumgebung setzen. Migration per PHP-CLI ausführen. Bei production werden keine Demo-Benutzer erzeugt und Session-Cookies erfordern HTTPS. Vor echtem Einsatz den initialen Administrator per `ADMIN_USERNAME` und `ADMIN_PASSWORD` in der CLI-Umgebung mit `php api/create-admin.php` anlegen; die öffentliche Registrierung ist bewusst nicht vorhanden. Automatisierte Backups müssen am Hosting eingerichtet werden.

## Umfang dieser ersten Version

Enthalten sind Login, Rollen, Mehrfachrollen, Speisekarte, Bestellungen mit Nummer, Sonderwünsche, Stationsbons, Statuswechsel, Abholhinweise, Tischrechnung, vollständiges Bestellstorno mit Grund, manuelle Zahlungsbestätigung, Archiv und grundlegende Adminanlage.

Noch nicht enthalten: Mitarbeiter sperren/Rollen bearbeiten/Passwort zurücksetzen in der Oberfläche, vollständige Speisekartenbearbeitung, Teilstorno und Positionskorrektur nach Versand, Teilzahlungen und Zahlarten, Offline-Synchronisierung zwischen Geräten, Push/Audio, Drag-and-drop, PWA-Service-Worker, fiskalische Belege und Kassensystemanbindung, automatisierte Deployment-/Backupjobs. Die Zahlungsbestätigung erfasst nur den manuell bestätigten Status; sie verarbeitet keine Zahlung. Archiv zeigt zunächst die letzten 200 Bons. Nicht als fertiges Kassensystem verwenden.

Geplant: Wenn kein Tresen-Mitarbeiter aktiv ist, aber Service online ist, übernimmt Service die Tresenarbeiten und kann Getränkebons bis „Abholbereit“ bearbeiten. Dafür fehlen derzeit die stationsweite Anwesenheitserkennung und die entsprechende Freigabe; Details stehen in [UEBERGABE.md](UEBERGABE.md).

## Stoppen

```sh
docker compose down
```

Das benannte Volume behält die Daten. `docker compose down -v` löscht ausdrücklich die lokale Demo-Datenbank.
