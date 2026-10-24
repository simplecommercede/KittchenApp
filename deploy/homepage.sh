#!/bin/sh
# Lädt die statische Homepage (Ordner homepage/) per rsync über SSH hoch.
#
#   sh deploy/homepage.sh          Probelauf: zeigt nur, welche Dateien übertragen würden
#   sh deploy/homepage.sh --live   überträgt wirklich
#
# Ziel kommt aus config/upload.ini (SSH_HOST, SSH_PORT, SSH_USER, HOMEPAGE_DOCUMENT_ROOT).
# Es wird nichts auf dem Server gelöscht: app/, .htaccess, .htpasswd und andere Dateien bleiben unberührt.
# Das SSH-Passwort fragt ssh selbst ab; es steht nirgends im Skript oder in der Kommandozeile.
set -eu
cd "$(dirname "$0")/.."

ini=config/upload.ini
[ -f "$ini" ] || { echo "Fehlt: $ini (Vorlage: config/upload.ini.example)" >&2; exit 1; }
get() { sed -n "s/^$1[[:space:]]*=[[:space:]]*\"\{0,1\}\([^\"]*\)\"\{0,1\}[[:space:]]*$/\1/p" "$ini" | head -n 1; }

host=$(get SSH_HOST); port=$(get SSH_PORT); user=$(get SSH_USER); target=$(get HOMEPAGE_DOCUMENT_ROOT)
for v in host port user target; do
  eval "val=\$$v"
  case "$val" in ""|EINTRAGEN) echo "In $ini fehlt ein Wert für $v (HOMEPAGE_DOCUMENT_ROOT bzw. SSH_*)." >&2; exit 1;; esac
done
case "$target" in /*) ;; *) echo "HOMEPAGE_DOCUMENT_ROOT muss ein absoluter Pfad sein: $target" >&2; exit 1;; esac

mode="--dry-run"
[ "${1:-}" = "--live" ] && mode=""

echo "Ziel: $user@$host:$target (Port $port)"
[ -n "$mode" ] && echo "Probelauf, es wird nichts übertragen. Zum Hochladen: sh deploy/homepage.sh --live"

# Ohne --delete: vorhandene Dateien auf dem Server werden nur ersetzt, nie entfernt.
# Ohne -p: neue Dateien bekommen die Standardrechte des Servers (lesbar für den Webserver).
# Bewusst nur Optionen, die auch das openrsync von macOS kennt.
rsync -rltvz $mode \
  --exclude 'README.md' --exclude '.DS_Store' \
  -e "ssh -p $port" \
  homepage/ "$user@$host:$target/"

[ -n "$mode" ] || echo "Homepage hochgeladen nach $target"
