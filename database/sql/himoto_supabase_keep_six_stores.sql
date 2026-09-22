-- HIMOTO / Supabase PostgreSQL, schema himoto: keep exactly CS1..CS6 and permanently remove
-- warehouse-owned records for every other store. Run ONCE in SQL Editor after
-- taking a database backup and putting the application in maintenance mode.
-- Do not use TRUNCATE ... CASCADE: it can erase records of the six kept stores.
-- Users and globally shared customers are not blindly deleted: users assigned
-- to deleted stores are unassigned, while customers are deleted only if no
-- remaining table references them after the warehouse records are purged.
-- Global/unattributed rows cannot be safely assigned to a warehouse; remote
-- image objects in Cloudinary/Supabase Storage are not removed by SQL.
-- A surviving contract with deleted child records or an unknown FK aborts
-- this transaction; no partial deletion is committed.

BEGIN;
SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
DO $schema_guard$
BEGIN
    IF to_regclass('himoto.stores') IS NULL THEN
        RAISE EXCEPTION 'himoto.stores does not exist in this Supabase project. Stop and verify the target database.';
    END IF;
END;
$schema_guard$;
LOCK TABLE himoto.stores IN ACCESS EXCLUSIVE MODE;

DO $guard$
BEGIN
    IF to_regclass('himoto.stores') IS NULL
       OR NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='stores' AND column_name='code')
       OR NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='stores' AND column_name='kind') THEN
        RAISE EXCEPTION 'Missing stores/code/kind. Deploy database migrations through 2026_09_15 first.';
    END IF;
    IF to_regclass('himoto.migrations') IS NOT NULL THEN
        IF NOT EXISTS (
            SELECT 1 FROM himoto.migrations
            WHERE migration = '2026_09_17_000014_ensure_himoto_warehouses'
        ) THEN
            RAISE EXCEPTION 'The old warehouse seeder migration has not run; running it later would recreate extra stores.';
        END IF;
    END IF;
END;
$guard$;

CREATE TEMP TABLE _wanted_stores (
    code text PRIMARY KEY,
    old_code text NOT NULL,
    old_name text NOT NULL,
    store_name text NOT NULL,
    store_address text NOT NULL,
    kind text NOT NULL
) ON COMMIT DROP;

INSERT INTO _wanted_stores VALUES
 ('CS1','LANG','Láng','CS 1','264 đường Láng, Đống Đa','physical'),
 ('CS2','NGUYEN_HOANG','Nguyễn Hoàng','CS 2','Số 30, ngõ 66 Nguyễn Hoàng','physical'),
 ('CS3','HANG_BUT','Hàng Bút','CS 3','02 Hàng Bút, Hoàn Kiếm','physical'),
 ('CS4','GIAP_BAT','Giáp Bát','CS 4','Số 33, ngõ 286 Giáp Bát','physical'),
 ('CS5','QUANG_TRUNG_HA_DONG','Quang Trung Hà Đông','CS 5','476 Quang Trung, Hà Đông','physical'),
 ('CS6','ONLINE_OWNERSHIP','Kho sở hữu online','Kho sở hữu','Kho sở hữu','lease_to_own');

CREATE TEMP TABLE _kept_stores (code text PRIMARY KEY, id bigint UNIQUE NOT NULL) ON COMMIT DROP;

DO $select_six$
DECLARE r record; chosen_id bigint;
BEGIN
    FOR r IN SELECT * FROM _wanted_stores ORDER BY code LOOP
        SELECT s.id INTO chosen_id
        FROM himoto.stores s
        WHERE s.id NOT IN (SELECT id FROM _kept_stores)
          AND (
            s.code IN (r.code, r.old_code)
            OR lower(btrim(s.store_address)) = lower(r.store_address)
            OR lower(btrim(s.store_name)) IN (lower(r.store_name), lower(r.old_name))
          )
        ORDER BY CASE WHEN s.code = r.code THEN 0
                      WHEN s.code = r.old_code THEN 1
                      WHEN lower(btrim(s.store_address)) = lower(r.store_address) THEN 2
                      ELSE 3 END, s.id
        LIMIT 1;
        IF chosen_id IS NULL THEN
            RAISE EXCEPTION 'Cannot unambiguously find %. No data changed; inspect stores and update the matching rule.', r.code;
        END IF;
        INSERT INTO _kept_stores (code, id) VALUES (r.code, chosen_id);
    END LOOP;
END;
$select_six$;

UPDATE himoto.stores s
SET code = d.code, store_name = d.store_name,
    store_address = d.store_address, kind = d.kind,
    status = 'opening', updated_at = now()
FROM _kept_stores k JOIN _wanted_stores d ON d.code = k.code
WHERE s.id = k.id;

CREATE TEMP TABLE _extra_stores (id bigint PRIMARY KEY) ON COMMIT DROP;
INSERT INTO _extra_stores SELECT id FROM himoto.stores WHERE id NOT IN (SELECT id FROM _kept_stores);

-- Preserve contract & financial integrity for the six kept stores:
-- Reassign transactions, vehicles, bank accounts, cash registers, and related
-- entities belonging to kept orders or lease contracts so they are not purged
-- when their historical store_id pointed to a deleted warehouse.
DO $reassign_kept_contracts_finances$
DECLARE
    n bigint;
