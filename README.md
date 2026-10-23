# Zum Stimbeck · Kitchen Manager

Für die Weiterentwicklung mit einer anderen Person oder KI zuerst [UEBERGABE.md](UEBERGABE.md) lesen; Abschnitt 0 dort zeigt den aktuellen Stand und die offenen Punkte.

Lokal testbare erste Version: React/TypeScript SPA, PHP 8.4 API, MariaDB 11.4. Keine externen Backenddienste. Die Speisekarte stammt aus `speisekarte/Zum Stimbeck - Speisekarte.pdf` (Stand 07.09.2026) und wird bei leerer Datenbank aus [api/speisekarte.php](api/speisekarte.php) eingespielt.

## Start mit Docker

Voraussetzung: Docker Engine oder Docker Desktop muss laufen; Docker Compose ist erforderlich. Node.js und PHP müssen für diesen Weg nicht auf dem Host installiert sein. Port `127.0.0.1:8087` muss frei sein.

```sh
docker compose up -d --build
docker compose exec -T app php /var/www/html/api/migrate.php
docker compose ps
```

Die Migration ist beim ersten Start ein eigener, notwendiger Schritt. Danach die App unter http://127.0.0.1:8087/ öffnen. `db` sollte in `docker compose ps` als `healthy` erscheinen; `app` sollte laufen. Die Migration kann erneut ausgeführt werden und ergänzt bei bestehenden Datenbanken die Spalten und Tabellen für Tischbesuche, Stornierungen und Positionskorrekturen.

Alle Demo-Zugänge verwenden **Stimbeck-Demo-2026!**:

| Benutzer | Rolle |
| --- | --- |
| admin | Admin und alle Betriebsrollen |
| robin | Küche |
| mia | Service |
| tresen | Tresen |

Für parallele Rollen unterschiedliche Browserprofile oder private Fenster verwenden. Browser-Tabs teilen ihre Anmeldung.

## Tests

Die Integrationstests benötigen Docker und Python 3 auf dem Host. Sie starten einen eigenen, frischen Stack (`kittchenapp-test`, Port 8088, eigenes Datenbank-Volume mit festen Testartikeln) und räumen ihn danach wieder ab. Die lokale App auf Port 8087 und ihre Daten bleiben unberührt:

```sh
sh tests/run.sh
```

Für einen zusätzlichen Host-Build sind Node.js und npm erforderlich. Der Docker-Build führt denselben Frontend-Build bereits aus:

```sh
npm ci
npm run build
```

Die Integrationstests rufen die echte PHP-API mit separaten Sitzungen auf und schreiben nur in die Testdatenbank. Geprüft werden Anmeldung, CSRF, Rollen, Stationsisolation, wiederholtes Senden, Sonderwünsche, Statusablauf, Archivverlauf, Tischrechnung, Storno, Positionskorrekturen, Zahlbetrag, Validierung, Adminfunktionen inkl. Kartenpflege, Logout, Login-Drosselung pro Benutzer und Adresse, Anfragegröße, Bon-Rückstau, UTC-Zeitbasis und Datenbankausfall. Ohne `TEST_URL` zielen sie auf Port 8088, nie auf die lokale App. Nicht gegen Produktion ausführen.

## Bedienung

