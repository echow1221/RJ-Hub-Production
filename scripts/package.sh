#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
destination="${1:-$project_root/backups}"
mkdir -p "$destination"
destination="$(cd "$destination" && pwd)"
name=RJ_Hub_WordPress_Local_Prototype
staging="$(mktemp -d)"
trap 'rm -rf -- "$staging"' EXIT

mkdir "$staging/$name"
cd "$project_root"
cp -R .env.example .gitignore README.md compose.yaml content docs local media scripts tests wp-content "$staging/$name/"
find "$staging/$name" -type d -exec chmod 755 {} +
find "$staging/$name" -type f -exec chmod a+r {} +
(
  cd "$staging"
  zip -qr "$staging/$name.zip" "$name"
  unzip -tq "$staging/$name.zip"
)
mv -- "$staging/$name.zip" "$destination/"
printf '%s\n' "$destination/$name.zip"