BEGIN
    -- 1. Preserve financial transactions belonging to kept orders:
    IF to_regclass('himoto.transactions') IS NOT NULL AND to_regclass('himoto.orders') IS NOT NULL THEN
        UPDATE himoto.transactions t
        SET store_id = o.store_id, updated_at = now()
        FROM himoto.orders o
        WHERE t.order_id = o.id
          AND o.store_id IN (SELECT id FROM _kept_stores)
          AND (t.store_id IN (SELECT id FROM _extra_stores) OR t.store_id IS NULL);
        GET DIAGNOSTICS n = ROW_COUNT;
        IF n > 0 THEN
            RAISE NOTICE 'Reassigned % transactions belonging to kept orders to their parent store.', n;
        END IF;

        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='transactions' AND column_name='order_item_id')
           AND to_regclass('himoto.order_vehicle_details') IS NOT NULL THEN
            EXECUTE 'UPDATE himoto.transactions t
                     SET store_id = o.store_id, updated_at = now()
                     FROM himoto.order_vehicle_details ovd
                     JOIN himoto.orders o ON o.id = ovd.order_id
                     WHERE t.order_item_id = ovd.id
                       AND o.store_id IN (SELECT id FROM _kept_stores)
                       AND (t.store_id IN (SELECT id FROM _extra_stores) OR t.store_id IS NULL)';
            GET DIAGNOSTICS n = ROW_COUNT;
            IF n > 0 THEN
                RAISE NOTICE 'Reassigned % order-item transactions of kept orders to their parent store.', n;
            END IF;
        END IF;
    END IF;

    -- 2. Preserve financial transactions belonging to kept lease contracts:
    IF to_regclass('himoto.transactions') IS NOT NULL AND to_regclass('himoto.lease_contracts') IS NOT NULL THEN
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='transactions' AND column_name='lease_contract_id') THEN
            EXECUTE 'UPDATE himoto.transactions t
                     SET store_id = lc.store_id, updated_at = now()
                     FROM himoto.lease_contracts lc
                     WHERE t.lease_contract_id = lc.id
                       AND lc.store_id IN (SELECT id FROM _kept_stores)
                       AND (t.store_id IN (SELECT id FROM _extra_stores) OR t.store_id IS NULL)';
            GET DIAGNOSTICS n = ROW_COUNT;
            IF n > 0 THEN
                RAISE NOTICE 'Reassigned % lease transactions of kept lease contracts to contract store.', n;
            END IF;
        END IF;

        IF to_regclass('himoto.lease_payment_allocations') IS NOT NULL THEN
            UPDATE himoto.transactions t
            SET store_id = lc.store_id, updated_at = now()
            FROM himoto.lease_payment_allocations lpa
            JOIN himoto.lease_contracts lc ON lc.id = lpa.lease_contract_id
            WHERE (t.id = lpa.transaction_id OR t.id = lpa.reversal_transaction_id)
              AND lc.store_id IN (SELECT id FROM _kept_stores)
              AND (t.store_id IN (SELECT id FROM _extra_stores) OR t.store_id IS NULL);
            GET DIAGNOSTICS n = ROW_COUNT;
            IF n > 0 THEN
                RAISE NOTICE 'Reassigned % allocated lease payment transactions to contract store.', n;
            END IF;
        END IF;
    END IF;

    -- 3. Lease contracts origin_store_id & ownership requests:
    IF to_regclass('himoto.lease_contracts') IS NOT NULL THEN
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='lease_contracts' AND column_name='origin_store_id') THEN
            EXECUTE 'UPDATE himoto.lease_contracts lc
                     SET origin_store_id = lc.store_id, updated_at = now()
                     WHERE lc.store_id IN (SELECT id FROM _kept_stores)
                       AND lc.origin_store_id IN (SELECT id FROM _extra_stores)';
            GET DIAGNOSTICS n = ROW_COUNT;
            IF n > 0 THEN
                RAISE NOTICE 'Reassigned % kept lease contracts origin_store_id to contract store.', n;
            END IF;
        END IF;
    END IF;

    IF to_regclass('himoto.lease_ownership_requests') IS NOT NULL AND to_regclass('himoto.lease_contracts') IS NOT NULL THEN
        UPDATE himoto.lease_ownership_requests lor
        SET store_id = lc.store_id, updated_at = now()
        FROM himoto.lease_contracts lc
        WHERE lor.lease_contract_id = lc.id
          AND lc.store_id IN (SELECT id FROM _kept_stores)
          AND lor.store_id IN (SELECT id FROM _extra_stores);
    END IF;

    -- 4. Preserve banks and cash referenced by kept transactions:
    IF to_regclass('himoto.banks') IS NOT NULL AND to_regclass('himoto.transactions') IS NOT NULL THEN
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='transactions' AND column_name='bank_id') THEN
            EXECUTE 'UPDATE himoto.banks b
                     SET store_id = t.store_id, updated_at = now()
                     FROM himoto.transactions t
                     WHERE b.id = t.bank_id
                       AND b.store_id IN (SELECT id FROM _extra_stores)
                       AND t.store_id IN (SELECT id FROM _kept_stores)';
            GET DIAGNOSTICS n = ROW_COUNT;
            IF n > 0 THEN
                RAISE NOTICE 'Reassigned % bank accounts used by kept transactions to kept stores.', n;
            END IF;
        END IF;
    END IF;

    IF to_regclass('himoto.cash') IS NOT NULL AND to_regclass('himoto.transactions') IS NOT NULL THEN
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='transactions' AND column_name='cash_id') THEN
            EXECUTE 'UPDATE himoto.cash c
                     SET store_id = t.store_id, updated_at = now()
                     FROM himoto.transactions t
                     WHERE c.id = t.cash_id
                       AND c.store_id IN (SELECT id FROM _extra_stores)
                       AND t.store_id IN (SELECT id FROM _kept_stores)';
            GET DIAGNOSTICS n = ROW_COUNT;
            IF n > 0 THEN
                RAISE NOTICE 'Reassigned % cash accounts used by kept transactions to kept stores.', n;
            END IF;
        END IF;
    END IF;

    -- 5. Preserve vehicles used in kept orders and kept lease contracts:
    IF to_regclass('himoto.vehicles') IS NOT NULL THEN
        IF to_regclass('himoto.order_vehicle_details') IS NOT NULL AND to_regclass('himoto.orders') IS NOT NULL THEN
            IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='vehicles' AND column_name='current_store_id') THEN
                EXECUTE 'UPDATE himoto.vehicles v
                         SET store_id = o.store_id,
                             current_store_id = CASE WHEN v.current_store_id IN (SELECT id FROM _extra_stores) THEN o.store_id ELSE COALESCE(v.current_store_id, o.store_id) END,
                             updated_at = now()
                         FROM himoto.order_vehicle_details ovd
                         JOIN himoto.orders o ON o.id = ovd.order_id
                         WHERE v.id = ovd.vehicle_id
                           AND o.store_id IN (SELECT id FROM _kept_stores)
                           AND (v.store_id IN (SELECT id FROM _extra_stores) OR v.current_store_id IN (SELECT id FROM _extra_stores))';
            ELSE
                EXECUTE 'UPDATE himoto.vehicles v
                         SET store_id = o.store_id, updated_at = now()
                         FROM himoto.order_vehicle_details ovd
                         JOIN himoto.orders o ON o.id = ovd.order_id
                         WHERE v.id = ovd.vehicle_id
                           AND o.store_id IN (SELECT id FROM _kept_stores)
                           AND v.store_id IN (SELECT id FROM _extra_stores)';
            END IF;
            GET DIAGNOSTICS n = ROW_COUNT;
            IF n > 0 THEN
                RAISE NOTICE 'Preserved % vehicles used in kept orders.', n;
            END IF;
        END IF;

        IF to_regclass('himoto.orders') IS NOT NULL AND EXISTS (
            SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='orders' AND column_name='vehicle_ids'
        ) THEN
            IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='vehicles' AND column_name='current_store_id') THEN
                EXECUTE 'UPDATE himoto.vehicles v
                         SET store_id = o.store_id,
                             current_store_id = CASE WHEN v.current_store_id IN (SELECT id FROM _extra_stores) THEN o.store_id ELSE COALESCE(v.current_store_id, o.store_id) END,
                             updated_at = now()
                         FROM himoto.orders o
                         WHERE o.store_id IN (SELECT id FROM _kept_stores)
                           AND o.vehicle_ids IS NOT NULL
                           AND o.vehicle_ids ~ (''(^|[^0-9])'' || v.id::text || ''([^0-9]|$)'')
                           AND (v.store_id IN (SELECT id FROM _extra_stores) OR v.current_store_id IN (SELECT id FROM _extra_stores))';
            ELSE
                EXECUTE 'UPDATE himoto.vehicles v
                         SET store_id = o.store_id, updated_at = now()
                         FROM himoto.orders o
                         WHERE o.store_id IN (SELECT id FROM _kept_stores)
                           AND o.vehicle_ids IS NOT NULL
                           AND o.vehicle_ids ~ (''(^|[^0-9])'' || v.id::text || ''([^0-9]|$)'')
                           AND v.store_id IN (SELECT id FROM _extra_stores)';
            END IF;
            GET DIAGNOSTICS n = ROW_COUNT;
            IF n > 0 THEN
                RAISE NOTICE 'Preserved % vehicles listed in kept legacy orders.', n;
            END IF;
        END IF;

        IF to_regclass('himoto.lease_contracts') IS NOT NULL THEN
            IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='vehicles' AND column_name='current_store_id') THEN
                EXECUTE 'UPDATE himoto.vehicles v
                         SET store_id = lc.store_id,
                             current_store_id = CASE WHEN v.current_store_id IN (SELECT id FROM _extra_stores) THEN lc.store_id ELSE COALESCE(v.current_store_id, lc.store_id) END,
                             updated_at = now()
                         FROM himoto.lease_contracts lc
                         WHERE v.id = lc.vehicle_id
                           AND lc.store_id IN (SELECT id FROM _kept_stores)
                           AND (v.store_id IN (SELECT id FROM _extra_stores) OR v.current_store_id IN (SELECT id FROM _extra_stores))';
            ELSE
                EXECUTE 'UPDATE himoto.vehicles v
                         SET store_id = lc.store_id, updated_at = now()
                         FROM himoto.lease_contracts lc
                         WHERE v.id = lc.vehicle_id
                           AND lc.store_id IN (SELECT id FROM _kept_stores)
                           AND v.store_id IN (SELECT id FROM _extra_stores)';
            END IF;
            GET DIAGNOSTICS n = ROW_COUNT;
            IF n > 0 THEN
                RAISE NOTICE 'Preserved % vehicles in kept lease contracts.', n;
            END IF;
        END IF;

        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='vehicles' AND column_name='current_store_id') THEN
            EXECUTE 'UPDATE himoto.vehicles v
                     SET current_store_id = v.store_id, updated_at = now()
                     WHERE v.store_id IN (SELECT id FROM _kept_stores)
                       AND v.current_store_id IN (SELECT id FROM _extra_stores)';
            GET DIAGNOSTICS n = ROW_COUNT;
            IF n > 0 THEN
                RAISE NOTICE 'Fixed current_store_id for % vehicles of kept stores.', n;
            END IF;
        END IF;
    END IF;

    -- 6. Accounting documents linked to kept transactions:
    IF to_regclass('himoto.accounting_vat_documents') IS NOT NULL AND to_regclass('himoto.transactions') IS NOT NULL THEN
        UPDATE himoto.accounting_vat_documents doc
        SET store_id = t.store_id, updated_at = now()
        FROM himoto.transactions t
        WHERE doc.transaction_id = t.id
          AND t.store_id IN (SELECT id FROM _kept_stores)
          AND doc.store_id IN (SELECT id FROM _extra_stores);
    END IF;

    IF to_regclass('himoto.journal_entries') IS NOT NULL AND to_regclass('himoto.transactions') IS NOT NULL THEN
        UPDATE himoto.journal_entries je
        SET store_id = t.store_id, updated_at = now()
        FROM himoto.transactions t
        WHERE je.source_type = 'transaction' AND je.source_id = t.id
          AND t.store_id IN (SELECT id FROM _kept_stores)
          AND je.store_id IN (SELECT id FROM _extra_stores);
    END IF;
