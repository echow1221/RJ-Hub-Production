#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
destination="${1:-$project_root/backups}"
media_mode="${2:-}"
if [[ $# -gt 2 || ( -n "$media_mode" && "$media_mode" != "--private-media" ) ]]; then
  printf 'Usage: bash scripts/package.sh [output-folder] [--private-media]\n' >&2
  exit 2
fi
umask 077
mkdir -p "$destination"
destination="$(cd "$destination" && pwd)"
name=RJ_Hub_WordPress_Local_Prototype
archive="$name.zip"
staging="$(mktemp -d)"
trap 'rm -rf -- "$staging"' EXIT

mkdir "$staging/$name"
cd "$project_root"
cp -R .env.example .gitignore README.md compose.yaml content docs local scripts tests wp-content "$staging/$name/"
mkdir -p "$staging/$name/media"
cp media/{INVENTORY.csv,MANIFEST.json,REVIEW.json,build-preview.py} "$staging/$name/media/"
if [[ "$media_mode" == "--private-media" ]]; then
  archive=RJ_Hub_WordPress_Private_Photo_Review.zip
  mkdir "$staging/$name/media/preview"
  cp media/preview/{archival-gathering.webp,circle-dialogue.webp,facilitation-circle.webp,rj-hub-mark.png,workshop-activity.webp} "$staging/$name/media/preview/"
fi
find "$staging/$name" -type d -exec chmod 755 {} +
find "$staging/$name" -type f -exec chmod a+r {} +
(
  cd "$staging"
  zip -qr "$staging/$archive" "$name"
  unzip -tq "$staging/$archive"
)
mv -- "$staging/$archive" "$destination/"
printf '%s\n' "$destination/$archive"
