# Übergabe: Zum Stimbeck – Kitchen Manager

Stand: 23.10.2026. Diese Datei ist der Einstieg für die nächste entwickelnde Person und ihre KI. Sie beschreibt den tatsächlich vorhandenen Stand, die ursprüngliche Absicht und offene Arbeiten. Bei Abweichungen zuerst den aktuellen Code prüfen; Aussagen zu Testergebnissen sind historische Ergebnisse, keine Garantie für spätere Änderungen.

## 1. Auftrag und Produktziel

Für Robin als Koch und das Serviceteam des Restaurants **Zum Stimbeck in Steinbeck an der Luhe** soll eine gemeinsame deutschsprachige SPA entstehen:

- Service nimmt am Tisch Speisen und Getränke auf, einschließlich Sonderwünschen wie „Ohne Tomate“, „Extra Gurke“ oder Freitext.
- Bestätigte Bestellungen werden nach Station aufgeteilt: Speisen zur Küche, Getränke zum Tresen.
- Küche und Tresen sehen digitale Bons mit Tisch, Eingangszeit und laufender Wartezeit.
- Ablauf: Neu → In Arbeit → Abholbereit → vom Service abgeholt → Archiv.
- Service erkennt den Fortschritt und bestätigt die Abholung.
- Abgeschlossene Bons bleiben zum Nachschlagen erhalten.
- Anmeldung und Rollen: Service, Küche, Tresen, Admin; mehrere Rollen pro Mitarbeiter.
- Geplante Vertretung: Ist kein Tresen-Mitarbeiter aktiv, aber Service online, übernimmt Service auch die Tresenarbeiten und bearbeitet die Getränkebons bis „Abholbereit“.
- Bedienung auf iPad und Smartphone, große Schaltflächen für den Restaurantalltag.
- Später eventuell Drag-and-drop, Probleme melden, Spracheingabe und Kassensystem. Das gehört nicht zum bisher fertigen Umfang.

Die erste Implementierung wurde ausdrücklich in einem **10-Minuten-Zeitfenster** erstellt. Sie ist ein getesteter lokaler Ausgangspunkt, keine fertig abgenommene Produktionslösung. Das Zeitlimit bezog sich auf diesen ersten Implementierungsschritt, nicht automatisch auf spätere Aufgaben.

## 2. Getroffene Architekturentscheidung

Zielhosting ist der vorhandene **Hetzner Managed Server mit konsoleH**. Der Screenshot zeigt PHP-, MariaDB/MySQL- und PostgreSQL-Verwaltung. Genaue produktive PHP-Version, Zugänge, Tarifdetails und Deploymentverfahren wurden nicht geprüft.

Vereinbart und umgesetzt:

- React 19 + TypeScript, Vite 6 als Buildwerkzeug.
- PHP 8.4 mit PDO MySQL als JSON-API, ohne PHP-Framework und ohne Composer-Abhängigkeiten.
- MariaDB 11.4 in der lokalen Docker-Umgebung.
- Cookie-basierte PHP-Sessions und CSRF-Schutz.
- Polling im Abstand von zwei Sekunden statt WebSockets/SSE.
- Docker Compose für die lokale Entwicklung und Tests.
- Auf Hetzner sollen statische Builddateien und PHP laufen; Docker und ein dauerhafter Node-Prozess werden dort nicht vorausgesetzt.

Frühere Vorschläge mit Next.js, Supabase, PostgreSQL oder SSE wurden durch diese Hostingentscheidung ersetzt. Sie sind **nicht** der aktuelle Stack. Nicht ohne neuen Grund wieder darauf umstellen.

## 3. Projekt übernehmen und starten

Das gesamte Projektverzeichnis übergeben, einschließlich `package-lock.json`, `api/`, `frontend/`, `tests/` und der Dokumentation. `node_modules/` und `dist/` müssen nicht übertragen werden. Die Designbilder unter `output/` helfen beim visuellen Weiterarbeiten.

Das Projekt wird mit einem Initial-Commit auf dem Branch `main` im Repository https://github.com/simplecommercede/KittchenApp verwaltet. Zum Übernehmen das Repository klonen; lokale Abhängigkeiten, Buildausgaben und Docker-Datenbankvolumes sind nicht Bestandteil des Repositories.

Voraussetzungen: Docker Engine mit Compose; für Tests auf dem Host Python 3, für lokalen Frontend-Build Node.js/npm. Im Docker-Build wird Node 22 verwendet.

