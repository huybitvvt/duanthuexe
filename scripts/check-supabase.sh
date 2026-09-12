#!/bin/sh
set -eu

if [ -z "${SUPABASE_DATABASE_URL:-}" ]; then
    echo "SUPABASE_DATABASE_URL is not set." >&2
    exit 1
fi

psql "$SUPABASE_DATABASE_URL" --no-psqlrc --tuples-only --no-align --set ON_ERROR_STOP=1 <<'SQL'
SELECT current_database(), current_user, current_setting('ssl');
SELECT count(*) FROM information_schema.tables WHERE table_schema = 'himoto';
SQL
