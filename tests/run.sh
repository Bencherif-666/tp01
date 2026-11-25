#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/opt/lampp/bin/php}"
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
DB_HOST="${TP_BN_DB_HOST:-localhost}"
DB_PORT="${TP_BN_DB_PORT:-3306}"
DB_USER="${TP_BN_DB_USER:-root}"
DB_PASSWORD="${TP_BN_DB_PASSWORD:-}"
TEST_DB="tp_bn_test_$(date +%Y%m%d%H%M%S)_$$"
CREATED_DB=0

if [[ ! -x "$PHP_BIN" ]]; then
    echo "PHP CLI not found: $PHP_BIN" >&2
    exit 1
fi
if [[ ! -x "$MYSQL_BIN" ]]; then
    echo "MySQL CLI not found: $MYSQL_BIN" >&2
    exit 1
fi
if [[ ! "$TEST_DB" =~ ^[a-zA-Z0-9_]+$ ]]; then
    echo "Unsafe temporary database name." >&2
    exit 1
fi

MYSQL_ARGS=(--host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --default-character-set=utf8mb4)
mysql_run() {
    MYSQL_PWD="$DB_PASSWORD" "$MYSQL_BIN" "${MYSQL_ARGS[@]}" "$@"
}

cleanup() {
    if [[ "$CREATED_DB" -eq 1 ]]; then
        mysql_run -e "DROP DATABASE IF EXISTS \`$TEST_DB\`" >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

existing_db="$(mysql_run --batch --skip-column-names -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$TEST_DB'")"
if [[ -n "$existing_db" ]]; then
    echo "Refusing to overwrite an existing database: $TEST_DB" >&2
    exit 1
fi

mysql_run -e "CREATE DATABASE \`$TEST_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
CREATED_DB=1

import_sql() {
    sed "s/tp01_paw/$TEST_DB/g" "$ROOT/$1" | mysql_run "$TEST_DB"
}

import_sql database/database.sql
import_sql database/migration_auth.sql
import_sql database/migration_student_email.sql
import_sql database/seed_pv_statistiques.sql

while IFS= read -r -d '' php_file; do
    "$PHP_BIN" -l "$php_file" >/dev/null
done < <(find "$ROOT" -type f -name '*.php' -not -path '*/.git/*')

export TP_BN_DB_HOST="$DB_HOST"
export TP_BN_DB_PORT="$DB_PORT"
export TP_BN_DB_USER="$DB_USER"
export TP_BN_DB_PASSWORD="$DB_PASSWORD"
export TP_BN_DB_NAME="$TEST_DB"
export PHP_BIN

"$PHP_BIN" "$ROOT/tests/run.php"
