# Homepage Zum Stimbeck

Statische Landingpage ohne Build-Schritt und ohne externe Dienste (keine Webfonts, keine eingebettete Karte, keine Cookies). Der Ordner kann so, wie er ist, auf jeden Webspace geladen werden.

| Datei | Inhalt |
| --- | --- |
| `index.html` | Hero-Slider, Das Haus, Speisekarte (Auszug + Dateien), Öffnungszeiten, Kontakt, Anfahrt |
| `impressum.html` | Impressum (Entwurf) |
| `style.css` | Gestaltung; Breakpoints Handy (Basis), ab 640 px Tablet, ab 1024 px Laptop, ab 1440 px Desktop |
| `site.js` | Handy-Menü, Kopfzeile beim Scrollen, Öffnungsstatus „Jetzt geöffnet“ (deutsche Zeit), heutiger Tag, Einblenden, Karten-Dateien, Großansicht |
| `slider.js` | Slider: 7 s Autoplay mit Fortschrittsbalken, Pfeile, Punkte, Pfeiltasten, Wischen; pausiert bei Maus/Fokus und bei reduzierter Bewegung |
| `karte/` | Speisekarte und weitere Karten als PDF oder Bild |

Der Hero zeigt eine gezeichnete Heidelandschaft (SVG). Jede Folie hat eine eigene Stimmung (Abend, Herbst, Nacht), die über `data-mood` und die Farbvariablen in `style.css` gesteuert wird.

Lokal ansehen:

```sh
python3 -m http.server 8090 --bind 127.0.0.1 --directory homepage
```

Dann http://127.0.0.1:8090/ öffnen. Über `file://` funktioniert die Seite auch, nur die automatische Erkennung der Karten-Dateien nicht.

## Speisekarte austauschen

Die Karten liegen in `karte/`. Jeder Eintrag im Abschnitt „Komplette Karten ansehen“ sucht der Reihe nach `<name>.pdf`, `<name>.png`, `<name>.jpg` und nimmt die erste vorhandene Datei:

| Eintrag | Dateiname | ohne Datei |
| --- | --- | --- |
| Speisen- und Getränkekarte | `karte/speisekarte.pdf` / `.png` / `.jpg` | Link auf die PDF bleibt stehen |
| Herbstkarte | `karte/herbstkarte.pdf` / `.png` / `.jpg` | Eintrag wird ausgeblendet |

- **PDF** öffnet sich in einem neuen Tab, **PNG/JPG** in einer Großansicht mit „Herunterladen“. Dateityp und Größe erscheinen automatisch.
- Neue Karte: Datei mit gleichem Namen ersetzen, fertig. Bei PNG/JPG die alte PDF löschen, sonst gewinnt die PDF.
- `karte/speisekarte-vorschau.png` ist das Vorschaubild der PDF-Kachel. Neu erzeugen auf dem Mac: `sips -s format png -Z 240 karte/speisekarte.pdf --out karte/speisekarte-vorschau.png`
- Weitere Karte (z. B. Weihnachtskarte): in `index.html` einen `menu-file`-Block kopieren, Titel und `data-files` anpassen, `data-optional` behalten.
- Die aktuelle PDF ist 6,2 MB groß. Für Handys im Mobilfunk lohnt es sich, sie vor dem Hochladen zu verkleinern (z. B. in Vorschau: Exportieren → Quartz-Filter „Reduce File Size“).
- Die Gerichte im Auszug auf der Seite (Herbstkarte, Klassiker, Burger) stehen fest in `index.html` und stammen aus `api/speisekarte.php`. Preisänderungen dort mitpflegen; nach dem 22.11.2026 den Herbstkarten-Block austauschen oder entfernen. Auch der zweite Slider zur Herbstkarte muss dann geändert werden.

## Vor der Veröffentlichung

- Kontaktdaten und Öffnungszeiten stammen vom Eintrag auf bispingen.de (abgerufen 24.10.2026) und sind mit dem Betreiber abzugleichen. Die Öffnungszeiten stehen auch in `site.js` (`OPEN_DAYS`, `OPEN_FROM`, `OPEN_TO`).
- Im Impressum die gelb markierten Stellen klären: Inhaber und Rechtsform, Umsatzsteuer-ID, inhaltlich Verantwortlicher.
- Eine Datenschutzerklärung fehlt noch. Sie ist auch ohne Cookies nötig (Server-Logs beim Hoster) und sollte vom Betreiber bzw. mit einem Generator erstellt werden.
- Die Lageskizze ist schematisch (Lage laut OpenStreetMap: etwa 4 km nordöstlich vom Ortskern Bispingen). Die Anfahrtsbeschreibung mit Ortskenntnis präzisieren.
- Auf der Seite sind keine Fotos. Wer echte Bilder möchte, kann im Hero hinter der Landschaft ein Foto ergänzen.
