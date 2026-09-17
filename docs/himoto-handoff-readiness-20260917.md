# HIMOTO — Kết quả review trước bàn giao 17/09/2026

Nhánh release candidate: `review/20260917-rc`

Commit sửa lỗi review: `37884e8`

Baseline production/main hiện tại: `a1a20bc`

## Kết luận

Mã local đã qua cổng kỹ thuật cục bộ nhưng **chưa đủ bằng chứng để tuyên bố production-ready hoặc hoàn thiện toàn bộ Excel**. Nhánh release candidate được giữ riêng để không kích hoạt deploy/migration production ngoài ý muốn.

## Kết quả kiểm chứng cục bộ

- PHPUnit: **175 tests / 882 assertions**, exit 0.
- `npm.cmd run production`: PASS; còn cảnh báo dependency Browserslist và Sass cũ, không có lỗi build.
- `npm.cmd run build:static`: PASS.
- `php artisan route:list`: PASS.
- PHP lint toàn bộ file thay đổi: PASS.
- `git diff --check`: PASS.
- Không commit `.env`, DSN hoặc credential provider. Các file `.env`, `.env.supabase`, `.env.cloudinary` vẫn bị ignore.

## Lỗi đã sửa sau review

1. RBAC trong DB là nguồn quyết định: quyền đã thu hồi không còn bị server tự cấp lại từ fallback hard-code.
2. Danh sách nhắc nợ được scope theo cơ sở; sửa relation khách hàng gây lỗi runtime action-list.
3. Gửi nhắc thật bị khóa nếu chỉ có sandbox hoặc `REMINDER_LIVE_ENABLED=false`; log/webhook được che PII.
4. Kế toán mặc định scope về cơ sở của người dùng nếu client bỏ `store_id`; chặn sửa/hủy chứng từ cơ sở khác và chặn kế toán cơ sở khóa kỳ toàn công ty.
5. Chuẩn hóa thứ tự lock kỳ → chứng từ, khóa reconciliation theo kỳ, chặn đối soát trong kỳ đã đóng và loại `FOR UPDATE` sai trên truy vấn aggregate.
6. Snapshot hợp đồng được tạo trong transaction, có lịch kỳ bất biến, kiểm checksum trước xuất PDF và chặn mass-assignment vào trường snapshot.
7. Tiền cọc cam kết không còn bị tính là tiền đã thu khi chưa có allocation thực.
8. Thực thi chuyển quyền mặc định khóa; GPS sync/recovery và reminder scan dùng permission đúng mức, có store scope.
9. Schema readiness tách riêng lease document, kiểm đủ sổ kép và RBAC trước khi mở route mới.

## Phần vẫn BLOCKED, không được ghi là đã hoàn thiện

- Supabase/Render staging chưa chạy UAT ghi–đọc thật; chưa có bằng chứng refresh/re-login, concurrency PostgreSQL, rollback và cleanup `test_run_id`.
- Supabase mục tiêu còn thiếu các migration mới, gồm nhóm `000001`–`000005`, `000011` và `000012`; chưa chạy migration live.
- Nhắc nợ và GPS mới có sandbox, chưa có provider/credential/delivery proof thật.
- Chart of accounts, posting rules và chuyển quyền sở hữu chưa được chủ hệ thống/kế toán/pháp lý ký duyệt.
- PDF thuê sở hữu hiện là renderer prototype ASCII; chưa đạt yêu cầu logo/font Unicode và mẫu pháp lý đã duyệt.
- Credential Supabase/Cloudinary từng xuất hiện trên màn hình/chat phải được rotate trước bàn giao tài khoản vận hành.

## Cổng phát hành bắt buộc

1. Tạo Supabase và Render staging riêng; rotate secret đã lộ.
2. Backup, review SQL/checksum và chạy migration trên staging, không chạy trực tiếp production.
3. Chạy lại full suite, PostgreSQL concurrency và browser UAT không mock.
4. Ký ma trận RBAC, chart of accounts, posting rules, chính sách chuyển quyền và mẫu PDF.
5. Chỉ merge `review/20260917-rc` vào `main` sau khi các bước trên PASS; provider live và ownership execute tiếp tục để `false` cho đến khi được duyệt.
