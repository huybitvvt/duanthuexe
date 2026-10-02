-- HIMOTO UAT reset. LOCKED until a separate staging project is verified.
-- Creates 14 fresh accounts across the 6 roles and 5 physical warehouses,
-- deactivates/soft-deletes all older accounts, and spreads eligible ready vehicles.
-- After the staging gate is removed, run the whole file once in its SQL Editor.
BEGIN;
SET LOCAL search_path TO himoto, extensions, public;
SET LOCAL lock_timeout = '10s';
SET LOCAL statement_timeout = '120s';

-- The project shown in the 28/09 screenshot serves the live website.
-- Keep this fail-closed gate until the staging project ref is known and
-- the backend's DATABASE_URL is confirmed to point to that new project.
DO $staging_guard$
BEGIN
  RAISE EXCEPTION 'UAT setup locked: the separate Supabase staging project has not been verified';
END
$staging_guard$;

LOCK TABLE himoto.users, himoto.vehicles IN SHARE ROW EXCLUSIVE MODE;

CREATE TEMP TABLE _uat_run (run_id text NOT NULL) ON COMMIT PRESERVE ROWS;
INSERT INTO _uat_run VALUES (to_char(clock_timestamp(), 'YYYYMMDDHH24MISS'));

CREATE TEMP TABLE _uat_stores ON COMMIT PRESERVE ROWS AS
SELECT id, code, store_name, row_number() OVER (ORDER BY code) AS position
FROM himoto.stores
WHERE code IN ('LANG', 'NGUYEN_HOANG', 'QUANG_TRUNG_HA_DONG', 'HANG_BUT', 'GIAP_BAT')
  AND kind = 'physical' AND status <> 'inactive';

DO $check$
BEGIN
  IF (SELECT count(*) FROM _uat_stores) <> 5 THEN
    RAISE EXCEPTION 'Expected all five physical HIMOTO warehouses';
  END IF;
  IF (SELECT count(*) FROM himoto.roles WHERE slug IN
      ('quan-tri-vien', 'ban-giam-doc', 'ke-toan', 'nhan-su', 'quan-ly-cua-hang', 'nhan-vien')) <> 6 THEN
    RAISE EXCEPTION 'Expected exactly six standard RBAC roles';
  END IF;
  IF (SELECT id FROM himoto.roles WHERE slug = 'quan-tri-vien') <> 1 THEN
    RAISE EXCEPTION 'Admin role must be ID 1 for the current Laravel/Node UI';
  END IF;
  IF EXISTS (
    SELECT 1 FROM himoto.roles r
    WHERE r.slug IN ('ban-giam-doc', 'ke-toan', 'nhan-su', 'quan-ly-cua-hang', 'nhan-vien')
      AND NOT EXISTS (SELECT 1 FROM himoto.roles_permissions rp WHERE rp.role_id = r.id)
  ) THEN
    RAISE EXCEPTION 'A non-admin UAT role has no permissions';
  END IF;
  IF to_regprocedure('crypt(text,text)') IS NULL
     OR to_regprocedure('gen_salt(text,integer)') IS NULL
     OR to_regprocedure('gen_random_bytes(integer)') IS NULL THEN
    RAISE EXCEPTION 'pgcrypto is unavailable; enable it in Supabase Extensions';
  END IF;
  IF EXISTS (SELECT 1 FROM himoto.users
             WHERE email LIKE 'uat-%@himoto.test' AND deleted_at IS NULL) THEN
    RAISE EXCEPTION 'Active UAT accounts already exist; refusing a second reset';
  END IF;
END
$check$;

CREATE TEMP TABLE _uat_accounts ON COMMIT PRESERVE ROWS AS
SELECT source.account_key,
       source.display_name,
       source.role_slug,
       source.store_id,
       source.store_name,
       format('uat-%s-%s@himoto.test', (SELECT run_id FROM _uat_run), source.account_key) AS email,
       encode(gen_random_bytes(18), 'hex') AS password_plain