Im Projektverzeichnis:

```sh
docker compose up -d --build
docker compose exec -T app php /var/www/html/api/migrate.php
```

App: **http://localhost:8087/**

Die Migration ist ein separater Schritt. Der Container führt sie nicht automatisch aus. Wiederholtes Starten der Migration legt bestehende Tabellen/Seeds nicht doppelt an, ersetzt aber auch keine geänderten Tabellenstrukturen.

### Lokale Demo-Konten

| Benutzer | Rechte |
| --- | --- |
| `admin` | Admin, Service, Küche, Tresen |
| `robin` | Küche |
| `mia` | Service |
| `tresen` | Tresen |

Passwort für alle: `Stimbeck-Demo-2026!`.

Das sind absichtlich öffentliche **lokale Demo-Zugangsdaten**, keine Produktivzugänge. Auch die Datenbankpasswörter in `compose.yaml` sind ausschließlich für die lokale Demo.

Mehrere Rollen parallel mit getrennten Browserprofilen oder privaten Fenstern testen. Normale Tabs desselben Browserprofils teilen die Session.

```sh
# Zustand und Logs
docker compose ps
docker compose logs --tail=100 app db

# Nach Änderungen neu bauen; Quellcode ist nicht als Volume eingebunden
docker compose up -d --build

# Stoppen, Daten behalten
docker compose down
```

Das Volume `kitchen-db` hält die Daten. `docker compose down -v` löscht die lokale Datenbank und darf nicht versehentlich als normaler Neustart verwendet werden.

Port 8087 ist an `127.0.0.1` gebunden. Ein physisches iPad im WLAN kann diese Instanz deshalb nicht direkt erreichen. Für echte Gerätetests braucht es eine bewusst eingerichtete erreichbare Testadresse. Außerdem verwendet das Frontend `crypto.randomUUID()`, wofür außerhalb von localhost ein sicherer Browserkontext/HTTPS erforderlich ist.

`npm run dev` startet nur Vite. Es gibt derzeit keinen Vite-Proxy zur PHP-API; der vollständige verifizierte Entwicklungsweg ist Docker. Hot Reload muss bei Bedarf noch sauber eingerichtet werden.

## 4. Wo welcher Code liegt

| Datei | Zweck |
| --- | --- |
| `frontend/src/main.tsx` | Gesamte React-Oberfläche, API-Client, Session, Rollenansichten, Polling, Bestellentwurf, Adminformulare |
| `frontend/src/style.css` | Responsive Gestaltung, Grün/Creme, Karten, Boards, mobile Breakpoints |
| `api/index.php` | API-Router, Authentifizierung, Autorisierung, Validierung und Bestellworkflow |
| `api/db.php` | PDO-Verbindung, vorbereitete SQL-Abfragen |
| `api/migrate.php` | CLI-Schemaanlage, nur bei `APP_ENV=local` Demo-Seed |
| `api/create-admin.php` | CLI-Anlage eines initialen Administrators |
| `Dockerfile` | Mehrstufiger Node-Build, danach PHP-Apache mit PDO MySQL |
| `compose.yaml` | Lokale App, MariaDB, persistentes DB-Volume und Healthcheck |
| `tests/integration.py` | HTTP-Integrationstests mit Python-Standardbibliothek |
| `tests/RESULTS.md` | Dokumentierter erster Teststand |
| `README.md` | Kurzanleitung, Betrieb und Funktionsumfang |
| `output/designs/stimbeck-kueche.png` | Früher Bildentwurf Küche |
| `output/designs/stimbeck-service.png` | Früher Bildentwurf Service |
| `output/designs/prompts.txt` | Prompts der Bildentwürfe |
| `output/kitchen-running.png` | Screenshot der tatsächlich laufenden Implementierung |

Die zwei großen Implementierungsdateien wurden aus Zeitgründen kompakt geschrieben. Beim Weiterentwickeln sinnvoll in Komponenten, Hooks, API-Typen und PHP-Funktionen/Module aufteilen; Verhalten durch Tests absichern, nicht unnötig komplett neu bauen.

## 5. Datenmodell und Regeln

