-- Khớp sơ đồ Ban giám đốc ngày 10/09/2025 vào himoto.staff_profiles.
-- Chạy một lần trong SQL Editor sau khi đã backup. Script nằm trong transaction:
-- thiếu CS1..CS6 hoặc thiếu bảng nhân sự thì rollback toàn bộ.
--
-- Không đụng mật khẩu, email, vai trò hay trạng thái bảng users.
-- 20 tài khoản UAT đang active giữ nguyên.
-- user_id chỉ được gắn khi đúng một user có cùng số điện thoại.
-- Cơ sở Thuốc Bắc trên sơ đồ được gắn CS3 (02 Hàng Bút). Không đổi tên kho
-- vì catalog CS1..CS6 đang khóa tên và địa chỉ.
-- Nguyễn Như Hoài Linh là một người, xuất hiện ở ba ô: quản lý công nợ,
-- kế toán và leader thuê sở hữu.

BEGIN;

DO $guard$
BEGIN
    IF to_regclass('himoto.staff_profiles') IS NULL
       OR to_regclass('himoto.departments') IS NULL
       OR to_regclass('himoto.stores') IS NULL
       OR to_regclass('himoto.users') IS NULL THEN
        RAISE EXCEPTION 'Thiếu himoto.staff_profiles, departments, stores hoặc users.';
    END IF;
    IF (SELECT count(*) FROM himoto.stores WHERE code IN ('CS1','CS2','CS3','CS4','CS5','CS6')) <> 6 THEN
        RAISE EXCEPTION 'Cần đủ đúng một dòng cho mỗi mã CS1..CS6.';
    END IF;
END;
$guard$;

INSERT INTO himoto.departments (name, code, description, created_at, updated_at)
VALUES
    ('Vận hành', 'VH', 'Sơ đồ Ban giám đốc 10/09/2025', now(), now()),
    ('Hành chính nhân sự', 'HCNS', 'Sơ đồ Ban giám đốc 10/09/2025', now(), now()),
    ('Quản lý công nợ', 'QLCN', 'Sơ đồ Ban giám đốc 10/09/2025', now(), now()),
    ('Kế toán', 'KT', 'Sơ đồ Ban giám đốc 10/09/2025', now(), now()),
    ('BPSC', 'BPSC', 'Sơ đồ Ban giám đốc 10/09/2025', now(), now()),
    ('Telesales', 'TLS', 'Sơ đồ Ban giám đốc 10/09/2025', now(), now()),
    ('Kinh doanh cơ sở', 'KDCS', 'TPKD, NVKD và PT theo từng cơ sở', now(), now())
ON CONFLICT (code) DO UPDATE
SET name = EXCLUDED.name,
    description = EXCLUDED.description,
    updated_at = now();

CREATE TEMP TABLE _org_staff (
    staff_code text PRIMARY KEY,
    full_name text NOT NULL,
    phone text NOT NULL,
    alt_phone text,
    department_code text NOT NULL,
    position text NOT NULL,
    store_code text,
    notes text
) ON COMMIT DROP;

