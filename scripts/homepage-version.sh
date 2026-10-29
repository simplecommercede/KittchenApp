#!/bin/sh
# Setzt eine neue Version an style.css und site.js in allen Homepage-Seiten
# (?v=JJJJ-MM-TT-HHMM, bei Bedarf mit Zähler) und committet sie direkt.
# Wird von den Git-Hooks vor dem Push und nach dem Merge auf main aufgerufen.
#
# Exit-Code 0: neue Version committet · 3: nichts zu tun · sonst Fehler
set -e

cd "$(git rev-parse --show-toplevel)"

# Nur wenn sich die Homepage seit der letzten Version geändert hat
last=$(git log -1 --format=%H --grep='^Homepage-Version ' HEAD)
if [ -n "$last" ] && git diff --quiet "$last" HEAD -- homepage; then
  exit 3
fi

# Offene Änderungen nicht ungefragt mit committen
if ! git diff --quiet HEAD -- homepage/*.html; then
  echo "homepage-version: homepage/*.html hat nicht committete Änderungen. Bitte erst committen." >&2
  exit 1
fi
if ! git diff --cached --quiet; then
  echo "homepage-version: Es sind schon Änderungen vorgemerkt (git add). Bitte erst committen." >&2
  exit 1
fi

version=$(date +%Y-%m-%d-%H%M)
# Zwei Versionen in derselben Minute: Zähler anhängen
current=$(grep -o 'style\.css?v=[0-9-]*' homepage/index.html | head -n 1 | cut -d= -f2)
n=2
base=$version
while [ "$current" = "$version" ] || git log -1 --format=%H --grep="^Homepage-Version $version\$" HEAD | grep -q .; do
  version="$base-$n"
  n=$((n + 1))
done
perl -pi -e 's/(\b(?:style\.css|site\.js)\?v=)[0-9-]+/${1}'"$version"'/g' homepage/*.html

if git diff --quiet -- homepage/*.html; then
  echo "homepage-version: keine ?v=-Links gefunden, nichts geändert." >&2
  exit 1
fi

# Commit über Plumbing-Befehle: funktioniert auch im post-merge-Hook, wo
# „git commit“ den Merge noch für unfertig hält
git add -- homepage/*.html
commit=$(git commit-tree "$(git write-tree)" -p HEAD -m "Homepage-Version $version")
git update-ref -m "homepage-version: $version" HEAD "$commit"
echo "homepage-version: neue Version $version committet."