| Tabelle | Inhalt |
| --- | --- |
| `users` | Name, eindeutiger Benutzername, Passwort-Hash, JSON-Rollen, Aktiv-Flag |
| `menu` | Gerichte/Getränke, Kategorie, Station, Preis in Cent, Aktiv-Flag |
| `dining_tables` | Tischnummer und Anzeigename |
| `orders` | Eindeutige Anfragekennung, Tisch, Serviceperson, Gesamtnotiz, Eingangszeit |
| `tickets` | Stationsbon einer Bestellung, Status und Zeitstempel |
| `items` | Bonposition mit Mengen-, Namens-, Preis- und Sonderwunsch-Snapshot |
| `events` | Wer hat einen Bon wann in welchen Status gesetzt? |
| `login_attempts` | Fehlversuche und zeitweise Login-Sperre |

Wichtige Invarianten:

1. Eine Bestellung kann einen Küchenbon und einen Tresenbon haben. Beide werden unabhängig bearbeitet und abgeholt.
2. `orders.request_key` ist eindeutig. Eine Wiederholung derselben Kennung durch denselben Benutzer liefert die bestehende Bestellung zurück.
3. Bestellung, Bons, Positionen und initiale Events werden zusammen in einer DB-Transaktion gespeichert.
4. Statusänderungen verwenden `SELECT … FOR UPDATE`. Nur der unmittelbar nächste Zustand ist erlaubt; veraltete Aktionen ergeben HTTP 409.
5. `new → working → ready` ist Aufgabe der jeweiligen Station; `ready → collected` des Service. Admin darf alles.
6. Küche/Tresen erhalten über die API nur ihre erlaubten Stationen. Service und Admin sehen alle Bons, nicht nur selbst aufgenommene.
7. Das Archiv ist eine Abfrage nach `collected`, keine Löschung oder separate Archivtabelle.
8. Preise sind Ganzzahlen in Cent. Gesendete Artikelnamen und Preise ändern sich nicht mit späteren Speisekartenänderungen.
9. Mengen und Sonderwünsche gehören zur einzelnen Bestellposition. Unterschiedliche Wünsche bei gleichem Gericht als separate Positionen erfassen.

## 6. API-Vertrag

Basis: `/api/index.php?action=...`. JSON als Ein- und Ausgabe. Fehlerformat: `{ "error": "Meldung" }`.

Zuerst `GET session` ausführen; die Antwort enthält `csrf` und ggf. `user`. Bei allen POST-Anfragen Session-Cookie und Header `X-CSRF-Token` mitsenden. Login erneuert Session-ID und CSRF-Token; danach den neuen Token benutzen.

| Methode / Aktion | Inhalt / Berechtigung |
| --- | --- |
| GET `session` | Session und CSRF; ohne Anmeldung möglich |
| POST `login` | `username`, `password` |
| POST `logout` | Leeres JSON-Objekt; angemeldet |
| GET `menu` | Speisekarte einschließlich temporär deaktivierter Produkte; angemeldet |
| GET `tables` | Tische; angemeldet |
| GET `table_status` | Offene Tischbesuche, Warte- und Zahlungsstatus; Service/Admin |
| GET `table_bill&table_id=…` | Bestellungen, Positionen, Preis-Snapshots und Zahlbetrag eines Tischbesuchs; Service/Admin |
| GET `tickets` | Aktive Bons plus `server_time`; nach Rollen gefiltert |
| GET `tickets&archive=1` | Abgeholte und stornierte Bons mit `events` |
| POST `order` | `request_key`, `table_id`, `note`, `items`; Service/Admin |
| POST `order_cancel` | `order_id`, `reason`; vollständiges Storno vor Zahlung; Service/Admin |
| POST `table_paid` | `visit_id`, `expected_total`; manuelle Bestätigung nach Abholung aller Bons; Service/Admin |
| POST `status` | `id`, `status`; rollen- und transitionsabhängig |
| GET `admin_users` | Benutzer ohne Passwort-Hashes; Admin |
| POST `admin_user` | Neuen Benutzer anlegen; `name`, `username`, `password`, `roles` |
| POST `admin_menu` | Anlegen; mit `id` auch Aktualisieren; `name`, `category`, `station`, `price` |
| POST `admin_menu_status` | Produkt temporär aktivieren/deaktivieren; Admin |
| POST `admin_table` | Tisch anlegen/umbenennen; `id`, `name` |
| POST `admin_table_status` | Tisch aktivieren/deaktivieren; Admin |

Beispielbestellung (Kennung für jede neue Bestellung neu erzeugen; für unveränderten Retry beibehalten):