END;
$reassign_kept_contracts_finances$;

-- The owner approved deleting every non-selected store row, including old
-- duplicate-location rows such as store id 3 ("02 Hàng Bút"). Cross-store
-- contract and financial integrity checks below still abort on conflicts.

CREATE TEMP TABLE _purge_rows (
    table_name text NOT NULL,
    id bigint NOT NULL,
    PRIMARY KEY (table_name, id)
) ON COMMIT DROP;

-- Every himoto table directly carrying an old store ID is in scope. Users are
-- handled separately so a staff account is not silently deleted with its shop.
DO $capture_direct$
DECLARE c record; matches bigint;
BEGIN
    FOR c IN
        SELECT table_name, column_name
        FROM information_schema.columns
        WHERE table_schema = 'himoto'
          AND column_name IN ('store_id','current_store_id','origin_store_id','from_store_id','to_store_id')
          AND table_name NOT IN ('stores','users')
        ORDER BY table_name, column_name
    LOOP
        EXECUTE format('SELECT count(*) FROM himoto.%I WHERE %I IN (SELECT id FROM _extra_stores)', c.table_name, c.column_name)
            INTO matches;
        IF matches > 0 AND NOT EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema='himoto' AND table_name=c.table_name AND column_name='id'
        ) THEN
            RAISE EXCEPTION '%.% has old-store data but no id column; refusing an incomplete purge.', c.table_name, c.column_name;
        END IF;
        IF matches > 0 THEN
            IF c.table_name = 'transactions' AND to_regclass('himoto.orders') IS NOT NULL THEN
                EXECUTE format(
                    'INSERT INTO _purge_rows (table_name,id) ' ||
                    'SELECT %L, t.id FROM himoto.transactions t ' ||
                    'WHERE t.%I IN (SELECT id FROM _extra_stores) ' ||
                    '  AND NOT (t.order_id IS NOT NULL AND EXISTS (SELECT 1 FROM himoto.orders o WHERE o.id = t.order_id AND o.store_id IN (SELECT id FROM _kept_stores))) ' ||
                    'ON CONFLICT DO NOTHING',
                    c.table_name, c.column_name
                );
            ELSE
                EXECUTE format(
                    'INSERT INTO _purge_rows (table_name,id) SELECT %L,id FROM himoto.%I WHERE %I IN (SELECT id FROM _extra_stores) ON CONFLICT DO NOTHING',
                    c.table_name, c.table_name, c.column_name
                );
            END IF;
        END IF;
    END LOOP;
