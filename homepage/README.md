# Homepage Zum Stimbeck

Statische Landingpage ohne Build-Schritt und ohne externe Dienste (keine Schriften, keine eingebettete Karte, keine Cookies). Der Ordner kann so, wie er ist, auf jeden Webspace geladen werden.

| Datei | Inhalt |
| --- | --- |
| `index.html` | Hero-Slider, Öffnungszeiten, Adresse, Reservierung, Anfahrt |
| `impressum.html` | Impressum (Entwurf) |
| `style.css` | Gestaltung (Cremeweiß, Dunkelgrün, Bernstein wie in der App) |
| `slider.js` | Slider: Autoplay alle 6 s, Pfeile, Punkte, Pfeiltasten, Wischen; pausiert bei Maus/Fokus und bei reduzierter Bewegung |

Lokal ansehen:

```sh
python3 -m http.server 8090 --bind 127.0.0.1 --directory homepage
```

Dann http://127.0.0.1:8090/ öffnen.

## Vor der Veröffentlichung

- Kontaktdaten und Öffnungszeiten stammen vom Eintrag auf bispingen.de (abgerufen 24.10.2026) und sind mit dem Betreiber abzugleichen.
- Im Impressum die gelb markierten Stellen klären: Inhaber und Rechtsform, Umsatzsteuer-ID, inhaltlich Verantwortlicher.
- Eine Datenschutzerklärung fehlt noch. Sie ist auch ohne Cookies nötig (Server-Logs beim Hoster) und sollte vom Betreiber bzw. mit einem Generator erstellt werden.
- Die Slider-Hintergründe sind Farbverläufe. Eigene Fotos als `img/slide-1.jpg` usw. ablegen und in `style.css` eintragen, z. B. `.slide-1 { --slide-bg: url(img/slide-1.jpg); }`.
- Die Anfahrtsbeschreibung ist allgemein gehalten; mit Ortskenntnis präzisieren.
