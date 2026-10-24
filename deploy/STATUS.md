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