END;
$capture_direct$;

-- Legacy cart rows sometimes carry a shop name rather than a numeric store_id.
DO $capture_legacy_cart$
BEGIN
    IF to_regclass('himoto.cart') IS NOT NULL AND EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema='himoto' AND table_name='cart' AND column_name='shop'
    ) THEN
        INSERT INTO _purge_rows(table_name,id)
        SELECT 'cart', c.id FROM himoto.cart c
        JOIN himoto.stores s ON s.id IN (SELECT id FROM _extra_stores)
          AND lower(btrim(c.shop)) IN (
              lower(btrim(s.store_name)), lower(btrim(s.store_address)), lower(btrim(s.code))
          )
        ON CONFLICT DO NOTHING;
    END IF;
END;
$capture_legacy_cart$;

-- Ownership edges from the repository schema. Actor/user references are
-- deliberately excluded: they do not imply ownership by the staff member's
-- currently assigned store. Absent optional tables/columns are skipped.
CREATE TEMP TABLE _purge_edges (
    child_table text NOT NULL,
    child_column text NOT NULL,
    parent_table text NOT NULL
) ON COMMIT DROP;

INSERT INTO _purge_edges VALUES
 ('order_vehicle_details','order_id','orders'),
 ('order_vehicle_details','vehicle_id','vehicles'),
 ('transactions','order_id','orders'),
 ('transactions','order_item_id','order_vehicle_details'),
 ('transactions','bank_id','banks'),
 ('transactions','cash_id','cash'),
 ('activity_logs','order_id','orders'),
 ('activity_logs','transaction_id','transactions'),
 ('add_on_orders','order_id','orders'),
 ('order_fees','order_id','orders'),
 ('order_fees','order_item_id','order_vehicle_details'),
 ('order_add','id_order','orders'),
 ('order_add','id_xe','vehicles'),
 ('warning','id_order','orders'),
 ('contract_amendments','order_id','orders'),
 ('contract_amendments','old_vehicle_id','vehicles'),
 ('contract_amendments','new_vehicle_id','vehicles'),
 ('vehicle_transfers','order_id','orders'),
 ('vehicle_transfer_items','transfer_id','vehicle_transfers'),
 ('vehicle_transfer_items','vehicle_id','vehicles'),
 ('vehicle_location_events','vehicle_id','vehicles'),
 ('maintenance','vehicle_id','vehicles'),
 ('maintenance_log','vehicle_id','vehicles'),
 ('maintenance_schedules','vehicle_id','vehicles'),
 ('vehicle_images','vehicle_id','vehicles'),
 ('cart','id_xe','vehicles'),
 ('gps_devices','vehicle_id','vehicles'),
 ('gps_positions','gps_device_id','gps_devices'),
 ('gps_alerts','gps_device_id','gps_devices'),
 ('gps_alerts','vehicle_id','vehicles'),
 ('gps_recovery_actions','gps_device_id','gps_devices'),
 ('gps_recovery_actions','vehicle_id','vehicles'),
 ('sell_order_items','order_id','sell_orders'),
 ('sell_order_items','vehicle_id','vehicles'),
 ('lease_contracts','vehicle_id','vehicles'),
 ('lease_installments','lease_contract_id','lease_contracts'),
 ('lease_payment_allocations','lease_contract_id','lease_contracts'),
 ('lease_payment_allocations','installment_id','lease_installments'),
 ('lease_payment_allocations','transaction_id','transactions'),
 ('lease_payment_allocations','reversal_transaction_id','transactions'),
 ('lease_payment_requests','lease_contract_id','lease_contracts'),
 ('debt_notes','lease_contract_id','lease_contracts'),
 ('lease_ownership_requests','lease_contract_id','lease_contracts'),
 ('lease_ownership_requests','vehicle_id','vehicles'),
 ('lease_ownership_events','ownership_request_id','lease_ownership_requests'),
 ('vehicle_ownerships','ownership_request_id','lease_ownership_requests'),
 ('vehicle_ownerships','lease_contract_id','lease_contracts'),
 ('vehicle_ownerships','vehicle_id','vehicles'),
 ('lead_logs','lead_id','leads'),
 ('leads','order_id','orders'),
 ('leads','vehicle_id','vehicles'),
 ('store_duty_schedules','staff_id','staff_profiles'),
 ('staff_attendances','staff_id','staff_profiles'),
 ('journal_lines','journal_entry_id','journal_entries'),
 ('journal_lines','account_id','accounting_accounts'),
 ('journal_entries','reversed_entry_id','journal_entries'),
 ('accounting_vat_documents','transaction_id','transactions'),
 ('reminder_contact_logs','reminder_id','customer_reminder_outbox'),
 ('reminder_delivery_events','outbox_id','customer_reminder_outbox');

-- Also include actual single-column foreign keys that may have been added in
-- Supabase but are not part of the repository migrations.
INSERT INTO _purge_edges (child_table, child_column, parent_table)
SELECT child.relname, child_col.attname, parent.relname
FROM pg_constraint fk
JOIN pg_class child ON child.oid = fk.conrelid
JOIN pg_namespace child_ns ON child_ns.oid = child.relnamespace
JOIN pg_class parent ON parent.oid = fk.confrelid
JOIN pg_namespace parent_ns ON parent_ns.oid = parent.relnamespace
JOIN pg_attribute child_col ON child_col.attrelid = child.oid AND child_col.attnum = fk.conkey[1]
JOIN pg_attribute parent_col ON parent_col.attrelid = parent.oid AND parent_col.attnum = fk.confkey[1]
WHERE fk.contype = 'f' AND array_length(fk.conkey, 1) = 1
  AND child_ns.nspname = 'himoto' AND parent_ns.nspname = 'himoto'
  AND parent_col.attname = 'id' AND child.relname <> 'users';