INSERT INTO _org_staff (staff_code, full_name, phone, alt_phone, department_code, position, store_code, notes)
VALUES
    ('ORG-VH-01', 'Phan Công Minh', '0342567705', NULL, 'VH', 'Vận hành', NULL, NULL),
    ('ORG-HCNS-01', 'Nguyễn Minh Tuấn', '0986268908', '0916683055', 'HCNS', 'HCNS', NULL, NULL),
    ('ORG-HCNS-02', 'Vũ Hồng Diệp', '0944280985', NULL, 'HCNS', 'HCNS', NULL, NULL),
    ('ORG-QLCN-01', 'Nguyễn Như Hoài Linh', '0988296110', NULL, 'QLCN', 'Leader', 'CS6',
        'Cùng một người: Leader quản lý công nợ, Kế toán, Leader thuê sở hữu.'),
    ('ORG-QLCN-02', 'Nguyễn Văn Nam', '09866547251', '0919594358', 'QLCN', 'Nhân viên', NULL,
        'Số chính trên sơ đồ có 11 chữ số.'),
    ('ORG-QLCN-03', 'Phan Đức Minh', '0982392644', '0915923055', 'QLCN', 'Nhân viên', NULL, NULL),
    ('ORG-BPSC-01', 'Đỗ Đức Hưng', '0968630185', NULL, 'BPSC', 'BPSC', NULL, NULL),
    ('ORG-TLS-01', 'Hoàng Thị Kim Huệ', '0364788339', '0886184116', 'TLS', 'Telesale', NULL, NULL),
    ('ORG-TLS-02', 'Nguyễn Như Thu Trang', '03347418746', '0855184116', 'TLS', 'Telesale', NULL,
        'Số chính trên sơ đồ có 11 chữ số.'),
    ('ORG-CS2-TPKD', 'Vi Văn Tượng', '0376654118', NULL, 'KDCS', 'TPKD', 'CS2', 'Cơ sở Nguyễn Hoàng.'),
    ('ORG-CS2-NVKD', 'Vũ Hoàng Minh', '0775284212', NULL, 'KDCS', 'NVKD', 'CS2', 'Cơ sở Nguyễn Hoàng.'),
    ('ORG-CS2-PT01', 'Kiều Phương Anh', '0325518898', NULL, 'KDCS', 'PT', 'CS2', 'Cơ sở Nguyễn Hoàng.'),
    ('ORG-CS2-PT02', 'Nguyễn Nhật Lil', '0706355781', NULL, 'KDCS', 'PT', 'CS2', 'Cơ sở Nguyễn Hoàng.'),
    ('ORG-CS1-TPKD', 'Nguyễn Đức Anh', '0395505622', NULL, 'KDCS', 'TPKD', 'CS1', 'Cơ sở Láng.'),
    ('ORG-CS1-NVKD', 'Nguyễn Minh Hiếu', '0394566430', NULL, 'KDCS', 'NVKD', 'CS1', 'Cơ sở Láng.'),
    ('ORG-CS1-PT', 'Hoàng Thị Yến Vi', '0348444989', NULL, 'KDCS', 'PT', 'CS1', 'Cơ sở Láng.'),
    ('ORG-CS3-TPKD', 'Nguyễn Hải Đăng', '0378784066', NULL, 'KDCS', 'TPKD', 'CS3',
        'Sơ đồ ghi Cơ sở Thuốc Bắc; gắn CS3 vì catalog đang để 02 Hàng Bút.'),
    ('ORG-CS3-NVKD', 'Nguyễn Quang Trường', '0325378569', NULL, 'KDCS', 'NVKD', 'CS3',
        'Sơ đồ ghi Cơ sở Thuốc Bắc; gắn CS3 vì catalog đang để 02 Hàng Bút.'),
    ('ORG-CS5-NVKD01', 'Trần Tấn Cảnh', '0974992405', NULL, 'KDCS', 'NVKD', 'CS5', 'Cơ sở Hà Đông.'),
    ('ORG-CS5-NVKD02', 'Phạm Quốc Cường', '0355403060', NULL, 'KDCS', 'NVKD', 'CS5', 'Cơ sở Hà Đông.'),
    ('ORG-CS5-PT', 'Đinh Ngọc Phụng', '0386125866', NULL, 'KDCS', 'PT', 'CS5', 'Cơ sở Hà Đông.'),
    ('ORG-CS4-TPKD', 'Nguyễn Thị Vui', '0396589623', NULL, 'KDCS', 'TPKD', 'CS4', 'Cơ sở Giáp Bát.'),
    ('ORG-CS4-NVKD01', 'Hà Viết Giang', '0333648392', NULL, 'KDCS', 'NVKD', 'CS4', 'Cơ sở Giáp Bát.'),
    ('ORG-CS4-NVKD02', 'Lê Duy Thủy', '0963165055', NULL, 'KDCS', 'NVKD', 'CS4', 'Cơ sở Giáp Bát.'),
    ('ORG-CS4-PT', 'Lê Trần Hiến', '0778430858', NULL, 'KDCS', 'PT', 'CS4', 'Cơ sở Giáp Bát.');

