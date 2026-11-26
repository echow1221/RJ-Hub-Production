#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname -- "${BASH_SOURCE[0]}")/.."
umask 077
mkdir -p backups
destination="$(mktemp -d "backups/rjhub-$(date -u +%Y%m%dT%H%M%SZ)-XXXXXX")"
trap 'rm -f -- "$destination/wordpress.sql.tmp" "$destination/uploads.tar.gz.tmp"' EXIT

docker compose exec -T db sh -c '
  export MYSQL_PWD="$MARIADB_PASSWORD"
  exec mariadb-dump --single-transaction --no-tablespaces -u "$MARIADB_USER" "$MARIADB_DATABASE"
' > "$destination/wordpress.sql.tmp"
docker compose exec -T wordpress sh -c '
  cd /var/www/html/wp-content
  mkdir -p uploads
  exec tar -czf - uploads
' > "$destination/uploads.tar.gz.tmp"
test -s "$destination/wordpress.sql.tmp"
tar -tzf "$destination/uploads.tar.gz.tmp" >/dev/null
mv "$destination/wordpress.sql.tmp" "$destination/wordpress.sql"
mv "$destination/uploads.tar.gz.tmp" "$destination/uploads.tar.gz"
printf 'Private database and uploads backup: %s\n' "$destination"