FROM (
  SELECT account_key, display_name, role_slug, NULL::bigint AS store_id, NULL::text AS store_name
  FROM (VALUES
    ('admin', 'UAT Quản trị viên', 'quan-tri-vien'),
    ('director', 'UAT Ban giám đốc', 'ban-giam-doc'),
    ('accountant', 'UAT Kế toán', 'ke-toan'),
    ('hr', 'UAT Nhân sự', 'nhan-su')
  ) AS central(account_key, display_name, role_slug)
  UNION ALL
  SELECT 'manager-' || lower(s.code), 'UAT Quản lý ' || s.store_name,
         'quan-ly-cua-hang', s.id, s.store_name
  FROM _uat_stores s
  UNION ALL
  SELECT 'staff-' || lower(s.code), 'UAT Nhân viên ' || s.store_name,
         'nhan-vien', s.id, s.store_name
  FROM _uat_stores s
) AS source;

DO $check$
BEGIN
  IF (SELECT count(*) FROM _uat_accounts) <> 14 THEN
    RAISE EXCEPTION 'Expected exactly 14 UAT accounts';
  END IF;
  IF EXISTS (SELECT 1 FROM himoto.users u JOIN _uat_accounts a ON a.email = u.email) THEN
    RAISE EXCEPTION 'A generated UAT email already exists';
  END IF;
END
$check$;

INSERT INTO himoto.users
  (name, email, password, role, role_id, store_id, status, deleted_at, created_at, updated_at)
SELECT a.display_name, a.email, crypt(a.password_plain, gen_salt('bf', 10)),
       CASE WHEN a.role_slug = 'quan-tri-vien' THEN 'admin' ELSE a.role_slug END,
       r.id, a.store_id, 'active', NULL, now(), now()
FROM _uat_accounts a
JOIN himoto.roles r ON r.slug = a.role_slug;

CREATE TEMP TABLE _uat_created ON COMMIT PRESERVE ROWS AS
SELECT u.id, a.account_key, a.email FROM himoto.users u JOIN _uat_accounts a ON a.email = u.email;

DO $check$
BEGIN
  IF (SELECT count(*) FROM _uat_created) <> 14 THEN
    RAISE EXCEPTION 'UAT account insert did not create 14 users';
  END IF;
END
$check$;

CREATE TEMP TABLE _uat_old_users ON COMMIT PRESERVE ROWS AS
SELECT id, status, deleted_at, store_id
FROM himoto.users
WHERE deleted_at IS NULL AND id NOT IN (SELECT id FROM _uat_created);

UPDATE himoto.users u
SET status = 'deactive', deleted_at = now(), remember_token = NULL, updated_at = now()
FROM _uat_old_users old
WHERE u.id = old.id;

INSERT INTO himoto.audit_events
  (actor_user_id, action, subject_type, subject_id, store_id, before_json, after_json, reason, request_id, created_at)
SELECT (SELECT id FROM _uat_created WHERE account_key = 'admin'),
       'uat.user.deactivated', 'users', old.id, old.store_id,
       jsonb_build_object('status', old.status, 'deleted_at', old.deleted_at)::text,
       jsonb_build_object('status', 'deactive', 'deleted_at', now())::text,
       'UAT account reset on staging', (SELECT run_id FROM _uat_run), now()
FROM _uat_old_users old;