```json
{
  "request_key": "c710c0b2-8380-40b0-a565-b0f654ed5681",
  "table_id": 7,
  "note": "Zusammen servieren",
  "items": [
    {"menu_id": 1, "quantity": 1, "note": "Ohne Tomate · Extra Gurke"},
    {"menu_id": 6, "quantity": 2, "note": ""}
  ]
}
```

IDs und Mengen in Schreibanfragen als JSON-Zahlen senden. Stationswerte: `kitchen`, `bar`; Rollen zusätzlich `service`, `admin`. HTTP-Codes u.a. 400 Validierung, 401 Anmeldung, 403 Rechte/CSRF, 409 Konflikt, 429 Login-Limit, 503 Datenbankfehler.

## 7. Verifiziert und nicht verifiziert

Am 23.10.2026 erfolgreich ausgeführt:

```sh
npm run build
php -l api/index.php
php -l api/migrate.php
python3 tests/integration.py
```

Docker-Image wurde gebaut, MariaDB meldete healthy und die App wurde auf Port 8087 gestartet.

Acht HTTP-Integrationstests gegen die echte Docker-API/Datenbank bestanden:

- Anmeldung und CSRF.
- Fehlende Berechtigungen.
- Vollständiger Stationsworkflow inkl. Sonderwünschen, wiederholtem Senden und Archiv.
- Ungültige Bestellungen.
- Adminanlage und ungültige Rollen.
- Logout.
- Namens-/Preis-Snapshots und atomare Validierung.
- Login-Drosselung.

Manueller Browser-Smoke-Test: Admin anmelden, Tisch 07 wählen, Hamburger mit zwei Sonderwünschen senden, Küchenbon sehen und in Arbeit nehmen. Screenshot liegt im Projekt.

**Grenzen des Nachweises:** Keine automatisierte Browser-Suite, keine Parallelitäts-/Lasttests, keine realen iPad-/Safari-Tests, keine Prüfung auf Hetzner, keine Produktionsfreigabe. Der vorher erfolgreiche Build ersetzt keinen Test nach neuen Änderungen.

Die Integrationstests schreiben Daten, legen zusätzliche Benutzer/Gerichte/Tische an und hinterlassen Bons. Nur gegen eine dedizierte lokale Testdatenbank ausführen. `TEST_URL` überschreibt die Basisadresse; nicht auf Produktion setzen. Tests setzen die Demo-Benutzer und Artikel-IDs voraus.

## 8. Bekannte Einschränkungen und priorisierte Weiterarbeit

Die folgenden technischen Punkte wurden bei der Übergabe aus dem Code abgeleitet. Sie sind noch nicht alle mit eigenen Regressionstests reproduziert.

### Priorität 1: Bestell- und Sitzungssicherheit vor realem Einsatz

- **Unklarer Sendestatus:** Nach verlorener Antwort darf dieselbe Bestellung nur mit unveränderter Kennung erneut gesendet werden. Momentan erzeugt jede Entwurfsänderung eine neue Kennung. Bearbeitung nach einem möglicherweise bereits erfolgreichen Senden kann deshalb eine zweite Bestellung erzeugen. Einen expliziten Zustand „Sendestatus unklar“ mit serverseitiger Abfrage und festgehaltener Kennung einführen und testen.
- **Kennung mit verändertem Inhalt:** Das Backend vergleicht bei vorhandener Kennung Benutzer, aber keinen Payload-Hash. Abweichende Wiederholungen sollten klar als Konflikt abgewiesen werden.
- **Gleichzeitige identische Anfragen:** Unique Constraint verhindert doppelte Speicherung; bei einem Rennen kann die zweite Anfrage 409 statt derselben Erfolgsantwort bekommen. Mit echter Parallelität testen und gewünschte Retry-Semantik festlegen.
- **Session-Ablauf:** Fehler beim Polling werden generell als „Verbindung unterbrochen“ behandelt. HTTP 401 sollte eine erneute Anmeldung auslösen; CSRF-/Container-Neustarts sauber behandeln. Nach Container-Neuanlage gingen im lokalen Test Sessions verloren; Neuladen holte einen neuen Token.
- **Zeitbasis:** Frontend interpretiert DB-Zeitstempel als UTC; `server_time` kommt aus `gmdate`. Die Datenbankverbindung setzt derzeit keine explizite UTC-Zeitzone. Für Hetzner DB-Session und PHP-Zeitzone explizit vereinheitlichen und testen.
- **Passwörter:** `shortText()` trimmt derzeit auch Passwörter im Login und Adminformular; das CLI-Adminskript tut dies nicht. Passwortbehandlung vereinheitlichen und Whitespace unverändert behandeln.
- **Fehlerbehandlung:** Bei fehlgeschlagener DB-Verbindung kann auch der Aufruf von `db()` im Catch-Block fehlschlagen. Fehlerpfad ohne erneuten Verbindungsversuch absichern.

