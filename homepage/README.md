# Homepage Zum Stimbeck

Statische Landingpage ohne Build-Schritt und ohne externe Dienste (keine Webfonts, keine eingebettete Google-Karte, keine Cookies). Der Ordner kann so, wie er ist, auf jeden Webspace geladen werden.

| Datei | Inhalt |
| --- | --- |
| `index.html` | Startseite: Willkommen (Foto, Leitsatz, „Speisekarte entdecken“, „Tisch reservieren“), Das Stimbeck, Unsere Küche, Der Mensch hinter dem Stimbeck, Gemeinsam feiern, Besuch uns mit Adresse, Telefon, Öffnungszeiten und Anfahrtskarte (`#anfahrt`) |
| `kueche.html` | Unsere Küche |
| `speisekarte.html` | Speisekarte zum Blättern (Seitenbilder) und Herbstkarte |
| `ueber-uns.html` | Über uns mit „Der Mensch hinter dem Stimbeck“ (`#inhaber`, Ziel von „Meine Geschichte“) |
| `feiern.html` | Feiern & Catering mit Anfrage per Telefon/E-Mail |
| `veranstaltungen.html` | Termine und Aktionen; neue Termine als `<article class="event">` eintragen, alte löschen |
| `kontakt.html` | Kontakt & Reservierung (`#reservierung`), Öffnungszeiten (`#zeiten`), Anfahrt mit Karte (`#anfahrt`). Öffnungszeiten stehen auch auf der Startseite und in `site.js`; bei Änderungen alle drei Stellen anpassen. |
| `impressum.html` | Impressum |
| `datenschutz.html` | Datenschutzhinweise für die öffentliche Homepage (Entwurf) |
| `style.css` | Gestaltung; Breakpoints Handy (Basis), ab 640 px Tablet, ab 1024 px Laptop, ab 1200 px volle Navigation, ab 1440 px Desktop |
| `site.js` | Handy-Menü, Kopfzeile beim Scrollen, Fenster „Tisch reservieren“, Öffnungsstatus „Jetzt geöffnet“ (deutsche Zeit), heutiger Tag, Einblenden, Speisekarten-Ansicht mit Großansicht |
| `karte/` | Erzeugte Seitenbilder und `karten.js`; nicht von Hand bearbeiten |
| `img/anfahrt-karte.jpg` | Kartenausschnitt aus OpenStreetMap (Zoom 14, 1600 × 900 px), Standort bei 60 % / 40 % |

Texte der Startseite, von „Über uns“ und „Feiern & Catering“ stammen vom Inhaber (29.10.2026). Die Anfrage-Box auf `feiern.html` und die Seite `veranstaltungen.html` sind eigene Formulierungen und mit ihm abzustimmen.

Kopf- und Fußzeile stehen in jeder HTML-Datei einzeln (kein Build-Schritt). Bei Änderungen an Menü, Kontaktdaten oder Fußzeile alle Seiten anpassen.

`style.css` und `site.js` sind mit einer Version eingebunden (`style.css?v=2026-10-29`), damit Browser nach einem Upload die neue Fassung laden. Die Version setzt `scripts/homepage-version.sh` automatisch (Format `JJJJ-MM-TT-HHMM`) und committet sie als „Homepage-Version …“, aber nur, wenn sich in `homepage/` seit der letzten Version etwas geändert hat:

- **Vor dem Push auf main** (Hook `scripts/git-hooks/pre-push`): Der Hook committet die neue Version und bricht den Push ab, weil Git die Commits schon festgelegt hat. Einfach `git push` noch einmal ausführen.
- **Nach einem Merge auf main**, auch bei `git pull` (Hook `scripts/git-hooks/post-merge`).
- Einmalig pro Klon einschalten: `git config core.hooksPath scripts/git-hooks`. Merges über die GitHub-Oberfläche lösen keine Hooks aus; dann vor dem Upload `sh scripts/homepage-version.sh` von Hand ausführen.

## Tisch reservieren

Es gibt kein Buchungssystem. Der Knopf „Tisch reservieren“ steht auf jeder Seite oben in der Kopfzeile (Handy: neben dem Menü-Knopf). Er öffnet ein kleines Fenster mit „Anrufen“ (`tel:`) und „E-Mail schreiben“ (`mailto:` mit Betreff „Tischreservierung“ und Vorlage für Datum, Uhrzeit, Personen). Ohne JavaScript führt er zu `kontakt.html#reservierung`. Telefonnummer und E-Mail-Adresse für das Fenster stehen oben in `site.js` (`RESERVE_PHONE`, `RESERVE_MAIL`).