DO $capture_children$
DECLARE e record; column_type text; predicate text; added integer; n integer;
BEGIN
    LOOP
        added := 0;
        FOR e IN SELECT DISTINCT child_table, child_column, parent_table FROM _purge_edges LOOP
            IF to_regclass(format('himoto.%I', e.child_table)) IS NULL
               OR to_regclass(format('himoto.%I', e.parent_table)) IS NULL THEN
                CONTINUE;
            END IF;
            SELECT data_type INTO column_type FROM information_schema.columns
            WHERE table_schema='himoto' AND table_name=e.child_table AND column_name=e.child_column;
            IF column_type IS NULL THEN CONTINUE; END IF;
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                           WHERE table_schema='himoto' AND table_name=e.child_table AND column_name='id') THEN
                RAISE EXCEPTION 'Linked table % has no id column; refusing incomplete purge.', e.child_table;
            END IF;
            predicate := CASE WHEN column_type IN ('text','character varying','character')
                         THEN format('c.%I = p.id::text', e.child_column)
                         ELSE format('c.%I = p.id', e.child_column) END;
            EXECUTE format(
                'INSERT INTO _purge_rows(table_name,id) SELECT %L,c.id FROM himoto.%I c JOIN _purge_rows p ON p.table_name=%L AND %s ON CONFLICT DO NOTHING',
                e.child_table, e.child_table, e.parent_table, predicate
            );
            GET DIAGNOSTICS n = ROW_COUNT;
            added := added + n;
        END LOOP;

        IF to_regclass('himoto.customer_reminder_outbox') IS NOT NULL THEN
            INSERT INTO _purge_rows(table_name,id)
            SELECT 'customer_reminder_outbox', r.id
            FROM himoto.customer_reminder_outbox r
            WHERE (r.contract_type IN ('rental','rental_order') AND EXISTS (
                    SELECT 1 FROM _purge_rows p WHERE p.table_name='orders' AND p.id=r.contract_id))
               OR (r.contract_type = 'lease' AND EXISTS (
                    SELECT 1 FROM _purge_rows p WHERE p.table_name='lease_contracts' AND p.id=r.contract_id))
            ON CONFLICT DO NOTHING;
            GET DIAGNOSTICS n = ROW_COUNT;
            added := added + n;
        END IF;
        IF to_regclass('himoto.journal_entries') IS NOT NULL THEN
            INSERT INTO _purge_rows(table_name,id)
            SELECT 'journal_entries', j.id
            FROM himoto.journal_entries j
            WHERE (j.source_type='transaction' AND EXISTS (
                    SELECT 1 FROM _purge_rows p WHERE p.table_name='transactions' AND p.id=j.source_id))
               OR (j.source_type='lease_contract' AND EXISTS (
                    SELECT 1 FROM _purge_rows p WHERE p.table_name='lease_contracts' AND p.id=j.source_id))
               OR (j.source_type='vat_document' AND EXISTS (
                    SELECT 1 FROM _purge_rows p WHERE p.table_name='accounting_vat_documents' AND p.id=j.source_id))
               OR (j.source_type='business_asset' AND EXISTS (
                    SELECT 1 FROM _purge_rows p WHERE p.table_name='business_assets' AND p.id=j.source_id))
            ON CONFLICT DO NOTHING;
            GET DIAGNOSTICS n = ROW_COUNT;
            added := added + n;
        END IF;
        EXIT WHEN added = 0;
    END LOOP;
END;
$capture_children$;

-- Shared-customer candidates are identified before their orders are removed.
CREATE TEMP TABLE _candidate_customers (id bigint PRIMARY KEY) ON COMMIT DROP;
DO $candidate_customers$
DECLARE c record;
BEGIN
    FOR c IN SELECT table_name FROM information_schema.columns
             WHERE table_schema='himoto' AND column_name='customer_id' AND table_name <> 'customers'
    LOOP
        IF EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_schema='himoto' AND table_name=c.table_name AND column_name='id') THEN
            EXECUTE format(
                'INSERT INTO _candidate_customers(id) SELECT DISTINCT t.customer_id FROM himoto.%I t JOIN _purge_rows p ON p.table_name=%L AND p.id=t.id WHERE t.customer_id IS NOT NULL ON CONFLICT DO NOTHING',
                c.table_name, c.table_name
            );
        END IF;
    END LOOP;
END;
$candidate_customers$;

-- File metadata is warehouse-owned only when every vehicle-image link to it
-- is removed. The remote object itself, if any, is outside PostgreSQL.
CREATE TEMP TABLE _candidate_files (id bigint PRIMARY KEY) ON COMMIT DROP;
DO $candidate_files$
BEGIN
    IF to_regclass('himoto.vehicle_images') IS NOT NULL
       AND to_regclass('himoto.files') IS NOT NULL THEN
        INSERT INTO _candidate_files(id)
        SELECT DISTINCT v.file_id FROM himoto.vehicle_images v
        JOIN _purge_rows p ON p.table_name='vehicle_images' AND p.id=v.id
        WHERE v.file_id IS NOT NULL
        ON CONFLICT DO NOTHING;
    END IF;
END;
$candidate_files$;

