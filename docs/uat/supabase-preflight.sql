-- Read-only inventory. SQL Editor shows one result row, including role/store JSON.
-- The Supabase project name and URL above the editor determine the target.
BEGIN TRANSACTION READ ONLY;
SET LOCAL search_path TO himoto, extensions, public;

WITH role_stats AS (
  SELECT r.id, r.slug, r.name,
         (SELECT count(*) FROM himoto.roles_permissions rp WHERE rp.role_id = r.id) AS permissions,
         count(u.id) FILTER (WHERE u.deleted_at IS NULL AND u.status <> 'deactive') AS active_users
  FROM himoto.roles r
  LEFT JOIN himoto.users u ON u.role_id = r.id
  WHERE r.slug IN ('quan-tri-vien', 'ban-giam-doc', 'ke-toan', 'nhan-su',
                   'quan-ly-cua-hang', 'nhan-vien')
  GROUP BY r.id, r.slug, r.name
), store_stats AS (
  SELECT s.id, s.code, s.store_name, s.kind, s.status,
         count(v.id) FILTER (WHERE v.status = 'ready') AS ready_vehicles,
         count(v.id) FILTER (WHERE v.status NOT IN ('sold', 'in_transit')) AS present_vehicles
  FROM himoto.stores s
  LEFT JOIN himoto.vehicles v ON COALESCE(v.current_store_id, v.store_id) = s.id
  WHERE s.code IN ('CS1', 'CS2', 'CS3', 'CS4', 'CS5') AND s.kind = 'physical'
  GROUP BY s.id, s.code, s.store_name, s.kind, s.status
), vehicle_stats AS (
  SELECT status, count(*) AS vehicles FROM himoto.vehicles GROUP BY status
)
SELECT current_database() AS database_name,
       current_schema() AS schema_name,
       to_regclass('himoto.audit_events') IS NOT NULL AS has_audit_events,
       to_regclass('himoto.vehicle_location_events') IS NOT NULL AS has_location_events,
       to_regprocedure('crypt(text,text)') IS NOT NULL AS has_bcrypt,
       to_regprocedure('gen_salt(text,integer)') IS NOT NULL AS has_gen_salt,
       to_regprocedure('gen_random_bytes(integer)') IS NOT NULL AS has_random_bytes,
       (SELECT count(*) FROM himoto.users WHERE deleted_at IS NULL AND status <> 'deactive') AS active_users,
       (SELECT count(*) FROM himoto.vehicle_transfers WHERE status = 'dispatched') AS open_transfers,
       (SELECT count(*) FROM role_stats) AS standard_roles_found,
       (SELECT count(*) FROM store_stats) AS target_stores_found,
       (SELECT COALESCE(jsonb_agg(to_jsonb(r) ORDER BY r.id), '[]'::jsonb) FROM role_stats r) AS roles,
       (SELECT COALESCE(jsonb_agg(to_jsonb(s) ORDER BY s.id), '[]'::jsonb) FROM store_stats s) AS stores,
       (SELECT COALESCE(jsonb_agg(to_jsonb(v) ORDER BY v.status), '[]'::jsonb) FROM vehicle_stats v) AS vehicle_statuses;

COMMIT;
