#!/usr/bin/env bash
set -euo pipefail

mode=${1:-}
if [[ $# -gt 1 || ( -n "$mode" && "$mode" != "--check" ) ]]; then
  printf 'Usage: bash scripts/update-original-copy.sh [--check]\n' >&2
  exit 2
fi

update_dir="$(cd "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
export COMPOSE_PROJECT_NAME=rjhuboriginal RJHUB_PORT=8092
container="$(docker ps --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME" \
  --filter label=com.docker.compose.service=wordpress --format '{{.ID}}')"
if [[ -z "$container" || "$container" == *$'\n'* ]] || \
  [[ "$(docker port "$container" 80/tcp)" != "127.0.0.1:$RJHUB_PORT" ]]; then
  printf 'Open Docker Desktop and start your original RJ Hub site at http://127.0.0.1:%s/ first.\n' "$RJHUB_PORT" >&2
  exit 1
fi

site_dir="$(docker inspect --format '{{index .Config.Labels "com.docker.compose.project.working_dir"}}' "$container")"
if [[ ! -f "$site_dir/compose.yaml" || ! -f "$site_dir/scripts/backup.sh" ]]; then
  printf 'The original WordPress project folder could not be opened.\n' >&2
  exit 1
fi
cd "$site_dir"

update_wording=(
  docker compose run --rm --no-deps -T
  --volume "$update_dir/scripts/apply-original-copy.php:/rjhub-copy-update.php:ro"
  --volume "$update_dir/content/original-copy-update.json:/rjhub-copy-update.json:ro"
  cli wp eval-file /rjhub-copy-update.php /rjhub-copy-update.json
)
"${update_wording[@]}" check

if [[ "$mode" == "--check" ]]; then
  exit
fi

bash scripts/backup.sh
"${update_wording[@]}" apply

umask 077
code_backup=''
update_file=''
trap 'if [[ -n "$update_file" ]]; then rm -f "$update_file"; fi' EXIT
while read -r original_hash relative_path; do
  revised_file="$update_dir/$relative_path"
  if [[ ! -f "$relative_path" || -L "$relative_path" ]]; then
    printf 'Kept %s as-is because the original file could not be identified.\n' "$relative_path"
    continue
  fi
  if cmp -s "$relative_path" "$revised_file"; then
    continue
  fi
  if command -v sha256sum >/dev/null; then
    current_hash="$(sha256sum "$relative_path")"
  else
    current_hash="$(shasum -a 256 "$relative_path")"
  fi
  if [[ "${current_hash%% *}" != "$original_hash" ]]; then
    printf 'Kept your edits to %s.\n' "$relative_path"
    continue
  fi

  if [[ -z "$code_backup" ]]; then
    code_backup="$(mktemp -d backups/rjhub-copy-code-XXXXXX)"
  fi
  mkdir -p "$code_backup/$(dirname "$relative_path")"
  cp -p "$relative_path" "$code_backup/$relative_path"
  update_file="$(mktemp "$relative_path.rjhub-update-XXXXXX")"
  cp -p "$revised_file" "$update_file"
  mv -f "$update_file" "$relative_path"
  update_file=''
done < "$update_dir/scripts/original-copy-files.sha256"

if [[ -n "$code_backup" ]]; then
  printf 'Previous archive, map, and intake wording saved in %s.\n' "$code_backup"
fi

printf 'Refresh the original RJ Hub site at http://127.0.0.1:%s/.\n' "$RJHUB_PORT"