-- Rows linked to removed-store data are removed even when their store_id
-- points at a kept store (explicitly approved for the three transactions).
-- Still refuse to leave a surviving contract/financial aggregate incomplete.
DO $cross_store_guard$
DECLARE e record; n bigint; target_count bigint; column_type text;
BEGIN
    SELECT count(*) INTO target_count FROM _purge_rows;
    RAISE NOTICE 'Extra stores: %; directly/indirectly linked rows selected: %',
        (SELECT count(*) FROM _extra_stores), target_count;
    SELECT count(*) INTO n FROM himoto.transactions tx
    JOIN _purge_rows p ON p.table_name='transactions' AND p.id=tx.id
    WHERE tx.store_id IN (SELECT id FROM _kept_stores);
    RAISE NOTICE 'Transactions recorded under kept stores but linked to removed data: %', n;

    -- A selected child must not silently strip history from a surviving
    -- contract, transfer, accounting entry or other business aggregate.
    FOR e IN SELECT DISTINCT child_table, child_column, parent_table
             FROM _purge_edges
             WHERE parent_table IN (
                 'orders','sell_orders','lease_contracts','lease_installments',
                 'transactions','order_vehicle_details','journal_entries',
                 'vehicle_transfers','lease_ownership_requests',
                 'customer_reminder_outbox','leads','staff_profiles'
             )
    LOOP
        IF to_regclass(format('himoto.%I', e.child_table)) IS NULL
           OR to_regclass(format('himoto.%I', e.parent_table)) IS NULL
           OR NOT EXISTS (SELECT 1 FROM information_schema.columns
                          WHERE table_schema='himoto' AND table_name=e.child_table
                            AND column_name=e.child_column) THEN
            CONTINUE;
        END IF;
        SELECT data_type INTO column_type FROM information_schema.columns
        WHERE table_schema='himoto' AND table_name=e.child_table
          AND column_name=e.child_column;
        EXECUTE format(
            'SELECT count(*) FROM himoto.%I child JOIN _purge_rows selected ON selected.table_name=%L AND selected.id=child.id JOIN himoto.%I parent ON %s WHERE NOT EXISTS (SELECT 1 FROM _purge_rows p WHERE p.table_name=%L AND p.id=parent.id)',
            e.child_table, e.child_table, e.parent_table,
            CASE WHEN column_type IN ('text','character varying','character')
                 THEN format('child.%I = parent.id::text', e.child_column)
                 ELSE format('child.%I = parent.id', e.child_column) END,
            e.parent_table
        ) INTO n;
        IF n > 0 THEN
            -- First, reassign child store_id to parent store_id if both tables have store_id
            IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name=e.child_table AND column_name='store_id')
               AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name=e.parent_table AND column_name='store_id') THEN
                EXECUTE format(
                    'UPDATE himoto.%I c SET store_id = p.store_id, updated_at = now() ' ||
                    'FROM himoto.%I p ' ||
                    'WHERE %s ' ||
                    '  AND EXISTS (SELECT 1 FROM _purge_rows sel WHERE sel.table_name=%L AND sel.id=c.id) ' ||
                    '  AND NOT EXISTS (SELECT 1 FROM _purge_rows pp WHERE pp.table_name=%L AND pp.id=p.id) ' ||
                    '  AND (c.store_id IN (SELECT id FROM _extra_stores) OR c.store_id IS NULL)',
                    e.child_table, e.parent_table,
                    CASE WHEN column_type IN ('text','character varying','character')
                         THEN format('c.%I = p.id::text', e.child_column)
                         ELSE format('c.%I = p.id', e.child_column) END,
                    e.child_table, e.parent_table
                );
            END IF;

            -- Unmark these child rows from _purge_rows so they are retained with their kept parent
            EXECUTE format(
                'DELETE FROM _purge_rows ' ||
                'WHERE table_name = %L ' ||
                '  AND id IN ( ' ||
                '      SELECT child.id FROM himoto.%I child ' ||
                '      JOIN himoto.%I parent ON %s ' ||
                '      WHERE NOT EXISTS (SELECT 1 FROM _purge_rows p WHERE p.table_name=%L AND p.id=parent.id) ' ||
                '  )',
                e.child_table, e.child_table, e.parent_table,
                CASE WHEN column_type IN ('text','character varying','character')
                     THEN format('child.%I = parent.id::text', e.child_column)
                     ELSE format('child.%I = parent.id', e.child_column) END,
                e.parent_table
            );

            RAISE NOTICE '% selected %.% rows belong to kept %: retained and protected from deletion.',
                n, e.child_table, e.child_column, e.parent_table;
        END IF;
    END LOOP;

    IF to_regclass('himoto.order_vehicle_details') IS NOT NULL AND to_regclass('himoto.orders') IS NOT NULL THEN
        SELECT count(*) INTO n FROM himoto.order_vehicle_details d
        JOIN _purge_rows p ON p.table_name='order_vehicle_details' AND p.id=d.id
        JOIN himoto.orders o ON o.id=d.order_id
        WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='orders' AND keep_parent.id=o.id);
        IF n > 0 THEN
            DELETE FROM _purge_rows WHERE table_name='order_vehicle_details' AND id IN (
                SELECT d.id FROM himoto.order_vehicle_details d
                JOIN himoto.orders o ON o.id=d.order_id
                WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='orders' AND keep_parent.id=o.id)
            );
            RAISE NOTICE '% kept orders items retained and protected from deletion.', n;
        END IF;
    END IF;

    IF to_regclass('himoto.orders') IS NOT NULL AND EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema='himoto' AND table_name='orders' AND column_name='vehicle_ids'
    ) THEN
        SELECT count(*) INTO n FROM himoto.orders o
        JOIN _purge_rows v ON v.table_name='vehicles'
          AND o.vehicle_ids ~ ('(^|[^0-9])' || v.id::text || '([^0-9]|$)')
        WHERE o.vehicle_ids IS NOT NULL
          AND NOT EXISTS (SELECT 1 FROM _purge_rows p WHERE p.table_name='orders' AND p.id=o.id);
        IF n > 0 THEN
            IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='himoto' AND table_name='vehicles' AND column_name='current_store_id') THEN
                EXECUTE 'UPDATE himoto.vehicles v
                         SET store_id = o.store_id,
                             current_store_id = CASE WHEN v.current_store_id IN (SELECT id FROM _extra_stores) THEN o.store_id ELSE COALESCE(v.current_store_id, o.store_id) END,
                             updated_at = now()
                         FROM himoto.orders o
                         WHERE o.vehicle_ids IS NOT NULL
                           AND o.vehicle_ids ~ (''(^|[^0-9])'' || v.id::text || ''([^0-9]|$)'')
                           AND NOT EXISTS (SELECT 1 FROM _purge_rows p WHERE p.table_name=''orders'' AND p.id=o.id)
                           AND (v.store_id IN (SELECT id FROM _extra_stores) OR v.current_store_id IN (SELECT id FROM _extra_stores))';
            ELSE
                EXECUTE 'UPDATE himoto.vehicles v
                         SET store_id = o.store_id, updated_at = now()
                         FROM himoto.orders o
                         WHERE o.vehicle_ids IS NOT NULL
                           AND o.vehicle_ids ~ (''(^|[^0-9])'' || v.id::text || ''([^0-9]|$)'')
                           AND NOT EXISTS (SELECT 1 FROM _purge_rows p WHERE p.table_name=''orders'' AND p.id=o.id)
                           AND v.store_id IN (SELECT id FROM _extra_stores)';
            END IF;

            DELETE FROM _purge_rows WHERE table_name='vehicles' AND id IN (
                SELECT v.id FROM himoto.vehicles v
                JOIN himoto.orders o ON o.vehicle_ids IS NOT NULL AND o.vehicle_ids ~ ('(^|[^0-9])' || v.id::text || '([^0-9]|$)')
                WHERE NOT EXISTS (SELECT 1 FROM _purge_rows p WHERE p.table_name='orders' AND p.id=o.id)
            );
            RAISE NOTICE '% vehicles referenced by kept legacy orders retained and protected from deletion.', n;
        END IF;
    END IF;

    IF to_regclass('himoto.sell_order_items') IS NOT NULL AND to_regclass('himoto.sell_orders') IS NOT NULL THEN
        SELECT count(*) INTO n FROM himoto.sell_order_items d
        JOIN _purge_rows p ON p.table_name='sell_order_items' AND p.id=d.id
        JOIN himoto.sell_orders o ON o.id=d.order_id
        WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='sell_orders' AND keep_parent.id=o.id);
        IF n > 0 THEN
            DELETE FROM _purge_rows WHERE table_name='sell_order_items' AND id IN (
                SELECT d.id FROM himoto.sell_order_items d
                JOIN himoto.sell_orders o ON o.id=d.order_id
                WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='sell_orders' AND keep_parent.id=o.id)
            );
            RAISE NOTICE '% kept sale orders items retained and protected from deletion.', n;
        END IF;
    END IF;

    IF to_regclass('himoto.transactions') IS NOT NULL AND to_regclass('himoto.orders') IS NOT NULL THEN
        SELECT count(*) INTO n FROM himoto.transactions d
        JOIN _purge_rows p ON p.table_name='transactions' AND p.id=d.id
        JOIN himoto.orders o ON o.id=d.order_id
        WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='orders' AND keep_parent.id=o.id);
        IF n > 0 THEN
            UPDATE himoto.transactions d
            SET store_id = o.store_id, updated_at = now()
            FROM himoto.orders o
            WHERE d.order_id = o.id
              AND NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='orders' AND keep_parent.id=o.id)
              AND (d.store_id IN (SELECT id FROM _extra_stores) OR d.store_id IS NULL);

            DELETE FROM _purge_rows WHERE table_name='transactions' AND id IN (
                SELECT d.id FROM himoto.transactions d
                JOIN himoto.orders o ON o.id=d.order_id
                WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='orders' AND keep_parent.id=o.id)
            );
            RAISE NOTICE '% kept orders transactions retained and protected from deletion.', n;
        END IF;
    END IF;

    IF to_regclass('himoto.lease_payment_allocations') IS NOT NULL AND to_regclass('himoto.lease_contracts') IS NOT NULL THEN
        SELECT count(*) INTO n FROM himoto.lease_payment_allocations d
        JOIN _purge_rows p ON p.table_name='lease_payment_allocations' AND p.id=d.id
        JOIN himoto.lease_contracts o ON o.id=d.lease_contract_id
        WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='lease_contracts' AND keep_parent.id=o.id);
        IF n > 0 THEN
            DELETE FROM _purge_rows WHERE table_name='lease_payment_allocations' AND id IN (
                SELECT d.id FROM himoto.lease_payment_allocations d
                JOIN himoto.lease_contracts o ON o.id=d.lease_contract_id
                WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='lease_contracts' AND keep_parent.id=o.id)
            );
            RAISE NOTICE '% kept lease payments retained and protected from deletion.', n;
        END IF;
    END IF;

    IF to_regclass('himoto.journal_lines') IS NOT NULL AND to_regclass('himoto.journal_entries') IS NOT NULL THEN
        SELECT count(*) INTO n FROM himoto.journal_lines d
        JOIN _purge_rows p ON p.table_name='journal_lines' AND p.id=d.id
        JOIN himoto.journal_entries o ON o.id=d.journal_entry_id
        WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='journal_entries' AND keep_parent.id=o.id);
        IF n > 0 THEN
            DELETE FROM _purge_rows WHERE table_name='journal_lines' AND id IN (
                SELECT d.id FROM himoto.journal_lines d
                JOIN himoto.journal_entries o ON o.id=d.journal_entry_id
                WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='journal_entries' AND keep_parent.id=o.id)
            );
            RAISE NOTICE '% kept journal entry lines retained and protected from deletion.', n;
        END IF;
    END IF;

    IF to_regclass('himoto.vehicle_transfer_items') IS NOT NULL AND to_regclass('himoto.vehicle_transfers') IS NOT NULL THEN
        SELECT count(*) INTO n FROM himoto.vehicle_transfer_items d
        JOIN _purge_rows p ON p.table_name='vehicle_transfer_items' AND p.id=d.id
        JOIN himoto.vehicle_transfers o ON o.id=d.transfer_id
        WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='vehicle_transfers' AND keep_parent.id=o.id);
        IF n > 0 THEN
            DELETE FROM _purge_rows WHERE table_name='vehicle_transfer_items' AND id IN (
                SELECT d.id FROM himoto.vehicle_transfer_items d
                JOIN himoto.vehicle_transfers o ON o.id=d.transfer_id
                WHERE NOT EXISTS (SELECT 1 FROM _purge_rows keep_parent WHERE keep_parent.table_name='vehicle_transfers' AND keep_parent.id=o.id)
            );
            RAISE NOTICE '% kept vehicle transfer items retained and protected from deletion.', n;
        END IF;
    END IF;

    IF EXISTS (
        SELECT 1 FROM pg_constraint fk JOIN pg_class parent ON parent.oid=fk.confrelid
        JOIN pg_namespace ns ON ns.oid=parent.relnamespace
        LEFT JOIN pg_attribute parent_col
            ON parent_col.attrelid=parent.oid AND parent_col.attnum=fk.confkey[1]
        WHERE fk.contype='f' AND ns.nspname='himoto'
          AND (array_length(fk.conkey,1) <> 1 OR parent_col.attname IS DISTINCT FROM 'id')
          AND EXISTS (SELECT 1 FROM _purge_rows p WHERE p.table_name=parent.relname)
    ) THEN
        RAISE EXCEPTION 'A target table has a composite/non-ID foreign key. Review it before purging.';
    END IF;
