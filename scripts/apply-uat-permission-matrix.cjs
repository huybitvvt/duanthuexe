#!/usr/bin/env node
// Apply the reviewable PHP matrix to the existing UAT database once.
// No account or password is read or modified.
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const { Client } = require('../apps/api/node_modules/pg');
require('dotenv').config({ path: path.join(__dirname, '..', '.env.supabase') });

const root = path.join(__dirname, '..');
const migration = '2026_09_29_000001_apply_uat_permission_matrix';
const php = spawnSync('php', ['-r',
  `require('${path.join(root, 'app/Support/UatPermissionMatrix.php').replace(/\\/g, '/')}'); ` +
  'echo json_encode([App\\Support\\UatPermissionMatrix::ROLES, App\\Support\\UatPermissionMatrix::PERMISSIONS], JSON_UNESCAPED_UNICODE);'
], { cwd: root, encoding: 'utf8' });
if (php.status !== 0) throw new Error(`Cannot load PHP permission matrix: ${php.stderr}`);
const [roles, permissions] = JSON.parse(php.stdout);
for (const [, permissionSlugs] of Object.values(roles)) {
  for (const slug of permissionSlugs) {
    if (!Object.prototype.hasOwnProperty.call(permissions, slug)) permissions[slug] = slug;
  }
}

const rawUrl = process.env.SUPABASE_DATABASE_URL;
if (!rawUrl) throw new Error('SUPABASE_DATABASE_URL is missing');
const dbUrl = new URL(rawUrl);
if (!(dbUrl.host.includes('xlxkheprwosozyhkejrl') ||
  decodeURIComponent(dbUrl.username).includes('xlxkheprwosozyhkejrl'))) {
  throw new Error('Connection is not the documented UAT Supabase project');
}
dbUrl.searchParams.delete('sslmode');
const client = new Client({ connectionString: dbUrl.toString(), ssl: { rejectUnauthorized: false }, connectionTimeoutMillis: 5000 });

async function verify() {
  const result = await client.query(`
    SELECT r.slug, count(DISTINCT rp.id)::int AS permission_count,
      array_remove(array_agg(DISTINCT p.slug ORDER BY p.slug), NULL) AS permission_slugs,
      count(DISTINCT u.id)::int AS active_users
    FROM himoto.roles r
    LEFT JOIN himoto.roles_permissions rp ON rp.role_id = r.id
    LEFT JOIN himoto.permissions p ON p.id = rp.permission_id
    LEFT JOIN himoto.users u ON u.role_id = r.id AND u.deleted_at IS NULL AND u.status <> 'deactive'
    WHERE r.slug = ANY($1::text[])
    GROUP BY r.slug ORDER BY r.slug
  `, [Object.keys(roles)]);
  if (result.rowCount !== Object.keys(roles).length) throw new Error('Not all matrix roles were created');
  for (const row of result.rows) {
    const expected = roles[row.slug][1].length;
    if (row.permission_count !== expected ||
      JSON.stringify(row.permission_slugs) !== JSON.stringify([...roles[row.slug][1]].sort())) {
      throw new Error(`Verification failed for ${row.slug}`);
    }
    delete row.permission_slugs;
  }
  console.log(JSON.stringify(result.rows));
}

async function main() {
  await client.connect();
  const preflight = await client.query(`
    SELECT
      (SELECT count(*)::int FROM himoto.users WHERE deleted_at IS NULL AND status <> 'deactive') AS active_users,
      (SELECT count(*)::int FROM himoto.stores WHERE code IN ('CS1','CS2','CS3','CS4','CS5') AND kind = 'physical') AS branch_stores,
      (SELECT count(*)::int FROM himoto.migrations WHERE migration = $1) AS already_applied
  `, [migration]);
  const state = preflight.rows[0];
  if (state.active_users !== 14 || state.branch_stores !== 5) {
    throw new Error(`UAT preflight changed: ${JSON.stringify(state)}`);
  }
  if (state.already_applied) {
    console.log('Permission matrix migration was already applied.');
    await verify();
    return;
  }

  const snapshot = await client.query(`
    SELECT r.id AS role_id, r.slug AS role_slug, r.name AS role_name,
      p.id AS permission_id, p.slug AS permission_slug, p.name AS permission_name
    FROM himoto.roles r
    LEFT JOIN himoto.roles_permissions rp ON rp.role_id = r.id
    LEFT JOIN himoto.permissions p ON p.id = rp.permission_id
    WHERE r.slug = ANY($1::text[])
    ORDER BY r.slug, p.slug
  `, [Object.keys(roles)]);
  const backup = path.join(root, 'backups', `uat-permissions-before-${new Date().toISOString().replace(/[:.]/g, '-')}.json`);
  fs.mkdirSync(path.dirname(backup), { recursive: true });
  fs.writeFileSync(backup, JSON.stringify({ project: 'xlxkheprwosozyhkejrl', migration, rows: snapshot.rows }, null, 2), { flag: 'wx' });
  console.log(`Saved previous grants to ${backup}`);

  await client.query('BEGIN');
  try {
    await client.query('SELECT pg_advisory_xact_lock(20260929, 1)');
    for (const [slug, name] of Object.entries(permissions)) {
      await client.query(`
        INSERT INTO himoto.permissions (slug, name, created_at, updated_at)
        SELECT $1::text, $2::text, NOW(), NOW()
        WHERE NOT EXISTS (SELECT 1 FROM himoto.permissions WHERE slug = $1::text)
      `, [slug, name]);
    }
    for (const [slug, [name, permissionSlugs]] of Object.entries(roles)) {
      await client.query(`
        INSERT INTO himoto.roles (slug, name, created_at, updated_at)
        SELECT $1::text, $2::text, NOW(), NOW()
        WHERE NOT EXISTS (SELECT 1 FROM himoto.roles WHERE slug = $1::text)
      `, [slug, name]);
      const roleId = (await client.query('SELECT id FROM himoto.roles WHERE slug = $1', [slug])).rows[0].id;
      const permissionRows = await client.query('SELECT id, slug FROM himoto.permissions WHERE slug = ANY($1::text[])', [permissionSlugs]);
      if (permissionRows.rowCount !== permissionSlugs.length) throw new Error(`Missing permission for ${slug}`);
      const permissionIds = permissionRows.rows.map(row => Number(row.id));
      for (const permissionId of permissionIds) {
        await client.query(`
          INSERT INTO himoto.roles_permissions (role_id, permission_id, created_at, updated_at)
          SELECT $1::int, $2::int, NOW(), NOW()
          WHERE NOT EXISTS (
            SELECT 1 FROM himoto.roles_permissions WHERE role_id = $1::int AND permission_id = $2::int
          )
        `, [roleId, permissionId]);
      }
      await client.query('DELETE FROM himoto.roles_permissions WHERE role_id = $1 AND NOT (permission_id = ANY($2::int[]))', [roleId, permissionIds]);
    }
    await client.query(`
      INSERT INTO himoto.migrations (migration, batch)
      SELECT $1, COALESCE(MAX(batch), 0) + 1 FROM himoto.migrations
    `, [migration]);
    await client.query('COMMIT');
  } catch (error) {
    await client.query('ROLLBACK');
    throw error;
  }

  await verify();
}

main().catch(error => {
  console.error(error.message);
  process.exitCode = 1;
}).finally(() => client.end());
