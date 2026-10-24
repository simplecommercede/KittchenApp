# Live-Betrieb auf app.zumstimbeck.de

## Status und Zugangsdaten

Docker bleibt die lokale Entwicklungsumgebung. Live läuft das gebaute Frontend mit PHP auf dem Hetzner Managed Server und einer **neuen PostgreSQL-Datenbank**. Erstinstallation am 24.10.2026 durchgeführt; Details siehe `deploy/STATUS.md`. Eine Übernahme bestehender MariaDB-Daten ist nicht enthalten.

Die vorbereiteten, Git-ignorierten Dateien lokal ausfüllen:

- `config/live.ini`: PostgreSQL-Host, Port, Datenbank, Benutzer, Passwort und TLS-Modus laut Hetzner.
- `config/upload.ini`: SSH-Benutzer, absoluter App-Stammpfad außerhalb des Webroots, PHP-8.4-CLI-Pfad. Host ist vorbelegt; vor dem ersten Upload prüfen.

Fehlen diese lokalen Dateien, die jeweiligen `.example`-Dateien kopieren. Werte in doppelte Anführungszeichen setzen; enthaltene Anführungszeichen mit Backslash maskieren. Keine Zugangsdaten committen oder in das Frontend einbauen. Das Upload-INI dient als Übergabe der Zielparameter, es löst selbst keine Verbindung aus.

Voraussetzungen: PHP >= 8.4, `pdo_pgsql`, `mbstring`, `session`, SSH/SFTP-Zugang und ein einstellbares DocumentRoot für `app.zumstimbeck.de`. Die PHP-Version für CLI und Web separat prüfen. PostgreSQL wurde mit Version 16 getestet. Die DB muss vom PHP-Host erreichbar sein; der DB-Benutzer braucht für Migrationen Rechte zum Anlegen/Ändern seiner Tabellen. `DB_SSLMODE=require` verschlüsselt, prüft aber nicht die Serveridentität. Wenn Hetzner CA und passenden Hostnamen bereitstellt, `verify-full` mit `DB_SSLROOTCERT` verwenden. Bei lokaler DB-Verbindung den von Hetzner vorgesehenen Modus einstellen.

## Lokal testen

```sh
# Bestehender MariaDB-Entwicklungsstack, Port 8087:
docker compose up -d --build

# Optional eigener PostgreSQL-Entwicklungsstack, Port 8089:
docker compose -f compose.postgres.yaml -p kittchenapp-pg up -d --build --wait
docker compose -f compose.postgres.yaml -p kittchenapp-pg exec app php api/migrate.php

# Isolierte Integrationstests, ohne die Entwicklungsdaten zu löschen:
sh tests/run.sh mysql
sh tests/run.sh pgsql
composer check
```

Testskripte löschen ausschließlich ihre eigenen Test-Volumes. Niemals `down -v` gegen den Entwicklungs- oder Live-Stack verwenden.

## Upload-Paket bauen

```sh
sh deploy/build.sh
```

Erzeugt `build/<release>.tar.gz`, inklusive gebautem Frontend und PHP-Abhängigkeiten ohne Entwicklungsabhängigkeiten. Ein nicht committeter Arbeitsstand erhält den Zusatz `worktree`. Der Befehl lädt nichts hoch. Zugangsdaten und Docker-Daten sind nicht Teil des Pakets. Für den späteren Upload benutzen wir SSH/SFTP mit dem dann geprüften Ziel; keine Passwörter als Kommandozeilenargumente.

## Zielstruktur und erster Upload

```text
<remote-app-root>/
  shared/app.ini                # private Live-Konfiguration
  releases/<release>/
    RELEASE
    public/                     # ausschließlich dieses Verzeichnis veröffentlichen
      index.html
      assets/
      api/
  current -> releases/<release>/public
```

1. App-Stammpfad und DocumentRoot in konsoleH prüfen. `shared` darf über keine Domain öffentlich erreichbar sein.
2. Paket in ein neues Release-Verzeichnis übertragen und entpacken. `config/live.ini` separat als `shared/app.ini` übertragen, nur für den ausführenden PHP-/SSH-Benutzer lesbar machen (normalerweise `chmod 600`).
3. CLI-Verbindungstest mit dem richtigen PHP-Binary ausführen:
   ```sh
   /pfad/zu/php84 /absoluter/app-root/releases/RELEASE/public/api/check-environment.php
   ```
   Er prüft Konfiguration und DB-Verbindung, schreibt keine Daten. Bootstrap findet `shared/app.ini` automatisch bei exakt obiger Verzeichnisstruktur. Alternativ `APP_CONFIG_FILE` als absoluten Pfad setzen (für CLI **und** Web-PHP konfigurieren).
4. Vor späteren Migrationen Datenbanksicherung erstellen. Für die neue DB `api/migrate.php` per CLI ausführen. PostgreSQL-Migrationen werden transaktional und versioniert ausgeführt. Eine Wiederholung darf keine Daten verdoppeln. `APP_ENV=production` erzeugt keine Demo-Benutzer und keine Demo-Tische; die Speisekarte wird initialisiert.
5. Ersten Admin per `api/create-admin.php` anlegen. `ADMIN_USERNAME` und ein mindestens 12 Zeichen langes `ADMIN_PASSWORD` nur temporär in der Prozessumgebung bereitstellen (Passwort verdeckt einlesen, danach Variable löschen). Kein Demo-Passwort verwenden. Das Skript ist nur per CLI ausführbar. Tische und Mitarbeiter anschließend in der Administration anlegen.
6. DocumentRoot von `app.zumstimbeck.de` auf `current` zeigen lassen, sofern der Hosting-Account Symlinks unterstützt; alternativ direkt auf das neue `releases/<release>/public`. HTTPS im SSL-Manager aktivieren und HTTP auf HTTPS umleiten. Erst nach erfolgreichem Vorbereitungstest umschalten.
7. Anmeldung, Rollen, Bestellung mit Sonderwunsch, Küche/Tresen, Abholung und Abrechnung über HTTPS prüfen. Private Konfiguration darf unter keiner URL abrufbar sein.

Das Zurückschalten auf ein vorheriges Release setzt nur den Code zurück. Datenbankschema und Daten werden dabei nicht zurückgerollt. Zukünftige Migrationen müssen daher kompatibel geplant oder aus einer geprüften Sicherung wiederhergestellt werden. Lokale Testdaten werden nicht mit hochgeladen.

## Festes DocumentRoot (tatsächliche Erstinstallation)

Bei Hetzner ist `public_html/app` das feste Webverzeichnis. Die Release-Dateien werden dorthin kopiert; `.htaccess` und `.htpasswd` bleiben erhalten. `api/config-path.php` gibt ausschließlich den absoluten Pfad der privaten INI-Datei zurück. Bootstrap lädt diese Datei, wenn `APP_CONFIG_FILE` nicht gesetzt ist. Der Locator ist serverabhängig, Git-ignoriert und enthält keine Zugangsdaten. Er muss bei späteren Uploads erhalten bleiben. Das automatische Release-Layout ist weiterhin für Installationen ohne Locator verfügbar.

SSH verwendet Port **222**. SFTP auf Port 22 ist ein separater Zugang ohne Shell. Für SCP über den SSH-Zugang ebenfalls Port 222 verwenden. Admin-Zugangsdaten liegen nur lokal in `config/admin.ini`; nicht ins Release übernehmen.
