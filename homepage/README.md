# Homepage Zum Stimbeck

Statische Landingpage ohne Build-Schritt und ohne externe Dienste (keine Webfonts, keine eingebettete Google-Karte, keine Cookies). Der Ordner kann so, wie er ist, auf jeden Webspace geladen werden.

| Datei | Inhalt |
| --- | --- |
| `index.html` | Startseite: Willkommensbereich, Das Haus, Hinweis auf die Speisekarte, Öffnungszeiten, Kontakt, Anfahrt mit Karte |
| `speisekarte.html` | Speisekarte zum Blättern (Seitenbilder) und Herbstkarte |
| `impressum.html` | Impressum (Entwurf) |
| `style.css` | Gestaltung; Breakpoints Handy (Basis), ab 640 px Tablet, ab 1024 px Laptop, ab 1440 px Desktop |
| `site.js` | Handy-Menü, Kopfzeile beim Scrollen, Öffnungsstatus „Jetzt geöffnet“ (deutsche Zeit), heutiger Tag, Einblenden, Speisekarten-Ansicht mit Großansicht |
| `karte/` | Erzeugte Seitenbilder und `karten.js`; nicht von Hand bearbeiten |
| `img/anfahrt-karte.jpg` | Kartenausschnitt aus OpenStreetMap (Zoom 14, 1600 × 900 px), Standort bei 60 % / 40 % |

Der Willkommensbereich zeigt das Foto `img/hero.webp` (Gastraum mit Logo, 1672 × 941 px), dazu `img/hero.jpg` und `img/hero-900.jpg` für Browser ohne WebP und für Handys. Weil Logo und Name im Foto stehen, liegt kein Text darauf; die Überschrift ist nur für Suchmaschinen und Screenreader im Code.

- Handy (unter 640 px): Ausschnitt 4:3 unter der Kopfzeile, Text und Knöpfe darunter.
- Tablet (640–1023 px): Foto vollständig, Text darunter.
- Laptop und Desktop (ab 1024 px): Foto füllt den Bildschirm unter der Kopfzeile, Text und Knöpfe unten auf einem dunklen Verlauf.
- Neues Foto: alle drei Dateien ersetzen (`sips -s format jpeg -s formatOptions 82 neu.webp --out img/hero.jpg` und mit `-Z 900` für `hero-900.jpg`). Liegt das Logo woanders als oben, `--hero-focus` unter `.hero` in `style.css` anpassen (z. B. `50% 42%` für mittig). Für große Bildschirme wäre eine Vorlage ab 1920 px Breite schärfer.

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
- Die Herbstkarte auf `speisekarte.html` (`#herbst`) steht fest im HTML und stammt aus `api/speisekarte.php`. Nach dem 22.11.2026 den Abschnitt entfernen und den Hinweistext „Schnitzel, Burger & Herbstkarte“ auf der Startseite anpassen.

## Karte in der Anfahrt

Die Karte ist ein fest gespeichertes Bild aus OpenStreetMap, keine eingebettete Google-Karte. Die Seite lädt dadurch nichts von Google oder OpenStreetMap. Erst ein Klick auf die Karte öffnet Google Maps in einem neuen Tab (Suche nach „Zum Stimbeck, Am Lütten Stimbeck 4, 29646 Bispingen“). Der Hinweis „Kartendaten © OpenStreetMap-Mitwirkende“ unter der Karte ist Pflicht und muss stehen bleiben.

- Das Bild zeigt am Handy und am Laptop jeweils einen Ausschnitt in Originalgröße um den Standort (`object-fit: none`), damit Straßennamen lesbar bleiben. Der Pin sitzt per CSS bei 60 % / 40 %, genau dort liegt die Adresse im Bild (Koordinaten laut OpenStreetMap: 53.10524, 10.05680).
- Ein neues Bild muss den Standort wieder bei 60 % / 40 % haben, sonst `.map-marker` und `object-position` in `style.css` anpassen.

## Vor der Veröffentlichung

- Kontaktdaten und Öffnungszeiten stammen vom Eintrag auf bispingen.de (abgerufen 24.10.2026) und sind mit dem Betreiber abzugleichen. Die Öffnungszeiten stehen auch in `site.js` (`OPEN_DAYS`, `OPEN_FROM`, `OPEN_TO`).
- Im Impressum die gelb markierten Stellen klären: Inhaber und Rechtsform, Umsatzsteuer-ID, inhaltlich Verantwortlicher.
- Eine Datenschutzerklärung fehlt noch. Sie ist auch ohne Cookies nötig (Server-Logs beim Hoster) und sollte vom Betreiber bzw. mit einem Generator erstellt werden.
- Die Anfahrtsbeschreibung ist aus der Karte abgelesen (A7, Anschlussstelle 43 Bispingen, über Hützel und K 4). Mit Ortskenntnis prüfen.
- Auf der Seite sind keine Fotos. Wer echte Bilder möchte, kann im Hero hinter der Landschaft ein Foto ergänzen.
