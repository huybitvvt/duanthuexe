# HIMOTO Release Candidate Verification Summary (rc-a1a20bc)

- **Thời gian lập:** 17/09/2026
- **Branch:** `review/20260917-rc`
- **Base Commit SHA:** `a1a20bc` (`fix(himoto): refine sidebar toggle and schema verification`)
- **Trạng thái Git:** Local-only. **KHÔNG `git push`** theo chỉ thị chủ hệ thống.

---

## 1. Kết quả kiểm tra kỹ thuật (R0)

| Hạng mục kiểm tra | Lệnh thực hiện | Kết quả | Chi tiết file log |
|---|---|---|---|
| **PHPUnit Test Suite** | `.\php.cmd ..\tools\phpunit.phar` | **171 tests, 873 assertions (100% OK, 0 failures, 0 errors)** | [`phpunit.log`](./phpunit.log) |
| **Frontend Production Build** | `npm.cmd run production` | **PASS (exit code 0)** | [`npm-production.log`](./npm-production.log) |
| **Static Build** | `npm.cmd run build:static` | **PASS (exit code 0, static-dist/ generated)** | [`npm-build-static.log`](./npm-build-static.log) |
| **Git Diff Syntax & Whitespace** | `git diff --check` | **PASS (exit code 0, chỉ cảnh báo CRLF)** | [`git-diff-check.log`](./git-diff-check.log) |
| **Route List Integrity** | `.\php.cmd artisan route:list` | **PASS (189 routes nạp thành công, 0 lỗi runtime)** | [`route-list.log`](./route-list.log) |
| **Operational Schema Guard** | `.\php.cmd scripts/check_operational_schema.php` | **BLOCKED (chế độ chỉ đọc, không chạy DDL/DML)** | [`operational-schema-check.log`](./operational-schema-check.log) |
| **Danh sách thay đổi** | `git status` | **Sạch, rõ ràng, không lẫn file rác** | [`git-status.log`](./git-status.log) |

---

## 2. Trạng thái thực hiện các chặng theo Kế hoạch 17/09/2026

### Chặng R0 — Đóng băng bằng chứng và làm sạch release candidate
- **Trạng thái:** **HOÀN TẤT (PASS)**
- Toàn bộ bằng chứng test, build, route list, git diff được lưu trữ tại `docs/evidence/rc-a1a20bc/`.
- Không chứa mật khẩu, token, DSN, số CCCD/điện thoại khách hàng trong log và commit.

### Chặng R1 — Hoàn tất RBAC và audit server-side
- **Trạng thái:** **HOÀN TẤT CỤC BỘ (PASS CỤC BỘ)**
- Đã chuẩn hóa JSON 403 đồng bộ từ `app/Exceptions/Handler.php`.
- Đã tạo trang 403 riêng biệt tại `resources/js/src/view/pages/error/Error-403.vue` và khai báo route `/403` trong `router.js` (không còn rơi vào 404).
- Bộ test `tests/Unit/PermissionMatrixTest.php` gồm 11 test case mô phỏng đầy đủ:
  - Nhân viên cơ sở A gọi dữ liệu cơ sở B -> 403
  - Nhân viên xem KPI toàn công ty -> 403
  - Kế toán sửa nhân sự / thu hồi GPS -> 403
  - Nhân sự xem kế toán -> 403
  - Ban Giám Đốc xem nhưng không post bút toán -> 403
  - Audit log che giấu mật khẩu, token, CCCD (0010********), số điện thoại (******4321).

### Chặng R2 — Snapshot PDF và chuyển quyền an toàn
- **Trạng thái:** **HOÀN TẤT CỤC BỘ (PASS CỤC BỘ)**
- Đã tạo migration `database/migrations/2026_09_17_000012_add_document_snapshot_to_lease_contracts_table.php` hỗ trợ backward-compatible với kiểm tra `hasColumn`.
- Đã bổ sung `LeaseDocumentSnapshotService` chụp toàn bộ snapshot bên A/B, CCCD, xe (hỗ trợ các cột production `license/chassis/engine`), tài chính, đọc số tiền bằng chữ tiếng Việt và mã băm sha256.
- `LeasePdfService` đọc cố định từ `document_snapshot` đã khóa, không phụ thuộc vào quan hệ động.
- Đã thêm test `tests/Unit/LeasePdfSnapshotTest.php`: chứng minh khi sửa thông tin khách hàng và xe trong CSDL sau khi khóa hợp đồng, nội dung PDF và mã sha256 vẫn giữ nguyên bất biến.
- Đã gắn feature flag `HIMOTO_ENABLE_OWNERSHIP_EXECUTE` (mặc định `false`) khóa chức năng execute chuyển quyền cho đến khi có chính sách được duyệt bằng văn bản.
- Đã thêm test `tests/Unit/LeaseOwnershipWorkflowTest.php` kiểm tra chặn execute khi cờ tắt.

### Chặng R3 — R6
- **R3 (Nhắc nợ thật):** **BLOCKED_EXTERNAL_PROVIDER** — Chờ webhook/API/credentials từ đối tác SMS/Zalo.
- **R4 (GPS thật):** **BLOCKED_EXTERNAL_PROVIDER** — Chờ tài liệu thiết bị và API provider.
- **R5 (Kế toán sổ kép production):** **BLOCKED_ACCOUNTING_POLICY** — Chờ kế toán ký duyệt bảng hệ thống tài khoản và quy tắc hạch toán nguồn.
- **R6 (UAT staging Render/Supabase):** **BLOCKED_STAGING_UAT** — Chờ cấu hình môi trường staging riêng biệt và xoay vòng secret.

---

## 3. Cam kết an toàn
- Tuyệt đối không thực hiện `git push` khi chưa có chỉ đạo từ chủ hệ thống.
- Giữ nguyên trạng thái sandbox cho GPS và nhắc nợ.
- Không thực thi migration trên CSDL live.
