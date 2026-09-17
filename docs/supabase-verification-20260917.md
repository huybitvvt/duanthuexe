# HIMOTO — Kiểm chứng Supabase staging/live ngày 17/09/2026

## Kết luận

**BLOCKED — không chạy migration và không ghi/xóa dữ liệu.**

Lệnh kiểm tra chỉ đọc `scripts/check_operational_schema.php` được chạy qua `.env.supabase`, schema được cấu hình là `himoto`. Các migration cũ `000006`–`000010` đã có trong kết quả kiểm tra trước; các bảng mới của chặng RBAC/audit, ownership, reminder nâng cao, GPS và kế toán sổ kép hiện chưa có trên database đích.

## Bằng chứng

- Script: `scripts/check_operational_schema.php`
- Chế độ: chỉ đọc `information_schema.columns` và bảng `migrations`
- Kết quả: exit code `2`, `Operational schema is incomplete`
- Không chạy `artisan migrate`, không chạy canary CRUD, không seed, không xóa dữ liệu.

Migration còn pending trên database đích:

| Migration | Phạm vi |
|---|---|
| `2026_09_17_000001_create_audit_events_table` | audit bất biến |
| `2026_09_17_000002_seed_rbac_permissions_and_roles` | quyền/capability |
| `2026_09_17_000003_create_lease_ownership_tables` | chuyển quyền thuê sở hữu |
| `2026_09_17_000004_enhance_customer_reminder_outbox_table` | outbox/provider/retry |
| `2026_09_17_000005_create_gps_tracking_tables` | thiết bị/vị trí/cảnh báo GPS |
| `2026_09_17_000011_create_double_entry_accounting_tables` | tài khoản/kỳ/journal/đối soát |

## Điều kiện để kiểm lại

1. Xác nhận database đích là staging, không phải production.
2. Backup và thử restore staging vào database khác.
3. Duyệt SQL/migration, checksum và thứ tự áp dụng.
4. Chạy migration trên staging bởi người được ủy quyền.
5. Chạy lại script này, sau đó mới chạy CRUD/concurrency/browser UAT với `test_run_id` riêng.

Không được kết luận provider GPS/nhắc nợ hoặc kế toán đã PASS chỉ vì unit test local đạt.