INSERT INTO himoto.staff_profiles (
    staff_code, full_name, phone, department_id, position, store_id, status, notes, created_at, updated_at
)
SELECT
    o.staff_code,
    o.full_name,
    o.phone,
    d.id,
    o.position,
    s.id,
    'active',
    concat_ws(' ',
        'Sơ đồ 10/09/2025.',
        o.notes,
        CASE WHEN o.alt_phone IS NOT NULL THEN 'SĐT phụ ' || o.alt_phone || '.' END
    ),
    now(),
    now()
FROM _org_staff o
JOIN himoto.departments d ON d.code = o.department_code
LEFT JOIN himoto.stores s ON s.code = o.store_code
ON CONFLICT (staff_code) DO UPDATE
SET full_name = EXCLUDED.full_name,
    phone = EXCLUDED.phone,
    department_id = EXCLUDED.department_id,
    position = EXCLUDED.position,
    store_id = EXCLUDED.store_id,
    status = EXCLUDED.status,
    notes = EXCLUDED.notes,
    updated_at = now();

DO $staff_count$
DECLARE total integer;
BEGIN
    SELECT count(*) INTO total FROM _org_staff;
    IF total <> 25 THEN
        RAISE EXCEPTION 'Sơ đồ phải có 25 người, đang có %.', total;
    END IF;
    IF EXISTS (
        SELECT 1 FROM _org_staff o
        LEFT JOIN himoto.staff_profiles sp ON sp.staff_code = o.staff_code
        WHERE sp.id IS NULL OR sp.full_name <> o.full_name
    ) THEN
        RAISE EXCEPTION 'Hồ sơ nhân sự chưa khớp danh sách sơ đồ.';
    END IF;
END;
$staff_count$;

-- Gắn tài khoản cũ chỉ khi số điện thoại trùng đúng một user.
-- Hiện chỉ Nguyễn Như Hoài Linh trùng user id 35 (tên lưu "Linh", đã ngừng).
UPDATE himoto.staff_profiles sp
SET user_id = matched.user_id,
    updated_at = now()
FROM (
    SELECT sp2.id AS profile_id, min(u.id) AS user_id
    FROM himoto.staff_profiles sp2
    JOIN himoto.users u
      ON regexp_replace(coalesce(u.phone, ''), '\D', '', 'g')
       = regexp_replace(sp2.phone, '\D', '', 'g')
    WHERE sp2.staff_code LIKE 'ORG-%'
      AND regexp_replace(sp2.phone, '\D', '', 'g') <> ''
    GROUP BY sp2.id
    HAVING count(*) = 1
) matched
WHERE sp.id = matched.profile_id;

-- Số cơ sở trên sơ đồ. CS4 đang lưu 0913361442; sơ đồ ghi 0865935833
-- và số phụ 0919233258 (cột store_phone chỉ chứa một số).
-- CS6 trên sơ đồ dùng cùng số với Láng: 0918669158.
UPDATE himoto.stores s
SET store_phone = v.phone,
    updated_at = now()
FROM (VALUES
    ('CS1', '0918669158'),
    ('CS2', '0921744886'),
    ('CS3', '0967568766'),
    ('CS4', '0865935833'),
    ('CS5', '0985081599'),
    ('CS6', '0918669158')
) AS v(code, phone)
WHERE s.code = v.code;

COMMIT;

SELECT sp.staff_code, sp.full_name, sp.phone, sp.position, d.code AS department,
       s.code AS store, sp.user_id, sp.status
FROM himoto.staff_profiles sp
JOIN himoto.departments d ON d.id = sp.department_id
LEFT JOIN himoto.stores s ON s.id = sp.store_id
WHERE sp.staff_code LIKE 'ORG-%'
ORDER BY sp.staff_code;
