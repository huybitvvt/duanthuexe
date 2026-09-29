#!/usr/bin/env node
// Read-only check of the Postgres settings that most affect Supabase log ingestion.
const path = require('path');
const { Client } = require('../apps/api/node_modules/pg');
require('dotenv').config({ path: path.join(__dirname, '..', '.env.supabase') });

const rawUrl = process.env.SUPABASE_DATABASE_URL;
if (!rawUrl) throw new Error('SUPABASE_DATABASE_URL is missing');
const dbUrl = new URL(rawUrl);
if (!(dbUrl.host.includes('xlxkheprwosozyhkejrl') ||
  decodeURIComponent(dbUrl.username).includes('xlxkheprwosozyhkejrl'))) {
  throw new Error('Connection is not the documented UAT Supabase project');
}
dbUrl.searchParams.delete('sslmode');
const client = new Client({ connectionString: dbUrl.toString(),
  ssl: { rejectUnauthorized: false }, connectionTimeoutMillis: 5000 });

(async () => {
  try {
    await client.connect();
    const names = ['log_statement', 'log_min_duration_statement', 'log_connections',
      'log_disconnections', 'log_min_messages', 'log_autovacuum_min_duration',
      'pgaudit.log', 'cron.log_statement'];
    const result = await client.query(
      'SELECT name, setting, unit, source FROM pg_settings WHERE name = ANY($1::text[]) ORDER BY name',
      [names]
    );
    for (const row of result.rows) {
      process.stdout.write(`${row.name}\t${row.setting}${row.unit || ''}\t${row.source}\n`);
    }
    const cron = await client.query("SELECT to_regclass('cron.job') IS NOT NULL AS available");
    if (cron.rows[0].available) {
      const jobs = await client.query('SELECT count(*)::int AS total, count(*) FILTER (WHERE active)::int AS active FROM cron.job');
      process.stdout.write(`cron_jobs\t${jobs.rows[0].active} active / ${jobs.rows[0].total} total\n`);
    }
  } finally {
    await client.end().catch(() => {});
  }
})().catch(error => {
  process.stderr.write(`Log configuration check failed: ${error.message}\n`);
  process.exitCode = 1;
});
