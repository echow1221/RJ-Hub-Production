#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname -- "${BASH_SOURCE[0]}")/.."

media_mode=${1:-}
if [[ $# -gt 1 || ( -n "$media_mode" && "$media_mode" != "--private-media" ) ]]; then
  printf 'Usage: bash scripts/setup.sh [--private-media]\n' >&2
  exit 2
fi

docker compose version >/dev/null
umask 077
mkdir -p .local
if [[ ! -f .env ]]; then
  printf 'RJHUB_PORT=8080\nRJHUB_DB_PASSWORD=%s\nRJHUB_DB_ROOT_PASSWORD=%s\n' \
    "$(openssl rand -hex 24)" "$(openssl rand -hex 24)" > .env
fi

docker compose up --detach --wait --wait-timeout 180 db wordpress
port="$(docker compose port wordpress 80)"
url="http://$port"

wp() {
  bash scripts/wp "$@"
}

if ! wp --url="$url" core is-installed; then
  if [[ ! -s .local/admin-password ]]; then
    openssl rand -hex 20 > .local/admin-password
  fi
  docker compose run --rm --no-deps -T cli sh -c '
    IFS= read -r password
    exec wp core install "$@" --admin_password="$password"
  ' setup --url="$url" --title='Restorative Justice Hub' --admin_user=rjhub-owner \
    --admin_email=owner@example.invalid --skip-email < .local/admin-password
  wp post delete 1 2 3 --force
  wp option update default_comment_status closed
  wp option update default_ping_status closed
  wp option update blog_public 0
  wp option update timezone_string America/Los_Angeles
fi

if ! wp plugin is-active rj-hub-core; then
  wp plugin activate rj-hub-core
fi
if ! wp theme is-active rj-hub; then
  wp theme activate rj-hub
fi

seed=(eval-file /workspace/project/scripts/seed.php /workspace/project)
if [[ "$media_mode" == "--private-media" ]]; then
  seed+=(private-media)
fi
wp "${seed[@]}"
wp rewrite structure '/%postname%/'
curl --fail --silent --show-error --max-time 15 --noproxy '*' "$url/" >/dev/null
printf 'Preview: %s/\nWordPress dashboard: %s/wp-admin/\n' "$url" "$url"
if [[ -s .local/admin-password ]]; then
  printf 'Local administrator: rjhub-owner\nInitial password file: .local/admin-password\n'
fi
