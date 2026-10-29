# Live-Stand – 24.10.2026

- URL: https://app.zumstimbeck.de
- Erstinstallation auf Hetzner abgeschlossen. SSL-Zertifikat erfolgreich validiert; vorhandener HTTP-Basic-Schutz erhalten.
- SSH: dedi1693.your-server.de, Port 222, Benutzer zumsth.
- PHP-CLI: /usr/bin/php84 (8.4.27), pdo_pgsql und mbstring vorhanden.
- Webroot: /usr/www/users/zumsth/app (im Account public_html/app).
- Privater Bereich: /usr/home/zumsth/kitchen; Konfiguration unter shared/app.ini, Rechte 600.
- Release: 20261024T163310Z-5858b14-worktree; bootstrap.php um den festen Konfigurations-Locator ergänzt. Arbeitsstand noch nicht committet.
- Release-Dateien wurden ins feste Webroot kopiert, kein Symlink-Wechsel. Vorhandene Auth-Dateien zuvor im privaten backups-Verzeichnis gesichert.
- PostgreSQL-Verbindung mit sslmode=require geprüft. Vor Migration leere public-Schema-Tabellenliste geprüft; Migration erfolgreich. Speisekarte mit 107 Einträgen vorhanden.
- Admin angelegt, generiertes Passwort nur in der lokalen ignorierten config/admin.ini. Temporäre Admin-Passwortdatei auf dem Server nach Anlage gelöscht.
- Keine Demo-Benutzer, keine Demo-Tische, keine Bestellungen erzeugt. Mitarbeiter und echte Tische sind anschließend über die Administration einzurichten.

## Prüfung

HTTPS und HTTP-Basic-Schutz (401 ohne Zugang), SPA und Assets, Session-Cookie Secure, Admin-Anmeldung, lesende Menü-/Tisch-/Bon-/Benutzer-API und Abmeldung erfolgreich über die öffentliche URL geprüft. Keine Testbestellungen in der Live-Datenbank angelegt. Vollständige Abläufe zuvor in Docker für MariaDB und PostgreSQL getestet; kein iPad-/Browser-Workflowtest auf Live in diesem Schritt.

## Weiter offen

Echte Tische/Mitarbeiter konfigurieren, Betriebsablauf auf den vorgesehenen Geräten abnehmen, regelmäßige Datenbanksicherung und Wiederherstellung bei Hetzner einrichten/prüfen. Spätere Updates müssen die privaten Konfigurations- und Auth-Dateien erhalten. Keine Zugangsdaten in Git speichern.

## Logging-Update 25.10.2026

API-Stand a0a7c33 installiert, einschließlich des zuvor hinzugekommenen Event-Loggings. Keine Migration, keine Änderung fachlicher Daten, keine Veröffentlichung der Homepage-Rechtsentwürfe.

- LOG_DIR=/usr/home/zumsth/SEC; Verzeichnis 0700, Tagesdatei 0600. Erste Datei debug_2026-10-25.log erfolgreich über CLI und HTTPS beschrieben und als JSON geprüft.
- Aufbewahrung heute plus sechs Vortage (Europe/Berlin); Bereinigung zusätzlich per Benutzer-Crontab alle fünf Minuten, bestehende Crontab vor Installation gesichert.
- API und private app.ini vor Austausch unter /usr/home/zumsth/kitchen/backups/before-a0a7c33 gesichert. Staging muss den Ordnernamen api behalten, da Composer relative Pfade darauf bezieht.
- PHPStan erfolgreich; 23 PHPUnit-Tests/70 Assertions und 28 PostgreSQL-Integrationstests erfolgreich.
- HTTPS-Session, Login mit lokal hinterlegtem Admin-Zugang, Menü, Tische, Bons, Benutzerliste und Logout geprüft. Vorhandene Live-Tische bleiben erhalten.
- Das vom Benutzer zuvor genannte gemeinsame Demo-Passwort wurde beim Admin-Test abgelehnt; keine Passwörter zurückgesetzt.
- Produktive Fehlversuchs-Logzeile geprüft: Event 2002 vorhanden, eingegebenes Passwort nicht gespeichert. Keine Log-Inhalte mit Benutzerbezug in dieser Dokumentation.

## Homepage-Deployment vom 29.10.2026

- Git-Stand `29028d9` auf main per Fast-forward übernommen und Homepage nach `/usr/www/users/zumsth` veröffentlicht.
- Neun HTML-Seiten, darunter Küche, Über uns, Feiern, Veranstaltungen und Kontakt; aktualisierte Navigation und Reservierungsdialog.
- Vorherige Homepage privat unter `kitchen/backups/homepage-before-29028d9/homepage.tar.gz` gesichert.
- JavaScript-Syntax und lokale Linkziele einschließlich Sprungmarken geprüft; Datenschutzlinks auf allen neun Seiten vorhanden.
- Alle 24 veröffentlichten Dateien über HTTPS bytegleich mit dem lokalen Stand geprüft.
- App, Datenbank und bestehende Zugriffsschutzdateien nicht verändert.