END;
$cross_store_guard$;

CREATE TEMP TABLE _deleted_counts (table_name text PRIMARY KEY, deleted bigint NOT NULL) ON COMMIT DROP;

-- Retry parents after their children. Unexpected FK constraints that cannot be
-- satisfied cause an exception and roll back the whole transaction.
DO $delete_selected$
DECLARE pass_no integer; t record; n bigint; removed_this_pass bigint; pending bigint; remaining_for_table bigint;
BEGIN
    FOR pass_no IN 1..100 LOOP
        removed_this_pass := 0;
        FOR t IN SELECT DISTINCT table_name FROM _purge_rows ORDER BY table_name LOOP
            n := 0;
            BEGIN
                EXECUTE format(
                    'DELETE FROM himoto.%I x USING _purge_rows p WHERE p.table_name=%L AND p.id=x.id',
                    t.table_name, t.table_name
                );
                GET DIAGNOSTICS n = ROW_COUNT;
            EXCEPTION WHEN foreign_key_violation THEN
                n := 0;
            END;
            IF n > 0 THEN
                INSERT INTO _deleted_counts(table_name,deleted) VALUES (t.table_name,n)
                ON CONFLICT (table_name) DO UPDATE
                    SET deleted = _deleted_counts.deleted + EXCLUDED.deleted;
                removed_this_pass := removed_this_pass + n;
            END IF;
        END LOOP;

        pending := 0;
        FOR t IN SELECT DISTINCT table_name FROM _purge_rows LOOP
            EXECUTE format(
                'SELECT count(*) FROM himoto.%I x JOIN _purge_rows p ON p.table_name=%L AND p.id=x.id',
                t.table_name, t.table_name
            ) INTO remaining_for_table;
            pending := pending + remaining_for_table;
        END LOOP;
        EXIT WHEN pending = 0;
        IF removed_this_pass = 0 THEN
            RAISE EXCEPTION '% selected rows cannot be deleted (foreign key or unsupported dependency). Entire purge rolled back.', pending;
        END IF;
    END LOOP;
    IF pending > 0 THEN RAISE EXCEPTION 'Purge exceeded 100 dependency passes. Entire purge rolled back.'; END IF;
