#!/bin/sh
set -eu

if [ -z "${SUPABASE_DATABASE_URL:-}" ]; then
    echo "SUPABASE_DATABASE_URL is not set." >&2
    exit 1
fi

psql "$SUPABASE_DATABASE_URL" --no-psqlrc --tuples-only --no-align --set ON_ERROR_STOP=1 <<'SQL'
SELECT 'tables=' || count(*) FROM information_schema.tables WHERE table_schema = 'himoto';
SELECT 'orders=' || count(*) FROM himoto.orders;
SELECT 'transactions=' || count(*) FROM himoto.transactions;
SELECT 'lead_logs=' || count(*) FROM himoto.lead_logs;
SELECT 'files=' || count(*) FROM himoto.files;
SELECT 'cloudinary_columns=' || count(*)
FROM information_schema.columns
WHERE table_schema = 'himoto'
  AND table_name = 'files'
  AND column_name IN ('provider', 'provider_id', 'url');
SELECT 'cloudinary_migration=' || count(*)
FROM himoto.migrations
WHERE migration = '2026_09_12_000000_add_cloudinary_columns_to_files_table';
SELECT 'database_size=' || pg_size_pretty(pg_database_size(current_database()));
SQL