1. Als `mia` Tisch wählen und „Bestellung aufnehmen“ öffnen. Nur dann erscheinen Speisekarte und Entwurf. Die Karte ist in „Getränke“ und „Speisen“ getrennt und alphabetisch sortiert. Bei Getränken mit mehreren Größen wird nach dem Antippen die Menge in ml gewählt.
2. Positionen und Sonderwünsche eintragen; bei alkoholfreien Getränken für Kinder (Softgetränke, Säfte, Schorlen) lässt sich optional „Mit Strohhalm“ antippen. Fehler im noch nicht gesendeten Entwurf direkt an der Position korrigieren: Produkt austauschen, Menge ändern oder Position entfernen. Danach Bestellung senden. Jede gesendete Bestellung erhält eine eigene Nummer; ein atomarer Datenbankvorgang erzeugt separate Küchen- und Tresenbons.
3. Als `robin` bzw. `tresen` Bon starten und abholbereit melden. Ist niemand mit Tresen-Rolle in der Tresen-Ansicht (30 Sekunden ohne Abruf oder abgemeldet), zeigt die Service-Ansicht „Tresen ist gerade nicht besetzt“; Service kann dann Getränkebons starten, abholbereit melden und Stornohinweise des Tresens quittieren. Sobald der Tresen wieder da ist, endet die Vertretung.
4. Service bestätigt die Abholung. Unter „Kassieren“ stehen alle noch zu bezahlenden Speisen und Getränke des Tischbesuchs nach Bestellnummer mit Preisen. Nach Abholung aller Bons kann Service den angezeigten Gesamtbetrag als bezahlt markieren.
5. Unter „Kassieren“ können Positionen mit Grund ergänzt, korrigiert oder einzeln entfernt werden. Auch die Bestellnotiz ist änderbar. Nachträge und Ersatzpositionen erscheinen als neue Bons bei Küche oder Tresen unter derselben Bestellnummer. Entfernte Positionen bleiben im Änderungsverlauf sichtbar und zählen nicht mehr zum Zahlbetrag. Eine ganze unbezahlte Bestellung kann weiterhin mit Grund storniert werden. Nach der Zahlungsbestätigung sind Änderungen gesperrt.
   Wird ein bereits gestarteter oder abholbereiter Bon ganz oder positionsweise storniert, erscheint ein Hinweis in der betroffenen Küche oder am Tresen. Die Station quittiert ihn mit „Storno gesehen“; bis dahin kann ein betroffener aktiver Bon nicht weiter auf „Abholbereit“ oder „Abgeholt“ gesetzt werden.
   Unter „Letzte Zahlungsbestätigungen“ kann Service die manuelle Zahlungsbestätigung des jüngsten Tischbesuchs mit Pflichtgrund zurücknehmen. Der Besuch wird wieder offen, und Bestätigung sowie Rücknahme stehen mit Person, Betrag und Grund im Zahlungsverlauf. Bei einem neueren Besuch ist die Rücknahme gesperrt; die Kasse muss dann separat abgeglichen werden.
6. Admin kann Mitarbeitende anlegen, Namen, Benutzernamen und mehrere Rollen (Service, Küche, Tresen, Admin) bearbeiten sowie Zugänge entfernen und wieder aktivieren. Entfernen sperrt die Anmeldung, erhält aber die Zuordnung früherer Bestellungen. Der eigene und der letzte Admin-Zugang sind geschützt. Die Speisekarte wird getrennt nach Getränken und Speisen gepflegt. Ein Getränk ist ein Eintrag mit Name, Kategorie, Strohhalm-Option und einer oder mehreren Portionsgrößen, jede mit Menge in ml und eigenem Preis (z. B. Apfelschorle: 300 ml 3,50 €, 500 ml 5,20 €). Größen kommen über „+ Weitere Größe“ hinzu; bestehende Größen werden nicht gelöscht, sondern auf „nicht verfügbar“ gesetzt. Speisen haben Name, Preis und Kategorie. Bereits gesendete Bestellungen behalten Name und Preis. Tische können angelegt werden; existierende Tischnummern werden umbenannt.

Die Oberfläche fragt alle zwei Sekunden Änderungen ab. Timer verwenden die Serverzeit und laufen lokal weiter. Der Verbindungsstatus zeigt fehlgeschlagene Abfragen. Bestellentwürfe werden pro Nutzer im lokalen Browser gespeichert; ungesendete Entwürfe sind keine Küchenbestellungen. Bei einem unklaren Sendefehler unverändert erneut senden, damit dieselbe Bestellkennung verwendet wird.

## Architektur