END;
$delete_selected$;

-- Preserve shared people/account identities. An employee attached to a removed
-- branch cannot use that branch again until reassigned to CS1..CS6.
DO $detach_users$
DECLARE n bigint;
BEGIN
    IF to_regclass('himoto.users') IS NOT NULL AND EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema='himoto' AND table_name='users' AND column_name='store_id'
    ) THEN
        UPDATE himoto.users SET store_id=NULL, updated_at=now()
        WHERE store_id IN (SELECT id FROM _extra_stores);
        GET DIAGNOSTICS n = ROW_COUNT;
        RAISE NOTICE 'User accounts detached from deleted stores: %', n;
    END IF;
END;
$detach_users$;

-- Delete customer profiles that were used only by removed warehouse data.
CREATE TEMP TABLE _still_used_customers (id bigint PRIMARY KEY) ON COMMIT DROP;
DO $cleanup_customers$
DECLARE c record; n bigint;
BEGIN
    FOR c IN SELECT table_name FROM information_schema.columns
             WHERE table_schema='himoto' AND column_name='customer_id' AND table_name <> 'customers'
    LOOP
        EXECUTE format(
            'INSERT INTO _still_used_customers(id) SELECT DISTINCT t.customer_id FROM himoto.%I t JOIN _candidate_customers k ON k.id=t.customer_id WHERE t.customer_id IS NOT NULL ON CONFLICT DO NOTHING',
            c.table_name
        );
    END LOOP;
    IF to_regclass('himoto.customers') IS NOT NULL THEN
        DELETE FROM himoto.customers c
        WHERE c.id IN (SELECT id FROM _candidate_customers)
          AND c.id NOT IN (SELECT id FROM _still_used_customers);
        GET DIAGNOSTICS n = ROW_COUNT;
        INSERT INTO _deleted_counts(table_name,deleted) VALUES ('customers',n)
        ON CONFLICT (table_name) DO UPDATE SET deleted = _deleted_counts.deleted + EXCLUDED.deleted;
    END IF;
END;
$cleanup_customers$;

CREATE TEMP TABLE _still_used_files (id bigint PRIMARY KEY) ON COMMIT DROP;
DO $cleanup_files$
DECLARE c record; n bigint;
BEGIN
    FOR c IN SELECT table_name FROM information_schema.columns
             WHERE table_schema='himoto' AND column_name='file_id' AND table_name <> 'files'
    LOOP
        EXECUTE format(
            'INSERT INTO _still_used_files(id) SELECT DISTINCT t.file_id FROM himoto.%I t JOIN _candidate_files k ON k.id=t.file_id WHERE t.file_id IS NOT NULL ON CONFLICT DO NOTHING',
            c.table_name
        );
    END LOOP;
    IF to_regclass('himoto.files') IS NOT NULL THEN
        DELETE FROM himoto.files f
        WHERE f.id IN (SELECT id FROM _candidate_files)
          AND f.id NOT IN (SELECT id FROM _still_used_files);
        GET DIAGNOSTICS n = ROW_COUNT;
        INSERT INTO _deleted_counts(table_name,deleted) VALUES ('files',n)
        ON CONFLICT (table_name) DO UPDATE SET deleted = _deleted_counts.deleted + EXCLUDED.deleted;
    END IF;
END;
$cleanup_files$;

DELETE FROM himoto.stores WHERE id IN (SELECT id FROM _extra_stores);
INSERT INTO _deleted_counts(table_name,deleted) VALUES ('stores', (SELECT count(*) FROM _extra_stores));

DO $verify$
DECLARE c record; n bigint;
BEGIN
    IF (SELECT count(*) FROM himoto.stores) <> 6
       OR EXISTS (
           SELECT 1 FROM _wanted_stores d JOIN _kept_stores k ON k.code=d.code
           JOIN himoto.stores s ON s.id=k.id
           WHERE s.code <> d.code OR s.store_name <> d.store_name
              OR s.store_address <> d.store_address OR s.kind <> d.kind
       ) THEN
        RAISE EXCEPTION 'Final warehouse catalogue is not exactly CS1..CS6. Entire purge rolled back.';
    END IF;
    FOR c IN SELECT table_name, column_name FROM information_schema.columns
             WHERE table_schema='himoto'
               AND column_name IN ('store_id','current_store_id','origin_store_id','from_store_id','to_store_id')
               AND table_name <> 'stores'
    LOOP
        EXECUTE format('SELECT count(*) FROM himoto.%I WHERE %I IN (SELECT id FROM _extra_stores)', c.table_name, c.column_name)
            INTO n;
        IF n > 0 THEN RAISE EXCEPTION '%.% still has % deleted-store references. Entire purge rolled back.', c.table_name, c.column_name, n; END IF;
    END LOOP;
    RAISE NOTICE 'Verified: exactly six stores remain, with no direct references to deleted store IDs.';
    FOR c IN SELECT table_name, deleted FROM _deleted_counts ORDER BY table_name LOOP
        RAISE NOTICE 'Deleted %: %', c.table_name, c.deleted;
    END LOOP;
END;
$verify$;

SELECT table_name, deleted FROM _deleted_counts ORDER BY table_name;
SELECT id, code, store_name, store_address, kind FROM himoto.stores ORDER BY code;
COMMIT;
