#!/usr/bin/env node
// Add only the six UAT users missing from the approved position matrix.
// Dry run by default; --apply writes a private credentials CSV and the DB rows.
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { Client } = require('../apps/api/node_modules/pg');
require('dotenv').config({ path: path.join(__dirname, '..', '.env.supabase') });

const root = path.join(__dirname, '..');
const project = 'xlxkheprwosozyhkejrl';
const runId = '20260930-remaining-roles';
const specs = [
  { key: 'director-thuy', name: 'UAT Ban giám đốc - Chị Thủy', role: 'ban-giam-doc' },
  { key: 'operations-minh', name: 'UAT Vận hành - Minh', role: 'van-hanh' },
  { key: 'lease-manager-cs6', name: 'UAT Trưởng phòng thuê sở hữu CS6', role: 'thue-so-huu-truong-phong', store: 'CS6' },
  { key: 'lease-contract-cs6', name: 'UAT Nhân viên hợp đồng thuê sở hữu CS6', role: 'thue-so-huu-hop-dong', store: 'CS6' },
  { key: 'lease-debt-cs6', name: 'UAT Nhân viên thu hồi nợ CS6', role: 'thue-so-huu-thu-hoi-no', store: 'CS6' },
  { key: 'telesale', name: 'UAT Telesale', role: 'telesale' },
].map(spec => ({ ...spec, email: `uat-20260930-${spec.key}@himoto.test` }));

if (process.argv.slice(2).some(arg => arg !== '--apply')) {
  throw new Error('Usage: node scripts/create-remaining-uat-accounts.cjs [--apply]');
}
const apply = process.argv.includes('--apply');
const rawUrl = process.env.SUPABASE_DATABASE_URL;
if (!rawUrl) throw new Error('SUPABASE_DATABASE_URL is missing');
const url = new URL(rawUrl);
if (!(url.host.includes(project) || decodeURIComponent(url.username).includes(project))) {
  throw new Error('Connection is not the documented UAT Supabase project');
}
url.searchParams.delete('sslmode');
const client = new Client({ connectionString: url.toString(), ssl: { rejectUnauthorized: false }, connectionTimeoutMillis: 5000 });

const csvCell = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
const output = path.join(root, 'backups', 'uat-credentials-additional-20260930.csv');
const pending = `${output}.pending`;

async function preflight() {
  const active = await client.query("SELECT count(*)::int AS total FROM himoto.users WHERE deleted_at IS NULL AND status <> 'deactive'");
  if (active.rows[0].total !== 14) throw new Error(`Expected 14 existing active UAT users, found ${active.rows[0].total}`);
  const director = await client.query(`
    SELECT u.id, u.name FROM himoto.users u JOIN himoto.roles r ON r.id = u.role_id
    WHERE u.deleted_at IS NULL AND u.status = 'active' AND r.slug = 'ban-giam-doc'
  `);
  if (director.rowCount !== 1 || director.rows[0].name !== 'UAT Ban giám đốc') {
    throw new Error('The original UAT director account changed; review before assigning Anh Tuấn');
  }
  const roles = await client.query('SELECT id, slug FROM himoto.roles WHERE slug = ANY($1::text[])', [specs.map(s => s.role)]);
  const roleIds = new Map(roles.rows.map(row => [row.slug, row.id]));
  if (roleIds.size !== new Set(specs.map(s => s.role)).size) throw new Error('A required UAT role is missing');
  const stores = await client.query("SELECT id FROM himoto.stores WHERE code = 'CS6' AND kind = 'lease_to_own' AND status <> 'inactive'");
  if (stores.rowCount !== 1) throw new Error('Canonical lease-to-own store CS6 is missing');
  const existing = await client.query('SELECT count(*)::int AS total FROM himoto.users WHERE email = ANY($1::text[])', [specs.map(s => s.email)]);
  if (existing.rows[0].total !== 0) throw new Error('One or more planned UAT emails already exist');
  return { directorId: director.rows[0].id, roleIds, leaseStoreId: stores.rows[0].id };
}

async function main() {
  await client.connect();
  const state = await preflight();
  if (!apply) {
    console.log(JSON.stringify({ mode: 'dry-run', existingActive: 14,
      planned: specs.map(({ key, role, store }) => ({ key, role, store: store || null })),
      renameExistingDirector: 'UAT Ban giám đốc - Anh Tuấn' }));
    return;
  }
  if (fs.existsSync(output) || fs.existsSync(pending)) throw new Error('Credentials file already exists; refusing to overwrite');

  const accounts = specs.map(spec => ({ ...spec, password: crypto.randomBytes(18).toString('hex') }));
  const header = 'run_id,email,password,name,role,warehouse,deactivated_old_users,moved_ready_vehicles';
  const lines = accounts.map(a => [runId, a.email, a.password, a.name, a.role, a.store || '', 0, 0].map(csvCell).join(','));
  fs.writeFileSync(pending, `\uFEFF${header}\r\n${lines.join('\r\n')}\r\n`, { flag: 'wx', mode: 0o600 });

  await client.query('BEGIN');
  try {
    await client.query('SELECT pg_advisory_xact_lock(20260930, 1)');
    await preflight();
    await client.query("UPDATE himoto.users SET name = 'UAT Ban giám đốc - Anh Tuấn', updated_at = now() WHERE id = $1", [state.directorId]);
    await client.query(`
      INSERT INTO himoto.audit_events (action, subject_type, subject_id, before_json, after_json, reason)
      VALUES ('uat_account_assigned', 'user', $1, $2, $3, $4)
    `, [state.directorId, JSON.stringify({ name: 'UAT Ban giám đốc' }),
      JSON.stringify({ name: 'UAT Ban giám đốc - Anh Tuấn', role: 'ban-giam-doc' }), runId]);

    for (const account of accounts) {
      const storeId = account.store ? state.leaseStoreId : null;
      const inserted = await client.query(`
        INSERT INTO himoto.users (name, email, password, role, role_id, store_id, status, deleted_at, created_at, updated_at)
        VALUES ($1, $2, crypt($3, gen_salt('bf', 10)), $4, $5, $6, 'active', NULL, now(), now()) RETURNING id
      `, [account.name, account.email, account.password, account.role, state.roleIds.get(account.role), storeId]);
      await client.query(`
        INSERT INTO himoto.audit_events (action, subject_type, subject_id, store_id, after_json, reason)
        VALUES ('uat_account_created', 'user', $1, $2, $3, $4)
      `, [inserted.rows[0].id, storeId, JSON.stringify({ role: account.role, store: account.store || null }), runId]);
    }
    const after = await client.query("SELECT count(*)::int AS total FROM himoto.users WHERE deleted_at IS NULL AND status <> 'deactive'");
    if (after.rows[0].total !== 20) throw new Error('Active user count after insert is not 20');
    await client.query('COMMIT');
  } catch (error) {
    await client.query('ROLLBACK');
    throw error;
  }
  fs.renameSync(pending, output);
  console.log(JSON.stringify({ mode: 'applied', created: accounts.length, activeUsers: 20,
    renamedExistingDirector: true, credentialsFile: output }));
}

main().catch(error => {
  console.error(`UAT account setup failed: ${error.message}`);
  process.exitCode = 1;
}).finally(() => client.end().catch(() => {}));