- `frontend/src/main.tsx`: rollenabhängige Oberfläche, Bestellaufnahme, Stationsboards, Archiv, Admin.
- `api/index.php`: JSON-Endpunkte, Authentifizierung, Rollenprüfung, CSRF, Workflow.
- `api/db.php`: PDO-Verbindung und vorbereitete SQL-Abfragen.
- `api/migrate.php`: wiederholbar ausführbare Schemaanlage, Speisekarte bei leerer Tabelle, lokale Demo-Konten und Tische.
- `api/speisekarte.php`: Produkte und Preise der gedruckten Speisekarte.
- `tests/run.sh`: startet den getrennten Teststack und führt die Integrationstests aus.
- `tests/integration.py`: unabhängige HTTP-Integrationstests, nur Python-Standardbibliothek.

API-Routing über `/api/index.php?action=...` benötigt keine Rewrite-Regeln. Schreibzugriffe sind POST und benötigen einen CSRF-Token. Benutzerrollen werden bei jedem Zugriff aus der Datenbank gelesen. Bons haben die Zustände `new → working → ready → collected`; beim Storno erhalten betroffene Bons den Zustand `cancelled`. Statusänderungen sperren den Bon innerhalb einer Transaktion. Die eindeutige technische Bestellkennung verhindert doppelte Speicherung; die sichtbare Bestellnummer leitet sich aus der Datenbank-ID ab. Gesendete Positionen speichern Name und Preis als Snapshot. Korrekturen haben eigene Kennungen und einen Verlauf mit Grund, Person und Vorher-/Nachher-Daten. Die Zahlungsbestätigung prüft den zuletzt angezeigten Betrag erneut auf dem Server. Alle Zeitstempel sind UTC; die Oberfläche zeigt lokale Uhrzeit.

## Hetzner Managed Server

Frontend mit `npm ci && npm run build` erzeugen. Inhalt von `dist/` sowie das Verzeichnis `api/` in das Document Root der eigenen Subdomain laden. PHP 8.4 mit PDO MySQL vorausgesetzt. DB_HOST, DB_NAME, DB_USER, DB_PASSWORD und APP_ENV=production sicher in der Serverumgebung setzen. Migration per PHP-CLI ausführen; sie spielt bei leerer Tabelle die Speisekarte ein. Bei production werden keine Demo-Benutzer und Tische erzeugt und Session-Cookies erfordern HTTPS. Vor echtem Einsatz den initialen Administrator per `ADMIN_USERNAME` und `ADMIN_PASSWORD` in der CLI-Umgebung mit `php api/create-admin.php` anlegen; die öffentliche Registrierung ist bewusst nicht vorhanden. Automatisierte Backups müssen am Hosting eingerichtet werden.

## Umfang dieser ersten Version

Enthalten sind Login mit Sperre pro Benutzer und Adresse, Rollen, Mehrfachrollen, Speisekarte aus der gedruckten Karte mit Pflege im Admin (Getränke mit Portionsgrößen und Preis je Größe), Bestellungen mit Nummer, Sonderwünsche, Stationsbons, Statuswechsel, Abholhinweise, Tischrechnung, vollständiges und positionsweises Bestellstorno mit Grund und Stationsquittierung bei späten Stornos, Positionskorrekturen und Nachträge, manuelle Zahlungsbestätigung samt protokollierter Rücknahme, Service-Vertretung für den unbesetzten Tresen, Archiv und grundlegende Adminanlage.

Noch nicht enthalten: Passwort zurücksetzen in der Oberfläche, Löschen von Produkten (nur Deaktivieren), Teilzahlungen und Zahlarten, Offline-Synchronisierung zwischen Geräten, Push/Audio, Drag-and-drop, PWA-Service-Worker, fiskalische Belege und Kassensystemanbindung, automatisierte Deployment-/Backupjobs. Zahlungsbestätigung und Rücknahme ändern nur den manuell erfassten Status; sie verarbeiten weder Zahlung noch echte Erstattung. Archiv zeigt zunächst die letzten 200 Bons. Nicht als fertiges Kassensystem verwenden.

## Stoppen

```sh
docker compose down
```

Das benannte Volume behält die Daten. `docker compose down -v` löscht ausdrücklich die lokale Datenbank; die nächste Migration legt Speisekarte, Demo-Konten und Tische neu an.
