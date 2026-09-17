# HIMOTO — Kiểm chứng Supabase ngày 17/09/2026

## Kết luận

**PASS về schema/migration; UAT nghiệp vụ vẫn BLOCKED.**

Theo chỉ đạo trực tiếp của chủ hệ thống, các migration còn thiếu được áp dụng vào Supabase đang sử dụng, schema `himoto`, từ commit RC `af8cacf6eaf54334bbbe5bf7baf034b90de87c39`.

## Bảo vệ dữ liệu trước khi chạy

- Backup trước migration: `backups/himoto-20260917-101823.dump`.
- SHA-256: `4E075CC16C0AF7F4E1BFC765C8A8AD93E01E1FE034DC106153E8D886D38C8850`.
- Archive custom-format đọc được bằng `pg_restore --list`, gồm 908 dòng TOC.
- Đã chạy `artisan migrate --pretend` trước khi chạy thật.
- Không dùng `migrate:fresh`, `migrate:refresh`, rollback hoặc hard-delete dữ liệu nghiệp vụ.

## Migration đã áp dụng

Các migration sau được ghi nhận ở batch 40:

| Migration | Phạm vi |
|---|---|
| `2026_09_17_000001_create_audit_events_table` | audit bất biến |
| `2026_09_17_000002_seed_rbac_permissions_and_roles` | quyền/capability |
| `2026_09_17_000003_create_lease_ownership_tables` | chuyển quyền thuê sở hữu |
| `2026_09_17_000004_enhance_customer_reminder_outbox_table` | outbox/provider/retry |
| `2026_09_17_000005_create_gps_tracking_tables` | thiết bị/vị trí/cảnh báo GPS |
| `2026_09_17_000011_create_double_entry_accounting_tables` | tài khoản/kỳ/journal/đối soát |
| `2026_09_17_000012_add_document_snapshot_to_lease_contracts_table` | snapshot PDF thuê sở hữu |

## Kết quả hậu kiểm

`scripts/check_operational_schema.php --json` trả:

- `ready: true`
- `schema: himoto`
- `missing_columns: []`
- `pending_migrations: []`
- `checksum_mismatches: []`

Đã tạo thêm backup sau migration: `backups/himoto-20260917-102213.dump`, SHA-256 `E85204C825F7C683CD04304BFCEAFF94063013D36F6179D0086C4255B545FCE3`; archive đọc được và có 1.062 dòng TOC.

## Phần chưa được chứng minh

- Chưa chạy CRUD/concurrency/browser UAT có `test_run_id` trên API Render release candidate.
- Chưa kiểm thử restore backup vào database khác.
- GPS và nhắc nợ vẫn dùng sandbox, chưa có provider/delivery proof thật.
- Chưa ký duyệt chart of accounts, posting rules, RBAC và quy trình chuyển quyền.
- PDF thuê sở hữu vẫn cần mẫu pháp lý, font tiếng Việt và logo được duyệt.

Không dùng kết quả schema PASS để kết luận toàn bộ yêu cầu Excel hoặc production UAT đã PASS.
