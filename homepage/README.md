# Homepage Zum Stimbeck

Statische Landingpage ohne Build-Schritt und ohne externe Dienste (keine Webfonts, keine eingebettete Karte, keine Cookies). Der Ordner kann so, wie er ist, auf jeden Webspace geladen werden.

| Datei | Inhalt |
| --- | --- |
| `index.html` | Startseite: Hero-Slider, Das Haus, Hinweis auf die Speisekarte, Öffnungszeiten, Kontakt, Anfahrt |
| `speisekarte.html` | Speisekarte zum Blättern (Seitenbilder) und Herbstkarte |
| `impressum.html` | Impressum (Entwurf) |
| `style.css` | Gestaltung; Breakpoints Handy (Basis), ab 640 px Tablet, ab 1024 px Laptop, ab 1440 px Desktop |
| `site.js` | Handy-Menü, Kopfzeile beim Scrollen, Öffnungsstatus „Jetzt geöffnet“ (deutsche Zeit), heutiger Tag, Einblenden, Speisekarten-Ansicht mit Großansicht |
| `slider.js` | Slider: 7 s Autoplay mit Fortschrittsbalken, Pfeile, Punkte, Pfeiltasten, Wischen; pausiert bei Maus/Fokus und bei reduzierter Bewegung |
| `karte/` | Erzeugte Seitenbilder und `karten.js`; nicht von Hand bearbeiten |

Der Hero zeigt eine gezeichnete Heidelandschaft (SVG). Jede Folie hat eine eigene Stimmung (Abend, Herbst, Nacht), die über `data-mood` und die Farbvariablen in `style.css` gesteuert wird.

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
- Die Herbstkarte auf `speisekarte.html` (`#herbst`) steht fest im HTML und stammt aus `api/speisekarte.php`. Nach dem 22.11.2026 den Abschnitt entfernen und die zweite Slider-Folie sowie den Hinweistext auf der Startseite anpassen.

## Vor der Veröffentlichung

- Kontaktdaten und Öffnungszeiten stammen vom Eintrag auf bispingen.de (abgerufen 24.10.2026) und sind mit dem Betreiber abzugleichen. Die Öffnungszeiten stehen auch in `site.js` (`OPEN_DAYS`, `OPEN_FROM`, `OPEN_TO`).
- Im Impressum die gelb markierten Stellen klären: Inhaber und Rechtsform, Umsatzsteuer-ID, inhaltlich Verantwortlicher.
- Eine Datenschutzerklärung fehlt noch. Sie ist auch ohne Cookies nötig (Server-Logs beim Hoster) und sollte vom Betreiber bzw. mit einem Generator erstellt werden.
- Die Lageskizze ist schematisch (Lage laut OpenStreetMap: etwa 4 km nordöstlich vom Ortskern Bispingen). Die Anfahrtsbeschreibung mit Ortskenntnis präzisieren.
- Auf der Seite sind keine Fotos. Wer echte Bilder möchte, kann im Hero hinter der Landschaft ein Foto ergänzen.