Ab 1200 px Breite ist die Navigation ausgeschrieben; „Startseite“ steckt dort im Logo und „Kontakt & Reservierung“ heißt kurz „Kontakt“. Darunter öffnet der Menü-Knopf die vollständige Liste.

Lokal ansehen:

```sh
python3 -m http.server 8090 --bind 127.0.0.1 --directory homepage
```

Dann http://127.0.0.1:8090/ öffnen. Die Seiten funktionieren auch direkt über `file://`.

## Speisekarte austauschen

Die Speisekarte wird auf `speisekarte.html` direkt angezeigt, nicht als Download. Dafür werden die Karten in Seitenbilder umgewandelt (eine PDF-Seite = ein Bild). Die Original-PDF liegt außerhalb des Webordners in `speisekarte/` und wird nicht veröffentlicht.

Neue Karte einpflegen (nur auf dem Mac, aus dem Projektverzeichnis):

```sh
swift scripts/homepage-karte.swift "Speisekarte=speisekarte/Zum Stimbeck - Speisekarte.pdf"
```

- Jedes Argument ist `Titel=Datei`, erlaubt sind PDF, PNG und JPG. Mehrere Karten werden als Reiter angezeigt, z. B. zusätzlich `"Herbstkarte=speisekarte/herbstkarte.png"`.
- Gleicher Titel mehrfach hängt weitere Seiten an dieselbe Karte an (z. B. zwei fotografierte Seiten).
- Jeder Aufruf erzeugt `homepage/karte/` komplett neu: Seitenbilder (JPG, 1400 px breit) und `karten.js`. Danach den Ordner `homepage/` hochladen.
- Der erste Lauf dauert gut eine Minute, weil Swift das Skript übersetzt.
- Auf der Seite: Wischen oder Pfeile zum Blättern, Seitenzahlen zum Springen, Antippen öffnet die Großansicht mit Blättern. Es gibt keinen Download-Link; Rechtsklick und langes Tippen auf die Bilder sind gesperrt. Ganz verhindern lässt sich das Speichern von Bildern im Web nicht (z. B. per Bildschirmfoto).
- Die Herbstkarte auf `speisekarte.html` (`#herbst`) steht fest im HTML und stammt aus `api/speisekarte.php`. Nach dem 22.11.2026 den Abschnitt entfernen und den Eintrag „Herbstkarte“ auf `veranstaltungen.html` löschen.

## Karte in der Anfahrt

Die Karte ist ein fest gespeichertes Bild aus OpenStreetMap, keine eingebettete Google-Karte. Die Seite lädt dadurch nichts von Google oder OpenStreetMap. Erst ein Klick auf die Karte öffnet Google Maps in einem neuen Tab (Suche nach „Zum Stimbeck, Am Lütten Stimbeck 4, 29646 Bispingen“). Der Hinweis „Kartendaten © OpenStreetMap-Mitwirkende“ unter der Karte ist Pflicht und muss stehen bleiben.

- Das Bild zeigt am Handy und am Laptop jeweils einen Ausschnitt in Originalgröße um den Standort (`object-fit: none`), damit Straßennamen lesbar bleiben. Der Pin sitzt per CSS bei 60 % / 40 %, genau dort liegt die Adresse im Bild (Koordinaten laut OpenStreetMap: 53.10524, 10.05680).
- Ein neues Bild muss den Standort wieder bei 60 % / 40 % haben, sonst `.map-marker` und `object-position` in `style.css` anpassen.

## Vor der Veröffentlichung

- Kontaktdaten und Öffnungszeiten stammen vom Eintrag auf bispingen.de (abgerufen 24.10.2026) und sind mit dem Betreiber abzugleichen. Die Öffnungszeiten stehen auch in `site.js` (`OPEN_DAYS`, `OPEN_FROM`, `OPEN_TO`).
- Rechtliche Entwürfe und offene Punkte: siehe `../docs/RECHTLICHE-ANGABEN.md`. Insbesondere Restaurant-IDs/Register, mögliche Teilnahmeverpflichtung an Streitbeilegung und konkrete E-Mail-Konfiguration klären.
- Datenschutzerklärung ist als Entwurf vorhanden und im Footer aller Seiten verlinkt. Die beschriebenen Verarbeitungen/Löschabläufe bestätigen, bevor die Entwurfskennzeichnung entfernt wird.
- Die Anfahrtsbeschreibung ist aus der Karte abgelesen (A7, Anschlussstelle 43 Bispingen, über Hützel und K 4). Mit Ortskenntnis prüfen.
- Nutzungsrechte am eingebundenen Gastraumfoto, Logo und Speisekartenmaterial bestätigen.
