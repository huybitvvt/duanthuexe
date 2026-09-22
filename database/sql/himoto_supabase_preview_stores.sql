-- Read-only preview for the application's HIMOTO schema in Supabase SQL Editor.
-- Review every 'XÓA' row before
-- running himoto_supabase_keep_six_stores.sql. It uses the same priority and
-- one-row-per-code selection rule as the destructive script.
WITH RECURSIVE wanted(seq, code, old_code, old_name, store_name, store_address) AS (
    VALUES
      (1,'CS1','LANG','Láng','CS 1','264 đường Láng, Đống Đa'),
      (2,'CS2','NGUYEN_HOANG','Nguyễn Hoàng','CS 2','Số 30, ngõ 66 Nguyễn Hoàng'),
      (3,'CS3','HANG_BUT','Hàng Bút','CS 3','02 Hàng Bút, Hoàn Kiếm'),
      (4,'CS4','GIAP_BAT','Giáp Bát','CS 4','Số 33, ngõ 286 Giáp Bát'),
      (5,'CS5','QUANG_TRUNG_HA_DONG','Quang Trung Hà Đông','CS 5','476 Quang Trung, Hà Đông'),
      (6,'CS6','ONLINE_OWNERSHIP','Kho sở hữu online','Kho sở hữu','Kho sở hữu')
), picked(seq, code, id, used_ids) AS (
    SELECT 0, NULL::text, NULL::bigint, ARRAY[]::bigint[]
    UNION ALL
    SELECT w.seq, w.code, matched.id,
           CASE WHEN matched.id IS NULL THEN p.used_ids ELSE p.used_ids || matched.id END
    FROM picked p
    JOIN wanted w ON w.seq = p.seq + 1
    LEFT JOIN LATERAL (
        SELECT s.id
        FROM himoto.stores s
        WHERE NOT (s.id = ANY(p.used_ids))
          AND (
              s.code IN (w.code, w.old_code)
              OR lower(btrim(s.store_address)) = lower(w.store_address)
              OR lower(btrim(s.store_name)) IN (lower(w.store_name), lower(w.old_name))
          )
        ORDER BY CASE WHEN s.code = w.code THEN 0
                      WHEN s.code = w.old_code THEN 1
                      WHEN lower(btrim(s.store_address)) = lower(w.store_address) THEN 2
                      ELSE 3 END, s.id
        LIMIT 1
    ) matched ON true
)
SELECT s.id,
       COALESCE('GIỮ: ' || p.code, 'XÓA') AS action,
       (SELECT count(id) FROM picked WHERE seq > 0) AS matched_keepers,
       (SELECT count(*) FROM himoto.orders o WHERE o.store_id=s.id) AS order_count,
       (SELECT count(*) FROM himoto.vehicles v WHERE v.store_id=s.id) AS owned_vehicle_count,
       (SELECT count(*) FROM himoto.vehicles v WHERE v.current_store_id=s.id) AS located_vehicle_count,
       (SELECT count(*) FROM himoto.users u WHERE u.store_id=s.id) AS assigned_user_count,
       s.code AS current_code, s.store_name, s.store_address, s.kind, s.status
FROM himoto.stores s
LEFT JOIN picked p ON p.id = s.id
ORDER BY CASE WHEN p.id IS NULL THEN 1 ELSE 0 END, p.code, s.id;

-- Counts above show only four important direct links; they are not a complete
-- inventory of related data. All 'XÓA' rows, including old duplicate-location
-- rows such as store id 3 ("02 Hàng Bút"), are now approved for deletion.
