#!/bin/sh
# Erstellt nur ein lokales Upload-Paket, verändert keinen Server.
set -eu
cd "$(dirname "$0")/.."
release="$(date -u +%Y%m%dT%H%M%SZ)-$(git rev-parse --short HEAD)"
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then release="$release-worktree"; fi
out="build/releases/$release"
mkdir -p "$out"
docker build --target release --output "type=local,dest=$out" .
printf '%s\n' "$release" > "$out/RELEASE"
COPYFILE_DISABLE=1 tar -czf "build/$release.tar.gz" -C "$out" .
printf '\nLokales Upload-Paket: build/%s.tar.gz\nKein Upload ausgeführt.\n' "$release"