### Priorität 2: Alltagstauglichkeit

- **Service übernimmt den Tresen bei unbesetzter Station:** Anwesenheit/Verfügbarkeit der Tresen-Mitarbeiter und des Service verlässlich erfassen. Sobald kein Tresen aktiv und mindestens ein Service-Mitarbeiter online ist, sollen Service-Mitarbeiter die Tresenbons sehen und `new → working → ready` ausführen können. Die Bons bleiben der Station `bar` zugeordnet; ihre Bearbeitung und die Abholung müssen im Verlauf mit der handelnden Person nachvollziehbar sein. Sobald wieder ein Tresen aktiv ist, endet die automatische Vertretung für neue Aktionen. Mehrere gleichzeitig angemeldete Geräte, Verbindungsabbrüche und veraltete Anwesenheitsdaten bei der Freigabe prüfen und testen. **Noch nicht implementiert:** Der heutige Verbindungsstatus zeigt nur die Verbindung des eigenen Browsers; es gibt keine stationsweite Online-/Aktiv-Erkennung und Service darf Tresenbons derzeit nicht starten oder abholbereit melden.
- Pro Benutzer existiert nur ein lokaler Entwurf. Ein Tischwechsel mit ungesendetem Warenkorb verlangt inzwischen eine Bestätigung und verwirft den Entwurf; getrennte Entwürfe pro Tisch fehlen weiterhin.
- Entwurfzustand beim Benutzerwechsel vollständig zurücksetzen; lokale Entwürfe über Logout hinaus sind auf gemeinsam verwendeten Geräten bewusst zu behandeln.
- Lokalen Storage-Ausfall und beschädigte/überholte Entwürfe abfangen; ein Storagefehler nach erfolgreicher Speicherung darf nicht als ungesendete Bestellung missverstanden werden.
- Mitarbeiter bearbeiten/sperren, Rollen ändern und Passwortverfahren ergänzen; Schutz vor Aussperren des letzten Admins.
- Menü bearbeiten: Backend kann bereits per `id` aktualisieren, die Oberfläche kann hinzufügen und Produkte temporär deaktivieren oder aktivieren; weitere Bearbeitung fehlt.
- Archivsuche, Datum/Tischfilter und Pagination ergänzen. **Auch aktive Bons sind derzeit auf 200 begrenzt**, nicht nur das Archiv. Bei größerem Rückstau dürfen neue Bons nicht unsichtbar werden.
- Archiv-Timer zählt momentan weiter seit Eingang; für abgeschlossene Bons eine feste Dauer bis Abholung anzeigen.
- Vollständiges Storno einer unbezahlten Bestellung mit Grund und Event ist umgesetzt. Teilstorno, Positionskorrekturen und nachträgliche Fehlerfälle fehlen; historische Bons dürfen nicht still gelöscht werden.
- Tischname/Servicename kommen aktuell aus Joins, sind keine historischen Snapshots. Entscheiden, ob spätere Umbenennungen auch in alten Bons erscheinen dürfen.

### Priorität 3: Wartbarkeit, Tests und Betrieb

- React-Komponenten, API-Typen und PHP-Handler aufteilen; Formatierung und Linting etablieren.
- Versionierte Datenbankmigrationen einführen. `CREATE TABLE IF NOT EXISTS` aktualisiert keine vorhandenen Spalten.
- Separate Testdatenbank, Aufräumstrategie und automatisierte Browser-/Mobile-Tests ergänzen.
- Polling ohne überlappende Requests, mit Timeout, Backoff und Schutz gegen veraltete Antworten implementieren.
- Katalogänderungen werden nicht laufend abgefragt; automatische Aktualisierung oder Reload-Mechanismus definieren.
- CSP/Sicherheitsheader, Session-Lebensdauer, erweitertes Login-Limiting, persistente Sessions und Monitoring vor Produktion prüfen.
- API lädt Positionen/Events pro Bon einzeln. Bei Bedarf gebündelte Abfragen und geeignete Indizes vorsehen.
- Tablet-Layout, Touch-Ziele, Tastaturbedienung und Kontraste auf echten Geräten prüfen.
- Installierbare PWA, Audio-/Pushhinweise und Drag-and-drop sind Erweiterungen, noch nicht vorhanden.