-- Only movable, available vehicles. Keep sold, rented, in-transit,
-- lease-to-own and vehicles linked to open orders or transfers unchanged.
CREATE TEMP TABLE _uat_vehicle_moves ON COMMIT PRESERVE ROWS AS
WITH eligible AS (
  SELECT v.id, v.store_id AS old_managed_store_id,
         v.current_store_id AS old_current_store_id,
         COALESCE(v.current_store_id, v.store_id) AS old_effective_store_id,
         v.odometer,
         row_number() OVER (ORDER BY v.type NULLS LAST, v.id) AS position
  FROM himoto.vehicles v
  WHERE v.status = 'ready'
    AND NOT EXISTS (
      SELECT 1 FROM himoto.stores s
      WHERE s.id IN (v.store_id, v.current_store_id) AND s.kind = 'lease_to_own'
    )
    AND NOT EXISTS (
      SELECT 1 FROM himoto.vehicle_transfer_items i
      JOIN himoto.vehicle_transfers t ON t.id = i.transfer_id
      WHERE i.vehicle_id = v.id AND t.status = 'dispatched'
    )
    AND NOT EXISTS (
      SELECT 1 FROM himoto.order_vehicle_details d
      JOIN himoto.orders o ON o.id = d.order_id
      WHERE d.vehicle_id = v.id AND d.deleted_at IS NULL AND o.deleted_at IS NULL
        AND COALESCE(o.order_status, '') NOT IN ('completed', 'cancelled', 'canceled', 'done')
    )
), targets AS (
  SELECT e.*, s.id AS target_store_id
  FROM eligible e
  JOIN _uat_stores s ON s.position = ((e.position - 1) % 5) + 1
)
SELECT * FROM targets
WHERE old_managed_store_id IS DISTINCT FROM target_store_id
   OR old_current_store_id IS DISTINCT FROM target_store_id;

INSERT INTO himoto.vehicle_location_events
  (vehicle_id, from_store_id, to_store_id, event_type, ref_type, ref_id,
   odometer, notes, created_by, created_at, updated_at)
SELECT m.id, m.old_effective_store_id, m.target_store_id, 'uat_rebalance',
       NULL, NULL, m.odometer,
       'UAT staging fleet rebalance; previous managed store: ' || COALESCE(m.old_managed_store_id::text, 'none'),
       (SELECT id FROM _uat_created WHERE account_key = 'admin'), now(), now()
FROM _uat_vehicle_moves m;

UPDATE himoto.vehicles v
SET store_id = m.target_store_id,
    current_store_id = m.target_store_id,
    updated_at = now()
FROM _uat_vehicle_moves m
WHERE v.id = m.id;

INSERT INTO himoto.audit_events
  (actor_user_id, action, subject_type, subject_id, store_id, before_json, after_json, reason, request_id, created_at)
SELECT (SELECT id FROM _uat_created WHERE account_key = 'admin'),
       'uat.vehicle.rebalanced', 'vehicles', m.id, m.target_store_id,
       jsonb_build_object('store_id', m.old_managed_store_id, 'current_store_id', m.old_current_store_id)::text,
       jsonb_build_object('store_id', m.target_store_id, 'current_store_id', m.target_store_id)::text,
       'UAT fleet distribution on staging', (SELECT run_id FROM _uat_run), now()
FROM _uat_vehicle_moves m;

DO $check$
BEGIN
  IF (SELECT count(*) FROM himoto.users WHERE deleted_at IS NULL AND status = 'active') <> 14 THEN
    RAISE EXCEPTION 'Postcheck failed: expected exactly 14 active accounts';
  END IF;
  IF EXISTS (
    SELECT 1 FROM _uat_vehicle_moves m JOIN himoto.vehicles v ON v.id = m.id
    WHERE v.store_id IS DISTINCT FROM m.target_store_id
       OR v.current_store_id IS DISTINCT FROM m.target_store_id
  ) THEN
    RAISE EXCEPTION 'Postcheck failed: vehicle assignment mismatch';
  END IF;
END
$check$;

COMMIT;

-- Copy this final result immediately. Passwords exist only in this SQL session.
SELECT (SELECT run_id FROM _uat_run) AS run_id,
       a.email, a.password_plain AS password, a.display_name AS name,
       a.role_slug AS role, a.store_name AS warehouse,
       (SELECT count(*) FROM _uat_old_users) AS deactivated_old_users,
       (SELECT count(*) FROM _uat_vehicle_moves) AS moved_ready_vehicles
FROM _uat_accounts a
ORDER BY CASE a.account_key WHEN 'admin' THEN 0 WHEN 'director' THEN 1
         WHEN 'accountant' THEN 2 WHEN 'hr' THEN 3 ELSE 4 END,
         a.store_name, a.role_slug;
