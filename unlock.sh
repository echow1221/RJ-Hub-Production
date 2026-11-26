#!/usr/bin/env bash
set -euo pipefail
umask 077

cd "$(dirname "${BASH_SOURCE[0]}")"
destination="$PWD/RJ_Hub_8092_Complete.zip"
if [[ -e "$destination" || -L "$destination" ]]; then
  printf 'A ZIP already exists in this folder. Move it aside if you want to unlock a new copy.\n' >&2
  exit 1
fi

read -r -s -p 'Paste the unlock key from the Codex chat and press Return: ' key
printf '\n'
if [[ ! "$key" =~ ^[0-9a-fA-F]{64}$ ]]; then
  printf 'The unlock key needs exactly 64 hexadecimal characters.\n' >&2
  exit 1
fi

temp="$(mktemp -d "$PWD/.unlock.XXXXXX")"
trap 'rm -rf -- "$temp"' EXIT
openssl enc -d -aes-256-cbc -nosalt -K "$key" -iv 03370aa37917b609a3d0486c5951ce8b \
  -in RJ_Hub_8092_Complete.zip.enc -out "$temp/RJ_Hub_8092_Complete.zip"
unset key
(
  cd "$temp"
  printf '%s\n' 'd7fe2cef802097658c168464048d53b63735050388298a268aa3e6b9669eec46  RJ_Hub_8092_Complete.zip' | shasum -a 256 -c
)
mv "$temp/RJ_Hub_8092_Complete.zip" "$destination"
printf 'Your complete ZIP is ready at %s\n' "$destination"
if [[ "$(uname -s)" == Darwin ]]; then
  open -R "$destination"
fi