## 9. Hetzner: Vorgehen und offene Punkte

Keine Produktionsdateien wurden verändert. Es wurde nichts auf Hetzner hochgeladen, keine Domain eingerichtet und keine produktive Datenbank angelegt.

Vor Deployment klären:

- Eigene Zielsubdomain, Document Root, PHP-Version und PDO-MySQL-Verfügbarkeit.
- SSH/SFTP-Zugang und CLI-PHP; sicherer Weg zur Konfiguration von Umgebungsvariablen für Web-PHP und CLI.
- Datenbankhost/-name/-benutzer, Berechtigungen und Zeichensatz.
- HTTPS, Zeitzone, Session-Speicherung, Fehlerlogs, Backups und Wiederherstellung.

Geplanter Weg:

1. `npm ci && npm run build` auf Entwicklungs-/Buildsystem.
2. Inhalt von `dist/` und Verzeichnis `api/` in die separate Subdomain ausliefern. Keine Demo-DB und keine lokalen Zugangsdaten übernehmen.
3. `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `APP_ENV=production` sicher konfigurieren. Eine `.env` wird vom aktuellen PHP-Code **nicht automatisch geladen**.
4. `php api/migrate.php` mit produktiver Umgebung ausführen; dadurch werden keine Demo-Konten angelegt.
5. Initialen Admin per CLI `php api/create-admin.php` mit sicher gesetzten `ADMIN_USERNAME` und `ADMIN_PASSWORD` anlegen. Keine Kennwörter in Git oder Chat schreiben.
6. Menü und Tische anlegen; kontrollierter Funktions-/Berechtigungstest unter HTTPS, anschließend Backup/Wiederherstellung prüfen.

Das ist ein Plan, kein bereits geprüftes Deployment. App und API unter derselben Origin halten. HTTPS-Cookies sind außerhalb `APP_ENV=local` aktiviert. Bestehende Shops auf dem Managed Server bleiben separate Anwendungen.

## 10. Gestaltung und Restaurantinformationen

Designrichtung: Cremeweiß, Dunkelgrün, gut lesbare Bons, bernsteinfarbene Sonderwünsche und grüne Abholhinweise. Große Tasten haben Vorrang; Ziehen ist eine optionale Ergänzung. Die tatsächliche Implementierung ist vereinfacht gegenüber den Bildentwürfen und verwendet keine Speisenfotos.

Restaurantzuordnung und Inspiration:

- https://www.bispingen.de/gastro/zum-stimbeck
- https://www.tripadvisor.de/Restaurant_Review-g651860-d16948713-Reviews-Gaststatte_Zum_Stimbeck-Bispingen_Lower_Saxony.html

Der öffentlich verlinkte Facebook-Auftritt ließ sich bei der Recherche nicht auslesen. Logo, Bildentwürfe, Beispielkarte und Preise sind keine verifizierten aktuellen Originaldaten des Restaurants. Vor realem Einsatz von Robin bestätigen lassen.

## 11. Kopierbarer Startauftrag für die nächste KI

> Lies zuerst UEBERGABE.md, README.md, tests/RESULTS.md und den tatsächlichen Code. Arbeite am vorhandenen React/TypeScript-, PHP- und MariaDB-Projekt weiter. Ziel ist der vorhandene Hetzner Managed Server; Supabase, Next.js und ein produktiver Node-Dienst sind nicht vorausgesetzt. Prüfe den Git- und Docker-Zustand, ohne vorhandene Daten zu löschen. Starte die App lokal nach Anleitung und führe Build sowie Integrationstests nur gegen eine lokale Testdatenbank aus. Übernimm die beschriebenen Rollen und den unabhängigen Workflow für Küche und Tresen. Plane auch die Service-Vertretung für einen unbesetzten Tresen gemäß Priorität 2 ein. Priorisiere zunächst die unter „Priorität 1“ beschriebenen Zuverlässigkeitslücken und ergänze passende Regressionstests. Halte funktionierende Abläufe und die Gestaltung bei. Unterscheide geprüfte Ergebnisse, offene Annahmen und fehlende Funktionen. Dokumentiere Änderungen und den neuen Teststand. Führe kein produktives Deployment ohne konkreten Auftrag und geklärtes Ziel durch.
