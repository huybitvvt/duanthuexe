# HIMOTO — Kế hoạch chi tiết hoàn thiện các phần còn thiếu

Ngày lập: 17/09/2026  
Baseline code: `a1a20bc` trên `main`  
Nguồn đối chiếu: `Thông tin yêu cầu về APP.xlsx` Sheet1 và `docs/himoto-completion-plan-from-excel.md`.

## 1. Kết luận và phạm vi

Hệ thống **chưa hoàn thiện toàn bộ yêu cầu Excel**. Kế hoạch này chỉ xử lý năm khoảng trống còn lại:

1. GPS và gửi nhắc nợ qua provider thật.
2. PDF thuê sở hữu và quy trình chuyển quyền sở hữu.
3. Phân quyền riêng cho BGĐ, kế toán và HR.
4. Kế toán đầy đủ: hệ thống tài khoản, bút toán kép, khóa kỳ và đối soát.
5. UAT toàn bộ trên Render và Supabase staging thật.

Không chạy migration production, không gửi tin thật và không đổi quyền tài khoản vận hành trong quá trình phát triển. Mọi thao tác live phải qua cổng phê duyệt ở mục 9.

### Hiện trạng code có thể tái sử dụng

| Phần | Đã có | Khoảng trống phải xử lý |
|---|---|---|
| GPS | `GpsProviderInterface`, `GpsService`, `MockGpsProvider`, API overview | Chưa có provider thật, mapping thiết bị, đồng bộ vị trí, cảnh báo và lịch sử GPS |
| Nhắc nợ | Outbox, scan khoản đến hạn, idempotency cơ bản, UI tác nghiệp | Chưa có SMS/Zalo/email adapter, queue worker, webhook giao nhận, retry thực và quản lý đồng ý nhận tin |
| Thuê sở hữu | Hợp đồng, kỳ trả, phân bổ tiền, tất toán, đảo thu, ghi chú, Excel | Chưa có PDF tải xuống và quy trình phê duyệt/chuyển quyền |
| Quyền | Admin và giới hạn theo cơ sở qua `PilotAccess`; có bảng role/permission cũ | Chưa có capability cho BGĐ/kế toán/HR; một số API KPI/kế toán hiện còn cho nhân viên cơ sở đọc |
| Kế toán | VAT, tài sản, transaction/két/ngân hàng nền | Chưa có chart of accounts, journal entry/line, kỳ kế toán, bút toán đối ứng và đối soát; VAT/tài sản đang có hard-delete |
| UAT | Unit/feature test và kiểm schema đọc-only | Chưa có ma trận nghiệp vụ đầy đủ trên PostgreSQL staging và browser qua API release thật |

## 2. Thứ tự thực hiện bắt buộc

| Chặng | Nội dung | Phụ thuộc | Điều kiện chuyển chặng |
|---|---|---|---|
| 0 | Chốt chính sách, secret và môi trường | Không | Có đủ quyết định/credential hoặc ghi BLOCKED rõ ràng |
| 1 | RBAC và audit log | Chặng 0 | Test quyền server đạt, không chỉ ẩn menu |
| 2 | PDF và chuyển quyền thuê sở hữu | Chặng 1; quy tắc chuyển quyền; khung bút toán | PDF snapshot đúng và state machine chuyển quyền đạt |
| 3 | Provider nhắc nợ | Chặng 1; credential/template/consent | Sandbox và webhook đạt trước khi bật gửi thật |
| 4 | Provider GPS | Chặng 1; tài liệu API/device map | Đồng bộ, stale/offline và cảnh báo đạt trên sandbox |
| 5 | Kế toán đầy đủ | Chặng 1; chính sách kế toán | Debit = credit, đóng kỳ, đảo bút toán, đối soát đạt |
| 6 | UAT Supabase/Render | Chặng 2–5 | Tất cả cổng P0/P1 đạt hoặc mục phụ thuộc được ký BLOCKED |

Chặng 2–4 có thể phát triển độc lập sau khi chặng 1 ổn định. Chặng 5 không được rút gọn thành dashboard tổng hợp vì yêu cầu Excel là nghiệp vụ kế toán, không chỉ báo cáo.

## 3. Chặng 0 — đầu vào và quyết định phải chốt

### 3.1 Bảo mật và môi trường

1. Đổi mật khẩu Supabase và Cloudinary đã từng xuất hiện trên màn hình/chat; cập nhật Render/local bằng secret mới.
2. Tạo Supabase **staging riêng**, không dùng database vận hành để chạy test ghi/xóa.
3. Tạo Render staging API/web riêng hoặc xác nhận service staging hiện có; tắt auto-migration production.
4. Chốt `APP_ENV`, `APP_DEBUG=false`, CORS, queue connection, scheduler và retention log.
5. Chỉ lưu tên biến môi trường trong repo; không lưu token, DSN, mật khẩu hoặc webhook secret.

### 3.2 Đầu vào từ chủ hệ thống

| Nhóm | Thông tin cần cung cấp | Nếu chưa có |
|---|---|---|
| GPS | Tên provider, tài liệu API, sandbox/base URL, kiểu auth, credential, danh sách `vehicle_id ↔ device_id`, giới hạn gọi API, webhook nếu có | Giữ `BLOCKED_PENDING_EXTERNAL_PROVIDER_CREDENTIALS`; không sinh vị trí giả |
| Nhắc nợ | Kênh ưu tiên (Zalo/SMS/email), provider, template đã duyệt, brandname/sender, webhook, giờ được gửi, retry, chính sách opt-out | Chỉ giữ `call_task` và dry-run; không đánh dấu `sent` |
| Chuyển quyền | Điều kiện hết nợ, giấy tờ bắt buộc, cấp duyệt, ngày hiệu lực, cách hạch toán phí/chiết khấu, có cho hoàn tác hay không | Chỉ làm PDF và màn hình nháp; endpoint thực thi chuyển quyền bị khóa |
| Phân quyền | Danh sách người thuộc BGĐ, kế toán, HR; phạm vi toàn công ty/cơ sở; quyền xem, tạo, duyệt, đóng kỳ, export | Dùng ma trận đề xuất ở mục 4 và yêu cầu ký duyệt trước seed live |
| Kế toán | Hệ thống tài khoản, tài khoản tiền/công nợ/doanh thu/VAT/tài sản/khấu hao, ngày bắt đầu, số dư đầu kỳ, kỳ khóa, quy tắc VAT | Không tự đặt tài khoản hoặc bút toán sản xuất |

### 3.3 Deliverable chặng 0

- `docs/decisions/himoto-provider-and-policy-decisions.md` với từng mục `APPROVED`, `PROPOSED` hoặc `BLOCKED`; `PROPOSED` chưa được dùng làm căn cứ bật live.
- Mẫu biến môi trường chỉ có tên biến và mô tả.
- Biên bản xác nhận Supabase staging, Render staging và người được phép bật provider live.

## 4. Chặng 1 — RBAC và audit nền tảng

### 4.1 Mô hình quyền

Tận dụng `roles`, `permissions`, `roles_permissions`, `users_permissions`; không tiếp tục rải điều kiện `role_id === 1` trong controller mới.

Permission slug đề xuất:

| Nhóm | Quyền |
|---|---|
| KPI | `kpi.view_company`, `kpi.view_store`, `kpi.export` |
| Kế toán | `accounting.view`, `accounting.post`, `accounting.reverse`, `accounting.close_period`, `accounting.reconcile`, `accounting.export` |
| HR | `hr.view`, `hr.manage_staff`, `hr.manage_attendance`, `hr.manage_schedule`, `hr.export` |
| Thuê sở hữu | `lease.view`, `lease.collect`, `lease.reverse_payment`, `lease.ownership_request`, `lease.ownership_approve`, `lease.ownership_execute`, `lease.export` |
| Nhắc nợ | `reminder.view`, `reminder.manage`, `reminder.dispatch_live` |
| GPS | `gps.view`, `gps.manage_devices`, `gps.manage_alerts`, `gps.recovery_action` |

Role mặc định đề xuất:

- `quan-tri-vien`: toàn quyền nhưng vẫn phải ghi audit cho thao tác tài chính.
- `ban-giam-doc`: xem toàn công ty, KPI/export; không tự sửa bút toán nếu không có quyền bổ sung.
- `ke-toan`: kế toán, két, ngân hàng, VAT, tài sản, công nợ; phạm vi công ty hoặc cơ sở theo cấu hình.
- `nhan-su`: hồ sơ nhân sự, chấm công, lịch trực; không đọc công nợ/GPS nếu không được cấp riêng.
- `quan-ly-cua-hang` và `nhan-vien`: chỉ nghiệp vụ cơ sở, không mặc định xem KPI toàn công ty/kế toán/HR.

### 4.2 Thay đổi backend

1. Tạo `PermissionAccess` hoặc policy trung tâm với `allows(user, permission, storeId)`.
2. Thêm middleware `permission:<slug>`; mọi API nhạy cảm kiểm quyền server.
3. Bổ sung unique index cho pivot role/user-permission nếu dữ liệu hiện tại không trùng; migration phải kiểm dữ liệu trước khi tạo index.
4. Sửa `KpiReportController`: chỉ BGĐ, kế toán được cấu hình hoặc admin được đọc; `store_id` phải bị scope ở server.
5. Sửa `AccountingController`: quyền xem/ghi/đóng kỳ tách riêng; bỏ hard-delete chứng từ đã ghi nhận.
6. Sửa `HrController`, `LeaseContractController`, `CustomerReminderController` và GPS routes theo permission tương ứng.
7. Response bị chặn dùng HTTP 403 ổn định; không trả dữ liệu rồi mới ẩn ở frontend.

### 4.3 Audit log

Tạo bảng `audit_events`:

- `actor_user_id`, `action`, `subject_type`, `subject_id`, `store_id`.
- `before_json`, `after_json` đã lọc secret/PII.
- `reason`, `request_id`, `ip_hash`, `created_at`.
- Index theo subject, actor, store và thời gian.

Bắt buộc ghi audit cho cấp quyền, đổi hồ sơ nhân sự, gửi nhắc thật, đổi mapping GPS, thu/đảo thu, yêu cầu/phê duyệt/thực thi chuyển quyền, ghi/đảo bút toán, đóng/mở kỳ và đối soát.

### 4.4 Frontend

- API trả `capabilities` sau đăng nhập; Vuex lưu theo phiên người dùng, xóa khi logout.
- Menu và nút dựa trên capability để giảm thao tác sai, nhưng server vẫn là nguồn quyết định.
- Trang 403 riêng, không chuyển sai quyền thành 404.
- Icon chức năng được dùng khi có `aria-label`/`title`, focus và vùng bấm tối thiểu phù hợp; loại icon trang trí dư thừa.

### 4.5 Test và điều kiện PASS

- Data provider test từng permission × role × store.
- Staff cơ sở A gọi trực tiếp API cơ sở B phải 403.
- HR không đọc kế toán; kế toán không sửa HR; BGĐ xem nhưng không tự post nếu thiếu quyền.
- Thay quyền phải có hiệu lực sau đăng nhập lại/token refresh; cache quyền không lẫn user.
- Mọi thao tác nhạy cảm có một audit event, không chứa token/full CCCD.

## 5. Chặng 2 — PDF và chuyển quyền thuê sở hữu

### 5.1 PDF hợp đồng và công nợ

1. Tạo view in độc lập từ **snapshot hợp đồng đã lưu**, không lấy lại tên/giá hiện tại để sửa lịch sử.
2. Thêm endpoint:
   - `GET /api/auth/lease-contracts/{id}/pdf`
   - `GET /api/auth/lease-contracts/{id}/debt-statement.pdf`
3. Dùng PDF renderer tương thích Laravel 5.8/PHP 7.4, pin version trong `composer.lock`; chạy `composer why-not` trước khi chọn package, không cài bản mới nhất mù quáng.
4. Nhúng font tiếng Việt và logo HIMOTO; kiểm trang A4, ngắt trang, số tiền bằng số/chữ, timezone Việt Nam.
5. Header PDF ghi mã hợp đồng, phiên bản snapshot, thời điểm phát hành; response là `application/pdf` và tên file an toàn.
6. Quyền tải PDF theo `lease.view` và phạm vi cơ sở; audit lượt xuất nếu tài liệu chứa PII.

### 5.2 State machine chuyển quyền

Tạo bảng `lease_ownership_requests`:

- Liên kết hợp đồng, xe, khách, cơ sở.
- Trạng thái: `draft → submitted → approved → executed`; nhánh `rejected` hoặc `cancelled`.
- Snapshot điều kiện tại lúc gửi duyệt: tổng phải thu, đã thu, giảm trừ, dư nợ, kỳ chưa trả, giấy tờ.
- Người yêu cầu/duyệt/thực thi và thời điểm tương ứng.
- `approval_reason`, `execution_note`, `effective_date`, `idempotency_key` unique.

Tạo `lease_ownership_events` bất biến để lưu mọi chuyển trạng thái. Nếu cần xác nhận chủ sở hữu xe trong hệ thống, tạo `vehicle_ownerships` theo thời gian; không ghi đè lịch sử khách cũ.

### 5.3 Luật thực thi

Trong một database transaction và lock theo thứ tự `lease_contract → vehicle → ownership_request`:

1. Hợp đồng tồn tại và thuộc quyền truy cập.
2. Trạng thái hợp đồng cho phép chuyển quyền.
3. Dư nợ tính từ allocation active bằng 0; không tin số dư frontend.
4. Không có request chuyển quyền khác đang active.
5. Đủ checklist/giấy tờ và người duyệt khác người yêu cầu nếu chính sách yêu cầu maker-checker.
6. Tạo sự kiện ownership, cập nhật trạng thái hợp đồng/xe theo chính sách, ghi audit và bút toán liên quan.
7. Retry cùng idempotency key trả lại kết quả cũ, không chuyển hai lần.

Không hỗ trợ “xóa chuyển quyền”. Sai sót phải dùng quy trình reversal/correction được phê duyệt.

### 5.4 API/UI

- `POST /lease-contracts/{id}/ownership-requests`
- `POST /lease-ownership-requests/{id}/submit`
- `POST /lease-ownership-requests/{id}/approve`
- `POST /lease-ownership-requests/{id}/reject`
- `POST /lease-ownership-requests/{id}/execute`
- `GET /lease-ownership-requests` có filter trạng thái/cơ sở/ngày.

UI hiển thị checklist, số dư đọc-only từ server, timeline phê duyệt và nút theo capability. Trước `execute` phải có màn xác nhận cuối cùng và lý do.

### 5.5 Test/PASS

- PDF snapshot không đổi sau khi sửa khách/xe; tiếng Việt và ngắt trang đúng với 1, 12, 36 kỳ.
- Thiếu quyền, khác cơ sở, còn nợ, request trùng, hai request execute đồng thời đều bị xử lý đúng.
- Retry không tạo ownership/bút toán lần hai.
- Lỗi giữa transaction rollback toàn bộ trạng thái, sự kiện và bút toán.
- PASS chỉ khi chính sách chuyển quyền đã `APPROVED`; nếu chưa, PDF có thể PASS nhưng thực thi ownership vẫn BLOCKED.

## 6. Chặng 3 — gửi nhắc nợ qua provider thật

### 6.1 Kiến trúc

Tạo `ReminderProviderInterface` với các hàm `send`, `queryStatus`, `verifyWebhook`. Mỗi provider là adapter riêng; nghiệp vụ không gọi HTTP trực tiếp từ controller.

Luồng:

1. Scheduler scan khoản đến hạn và tạo outbox idempotent.
2. Worker claim bản ghi bằng transaction/row lock; không để hai worker cùng gửi.
3. Kiểm lại khoản đã trả, hợp đồng hủy, opt-out và giờ gửi ngay trước khi gọi provider.
4. Gửi với provider idempotency/correlation ID.
5. Lưu mã message của provider, trạng thái chấp nhận; webhook cập nhật delivered/failed.
6. Retry theo backoff có giới hạn; quá ngưỡng chuyển `dead_letter`, không vòng lặp vô hạn.

### 6.2 Schema cần bổ sung

Mở rộng outbox bằng migration backward-compatible:

- `provider`, `provider_message_id`, `template_code`.
- `attempted_at`, `next_attempt_at`, `delivered_at`, `failed_at`.
- `locked_at`, `locked_by`, `last_http_status`.
- `consent_source`, `consent_captured_at`, `cancel_reason`.
- Index `(status, next_attempt_at)` và unique phù hợp với idempotency.

Tạo bảng `reminder_delivery_events` để lưu timeline provider đã rút gọn; không lưu header auth hoặc response chứa secret.

### 6.3 Queue và scheduler

- Production không dùng `QUEUE_CONNECTION=sync` cho gửi tin.
- Dùng database queue hoặc Redis; có worker riêng trên Render, timeout/retry rõ ràng.
- Thêm command scan và dispatch, schedule theo giờ Việt Nam; dùng lock chống chạy trùng scheduler.
- Feature flags: `REMINDER_LIVE_ENABLED=false` mặc định và whitelist số test ở sandbox.

### 6.4 An toàn nghiệp vụ

- Template và biến được whitelist; escape nội dung, không cho người dùng chèn link tùy ý.
- Che số điện thoại trên log/UI theo quyền.
- Quiet hours, opt-out và rate limit theo khách/hợp đồng/kênh.
- Nút “Gửi thật” yêu cầu `reminder.dispatch_live`; dry-run không đổi `sent`.
- Webhook kiểm signature, timestamp và replay; endpoint có rate limit.

### 6.5 Test/PASS

- Contract test adapter bằng sandbox provider.
- Hai worker claim cùng outbox chỉ gửi một lần.
- Đã trả tiền trước lúc gửi → `skipped`, không gọi provider.
- Timeout/429/5xx retry đúng; 4xx vĩnh viễn vào failed/dead-letter.
- Webhook trùng không tạo event/trạng thái trùng.
- UAT chỉ gửi tới whitelist nội bộ; sau khi đối chiếu provider dashboard mới xin bật live.

## 7. Chặng 4 — GPS provider thật

### 7.1 Schema

Không dùng `vehicle_location_events` của điều chuyển kho để lưu tọa độ GPS. Tạo riêng:

- `gps_devices`: `vehicle_id`, provider, external device ID, trạng thái mapping, last sync, metadata tối thiểu.
- `gps_positions`: device, lat/lng, speed, ignition, heading, provider timestamp, received timestamp, normalized status; unique provider event/time để chống trùng.
- `gps_alerts`: loại `offline/stale/stopped/geofence/tamper`, severity, opened/resolved/acknowledged.
- `gps_recovery_actions`: người phụ trách, kế hoạch thu hồi, hạn xử lý, ghi chú, trạng thái và audit.

Thiết lập retention vị trí theo chính sách; dữ liệu cũ được tổng hợp/xóa theo job đã duyệt, không giữ vô hạn mặc định.

### 7.2 Adapter và đồng bộ

1. Implement adapter theo `GpsProviderInterface`; mapping status provider về domain status hiện có.
2. Credential chỉ từ env/secret store; HTTP timeout, retry, circuit breaker và rate limit.
3. Nếu provider có webhook: xác thực signature và chống replay. Nếu chỉ polling: batch device, cursor và lock scheduler.
4. Lưu cả `provider_recorded_at` và `received_at`; stale dựa trên timestamp provider, không dựa duy nhất vào lúc request.
5. Không ghi vị trí `0,0` hoặc dữ liệu thiếu thành vị trí hợp lệ.

### 7.3 API/UI

- Tổng quan fleet theo scope cơ sở và capability.
- Danh sách xe mất tín hiệu/stale, lần cuối kết nối, người phụ trách và trạng thái thu hồi.
- Chi tiết lịch sử giới hạn thời gian; export chỉ cho quyền được duyệt.
- Nếu provider lỗi, UI hiển thị “Dữ liệu cập nhật lần cuối …”, không giả vờ realtime.
- Không đưa API key/provider token vào bundle frontend.

### 7.4 Test/PASS

- Fixture từ payload sandbox đã ẩn danh; test mapping moving/stopped/stale/offline/never-connected.
- Webhook/poll trùng không tạo vị trí trùng.
- Nhân viên cơ sở A không đọc tọa độ cơ sở B.
- Provider timeout không xóa vị trí cuối; tạo health warning đúng.
- Cảnh báo mở/đóng idempotent, không spam mỗi lần poll.
- PASS khi ít nhất một thiết bị sandbox/thử nghiệm trả dữ liệu thật và đọc lại từ Supabase staging.

## 8. Chặng 5 — kế toán đầy đủ

Đây là chặng lớn nhất. Không ánh xạ `transactions` hiện có thành sổ kế toán bằng suy đoán rồi công bố hoàn tất.

### 8.1 Mô hình dữ liệu

Tạo các bảng:

1. `accounting_accounts`: mã, tên, loại, tài khoản cha, chiều số dư, trạng thái.
2. `accounting_periods`: tháng/năm, ngày bắt đầu/kết thúc, trạng thái `open/closing/closed`, người đóng/mở lại và lý do.
3. `journal_entries`: số chứng từ, ngày hạch toán, cơ sở, nguồn (`source_type/source_id`), trạng thái `draft/posted/reversed`, idempotency key, diễn giải, người tạo/duyệt/post.
4. `journal_lines`: tài khoản, debit, credit, đối tượng, cơ sở, bank/cash/contract/asset reference.
5. `accounting_reconciliations`: kỳ, cơ sở, loại cash/bank, số sổ, số thực tế/sao kê, chênh lệch, trạng thái và người duyệt.
6. `accounting_opening_balances` hoặc journal opening entry có source riêng.

Ràng buộc ứng dụng và DB:

- Mỗi entry posted có tổng debit = tổng credit và ít nhất hai dòng.
- Debit/credit không âm; một line không đồng thời có cả hai.
- Source/idempotency không được post hai lần.
- Entry posted là bất biến; sửa sai bằng reversal + entry mới.
- Kỳ closed chặn post/backdate; mở lại cần quyền và lý do/audit.

### 8.2 Hệ thống tài khoản và posting rules

Chủ hệ thống/kế toán duyệt mapping tối thiểu:

- Tiền mặt từng cơ sở, ngân hàng cá nhân/công ty.
- Phải thu khách thuê và thuê sở hữu.
- Doanh thu thuê xe, gia hạn, phạt, thuê sở hữu.
- Tiền cọc nhận/hoàn.
- VAT đầu vào/đầu ra/phải nộp.
- Tài sản xe/tài sản khác, hao mòn và chi phí khấu hao.
- Chi phí vận hành, chênh lệch két/ngân hàng.

Mỗi nghiệp vụ có posting rule versioned và test ví dụ. Không hard-code tài khoản rải trong controller.

### 8.3 Tích hợp dữ liệu hiện hữu

1. Viết report phân loại `transactions` hiện hữu: mapped, ambiguous, invalid; không tự post mục ambiguous.
2. Chạy **shadow mode** trên staging: tạo journal nháp từ giao dịch test, so tổng theo ngày/cơ sở/phương thức.
3. Chỉ backfill dữ liệu cũ khi kế toán duyệt mốc bắt đầu và mapping; lưu source link/checksum.
4. VAT/tài sản hiện có được liên kết entry; thay endpoint hard-delete bằng `void/dispose/reverse` có lý do.
5. Daily cash register và bank/cash ledger được đối soát với journal, không tạo thêm nguồn sự thật cạnh tranh.

### 8.4 Service/API

- `JournalPostingService`: validate, lock kỳ, post atomically, idempotency.
- `JournalReversalService`: tạo entry đối ứng, không sửa entry gốc.
- `PeriodCloseService`: kiểm draft/chênh lệch trước khi close.
- `ReconciliationService`: cash/bank theo ngày/tháng/cơ sở.
- API cho chart of accounts, journal, trial balance, general ledger, period close/reopen, reconciliation, VAT và asset register.
- Tất cả route dùng permission kế toán và store scope.

### 8.5 UI và báo cáo

- Danh mục tài khoản; sổ nhật ký; sổ cái; bảng cân đối phát sinh.
- VAT đầu vào/đầu ra liên kết chứng từ nguồn.
- Tài sản và lịch khấu hao; thanh lý/điều chuyển có sự kiện.
- Đối soát két/ngân hàng hiển thị nguồn chênh lệch và trạng thái xử lý.
- Export CSV/Excel/PDF chạy server-side với cùng bộ lọc/quyền như màn hình.
- UI không cho sửa/xóa entry posted; chỉ nút đảo bút toán nếu có quyền.

### 8.6 Test/PASS

- Property/invariant test debit = credit cho từng posting rule.
- Hai request cùng idempotency chỉ có một entry posted.
- Hai request post/close kỳ đồng thời không xuyên khóa.
- Closed period chặn tạo/post/reversal backdate trái chính sách.
- Reversal giữ entry gốc và tạo đúng đối ứng.
- Trial balance, ledger, VAT, asset, cash/bank reconciliation khớp dataset chuẩn đã được kế toán ký.
- PostgreSQL transaction rollback không để header không line hoặc line nửa chừng.

## 9. Chặng 6 — UAT Supabase staging và Render

### 9.1 Chuẩn bị release candidate

1. Chốt commit RC; working tree sạch; lưu SHA frontend/backend.
2. Backup staging; chạy migration dry-run/SQL review/checksum; kiểm `down()` nhưng không coi rollback schema là phương án duy nhất.
3. Deploy API trước, chạy health/schema readiness, sau đó deploy web cùng version.
4. Xác nhận runtime dùng PostgreSQL Supabase staging bằng query không nhạy cảm; không in DSN.

### 9.2 Dataset và truy vết

- Mỗi đợt UAT có `test_run_id` trong sổ kiểm thử và `request_id`/audit correlation.
- Dùng khách/xe/số điện thoại giả lập hoặc whitelist nội bộ; không dùng dữ liệu khách thật cho provider sandbox.
- Ghi ID từng record tạo ra; cleanup bằng danh sách ID đã xác minh, không dùng wildcard/hard-delete giao dịch tài chính.
- Record tài chính được đảo bằng nghiệp vụ; xác nhận tổng trước/sau và không còn orphan.

### 9.3 Ma trận UAT bắt buộc

| Nhóm | Case tối thiểu | Bằng chứng |
|---|---|---|
| RBAC | Admin/BGĐ/kế toán/HR/quản lý/staff; khác cơ sở; gọi URL/API trực tiếp | HTTP status, response đã che dữ liệu, audit event |
| PDF/ownership | PDF snapshot; request→approve→execute; còn nợ; retry; concurrency | PDF, DB rows, checksum, timeline/audit |
| Nhắc nợ | scan, dry-run, sandbox send, paid-before-send, retry, webhook trùng | Provider message ID đã che, outbox/event, HTTP status |
| GPS | mapping thiết bị, poll/webhook, stale/offline, cảnh báo, khác cơ sở | Payload đã ẩn, position/alert DB, ảnh UI |
| Kế toán | post từng rule, reversal, close period, reconciliation, export | Journal/line sums, query balance, file export |
| Hồi quy | Hợp đồng thuê, thu tiền, két, kho/đổi xe, thuê sở hữu, HR | API/UI/DB cùng giá trị sau refresh/re-login |

### 9.4 Browser E2E

- Không mock API trong suite nghiệm thu.
- Viewport 360/390/768/1024/1440; desktop/mobile; keyboard/focus.
- Deep-link/refresh/logout-login; cache không lẫn tài khoản.
- Preview/PDF/download; loading/error/empty state; không tràn ngang.
- Icon chức năng có nhãn trợ năng; icon trang trí không lấn nội dung.

### 9.5 Concurrency và lỗi giữa transaction

- Gửi trùng payment/ownership/reminder/journal cùng idempotency.
- Hai worker reminder và hai job GPS cùng claim.
- Close kỳ đồng thời với post journal.
- Mô phỏng lỗi sau nửa transaction; xác nhận rollback toàn bộ.
- Chạy trên PostgreSQL staging, không suy kết quả từ SQLite.

### 9.6 Deploy production

Chỉ deploy khi chủ hệ thống ký UAT:

1. Backup và thử restore vào database khác.
2. Ghi migration/version/checksum trước deploy.
3. Deploy trong khung giờ duyệt; provider live vẫn tắt.
4. Chạy read-only smoke, sau đó một canary nghiệp vụ được phê duyệt.
5. Bật provider theo từng kênh/nhóm whitelist, quan sát lỗi và chi phí rồi mới mở rộng.
6. Có rollback app về SHA cũ; dữ liệu dùng forward-fix/reversal, không tự hạ migration tài chính đã có dữ liệu.

## 10. Bộ test và lệnh kiểm tra

Mỗi chặng phải chạy tối thiểu:

```powershell
git status --short
.\php.cmd artisan test
npm.cmd run production
npm.cmd run build:static
git diff --check
```

Bổ sung suite riêng:

- `PermissionMatrixTest`
- `LeaseOwnershipWorkflowTest`
- `LeasePdfSnapshotTest`
- `ReminderProviderContractTest`
- `ReminderDispatchConcurrencyTest`
- `GpsProviderContractTest`
- `GpsAlertIdempotencyTest`
- `JournalPostingInvariantTest`
- `AccountingPeriodCloseTest`
- `AccountingReconciliationTest`
- PostgreSQL staging API/UAT scripts có guard cấm production host.

Không cho script UAT chạy nếu target không có cờ `HIMOTO_UAT_DATABASE=true` và project ref staging đã được allowlist.

## 11. File/module dự kiến thay đổi

| Nhóm | Vị trí chính |
|---|---|
| RBAC | `app/Support/PilotAccess.php` (chuyển tiếp), middleware/policies mới, controllers liên quan, router/menu/Vuex |
| Audit | migration/model/service `audit_events`, middleware gắn request ID |
| Thuê sở hữu | `LeaseContractService`, controller/routes, model/migration ownership, view PDF, trang lease-to-own |
| Nhắc nợ | `CustomerReminderService`, provider interface/adapter, jobs/commands/webhook controller, outbox migration, UI reminder |
| GPS | `GpsProviderInterface`, `GpsService`, adapter mới, jobs/webhook, models/migrations GPS, UI overview/recovery |
| Kế toán | `AccountingService` được tách thành posting/period/reconciliation services, models/migrations journal, accounting UI/report/export |
| UAT | `tests/Feature/Himoto`, `tests/Unit`, scripts staging có safety guard, báo cáo trong `docs/` |


Tổng: khoảng **43–70 ngày công**, có thể rút ngắn theo lịch nếu provider và chính sách được cung cấp sớm. Không nên gộp kế toán, GPS và nhắc nợ vào một lần deploy lớn.

## 13. Definition of Done toàn bộ Excel

Chỉ được tuyên bố hoàn thiện khi:

- Mọi quyền được kiểm ở server và ma trận vai trò đã ký.
- PDF và chuyển quyền chạy từ snapshot, có audit và concurrency test.
- GPS có dữ liệu provider thật; nhắc nợ có delivery proof hoặc được ký BLOCKED ngoài phạm vi.
- Kế toán có sổ kép, khóa kỳ, reversal và đối soát khớp dataset chuẩn.
- Toàn bộ UAT chạy qua Render + Supabase staging thật, không mock API.
- Có backup đã thử restore, rollback app, hướng dẫn vận hành và bằng chứng không còn dữ liệu test mồ côi.
- Báo cáo nghiệm thu ghi rõ PASS/BLOCKED theo từng dòng Excel; không dùng số test passing thay cho xác nhận nghiệp vụ.

## 14. Việc cần làm ngay tiếp theo

1. Chủ hệ thống cung cấp/chốt bảng đầu vào ở mục 3.2.
2. Đổi credential đã lộ và tạo Supabase/Render staging riêng.
3. Thực hiện chặng 1 RBAC/audit trước mọi provider và kế toán.
4. Trong lúc chờ provider, làm PDF thuê sở hữu và schema/state machine chuyển quyền ở chế độ khóa `execute`.
5. Chốt chart of accounts và posting rules với kế toán trước khi viết migration journal production.

## 15. Kết quả review bản triển khai hiện tại (17/09/2026)

Phần này là kết quả kiểm tra mã trong working tree sau khi nhận báo cáo “đã hoàn thành 6 chặng”. Báo cáo đó **không được coi là bằng chứng nghiệm thu**: các provider mới chỉ là sandbox, test chủ yếu tạo schema tạm bằng SQLite và database Supabase đang kiểm tra chưa có các migration mới. Working tree cũng chưa phải một release commit sạch.

### 15.1 Đã kiểm chứng được

| Hạng mục | Kết quả review | Trạng thái |
|---|---|---|
| PHPUnit | Lần chạy đầy đủ trước các chỉnh sửa cuối: 165 tests / 843 assertions, exit 0 | PASS cục bộ; phải chạy lại sau commit RC |
| Frontend | `npm.cmd run production` hoàn tất, chỉ còn cảnh báo Sass/Browserslist | PASS build; chưa phải UAT trình duyệt |
| Route | Route webhook nhắc nợ đã đưa ra ngoài JWT; route trùng trong nhóm auth đã bỏ | PASS cấu hình; cần test HMAC provider |
| Lease ownership | Eloquent eager-load dùng đúng `requester/approver/executor`; kiểm tra dư nợ không cộng tiền đặt cọc chưa thu; chặn hồ sơ active trùng trong transaction | PASS unit cần bổ sung; chưa PASS staging |
| Schema guard | Đã bổ sung yêu cầu audit/ownership/reminder/GPS/accounting vào operational check và manifest checksum | PASS read-only local |
| Supabase | Read-only check trả `BLOCKED`: còn thiếu các migration 000001–000005 và 000011 cùng bảng/column tương ứng | BLOCKED; chưa chạy migration |

### 15.2 Khoảng trống còn tồn tại

1. **Nhắc nợ:** chỉ có `SandboxReminderProvider`; controller vẫn gọi đường tương thích `processOutbox()` và chế độ live chủ động giữ trạng thái pending vì chưa có adapter thật. Chưa có delivery proof từ SMS/Zalo/email, consent thật, queue worker production và webhook provider đã ký.
2. **GPS:** chỉ có `SandboxGpsProvider` với dữ liệu mô phỏng; chưa có mapping thiết bị thật, endpoint sync/history/recovery đầy đủ, credential, webhook/polling thật hoặc dữ liệu provider đọc lại từ Supabase staging.
3. **PDF thuê sở hữu:** binary PDF hiện do renderer tối giản tự tạo, dùng chuỗi ASCII và dữ liệu quan hệ hiện tại. Chưa có cột snapshot bất biến được chụp lúc chốt hợp đồng, font Unicode tiếng Việt, logo HIMOTO từ tài sản được duyệt, hash/version và kiểm tra nội dung sau khi đổi khách/xe.
4. **Chuyển quyền:** state machine cơ bản đã có nhưng còn phải khóa theo chính sách đã ký, ràng buộc unique active request ở PostgreSQL, ghi bút toán liên quan (nếu chính sách yêu cầu), kiểm concurrency trên PostgreSQL và chứng minh reversal/correction.
5. **RBAC:** capability fallback và middleware đã có, nhưng chưa có ma trận HTTP 403 chạy trên Render/Supabase; một số route HR/KPI/kế toán cũ cần rà lại scope store và permission riêng. Không dùng việc ẩn menu làm bằng chứng.
6. **Kế toán:** journal/period/reconciliation là lớp mới chưa nối đầy đủ với mọi transaction/két/VAT/tài sản hiện hữu. Sinh số chứng từ bằng “đọc bản ghi cuối” có thể đụng khi concurrent; so sánh tiền dùng float; đóng kỳ chưa lock cùng transaction post; endpoint VAT/tài sản cũ còn phải đổi từ hard-delete sang void/reverse.
7. **UAT:** chưa có release candidate deploy qua Render với PostgreSQL Supabase staging, backup/restore thử, browser E2E không mock, checksum trước–sau và cleanup `test_run_id`.

### 15.3 Cổng trạng thái sau review

- Chặng 0: **BLOCKED_OWNER_INPUT** — quyết định provider, chính sách chuyển quyền, chart of accounts và staging/secret rotation chưa có chữ ký chủ hệ thống.
- Chặng 1: **IN_PROGRESS** — khung RBAC/audit đã có; còn test HTTP theo vai trò, scope và audit completeness.
- Chặng 2: **IN_PROGRESS/BLOCKED_POLICY** — workflow cơ bản có; PDF snapshot/legal output và chính sách execute chưa đạt.
- Chặng 3: **BLOCKED_EXTERNAL_PROVIDER** — sandbox only.
- Chặng 4: **BLOCKED_EXTERNAL_PROVIDER** — sandbox only.
- Chặng 5: **IN_PROGRESS/BLOCKED_ACCOUNTING_POLICY** — schema/service/test invariant có; chưa tích hợp nguồn giao dịch và chưa được kế toán duyệt mapping.
- Chặng 6: **BLOCKED_STAGING_UAT** — chưa có bằng chứng Render + Supabase staging thật.

### 15.4 Tiến độ thực hiện các chặng R0–R2 cục bộ (Cập nhật 17/09/2026)

- **R0 (Đóng băng bằng chứng):** ĐÃ HOÀN TẤT CỤC BỘ. Đã tạo nhánh `review/20260917-rc` từ SHA `a1a20bc`. Lưu toàn bộ bằng chứng test (171 tests, 873 assertions), build production, static build, git diff check, route list tại `docs/evidence/rc-a1a20bc/`.
- **R1 (RBAC & Audit server-side):** ĐÃ HOÀN TẤT CỤC BỘ. Chuẩn hóa JSON 403, trang 403 Vue và route `/403`, bộ test `PermissionMatrixTest` (11 test case) kiểm soát chặt chẽ cross-store, BOD, Accountant, HR và audit data redaction.
- **R2 (Snapshot PDF & Ownership Safety):** ĐÃ HOÀN TẤT CỤC BỘ. Migration `000012` thêm cột `document_snapshot*`, `LeaseDocumentSnapshotService` chụp bất biến thông tin hợp đồng/khách/xe, `LeasePdfService` đọc cố định từ snapshot kèm đọc tiền bằng chữ tiếng Việt; feature flag `HIMOTO_ENABLE_OWNERSHIP_EXECUTE` khóa chức năng execute. Test `LeasePdfSnapshotTest` và `LeaseOwnershipWorkflowTest` đạt 100%.
- **R3–R6:** Giữ nguyên trạng thái `BLOCKED` chờ các đầu vào từ chủ hệ thống, kế toán và bên thứ ba theo mục 17.

## 16. Plan sửa sau review — thứ tự và cách làm cụ thể

### R0 — Đóng băng bằng chứng và làm sạch release candidate (0,5–1 ngày)

1. Giữ nguyên các chỉnh sửa đang có, tạo branch `review/20260917-rc` và lưu SHA hiện tại; không squash hoặc xóa migration chưa review.
2. Chạy lại `php.cmd artisan test`, `npm.cmd run production`, `npm.cmd run build:static`, `git diff --check`; lưu output vào `docs/evidence/rc-<sha>/`.
3. Chạy `scripts/check_operational_schema.php` ở chế độ read-only; nếu target là production hoặc không có `HIMOTO_UAT_DATABASE=true` thì dừng với `BLOCKED`.
4. Không đưa `.env`, DSN, token, số điện thoại/CCCD đầy đủ hoặc payload provider vào commit/evidence.

**Điều kiện qua R0:** working tree có danh sách file rõ ràng, test/build lặp lại được, không còn lỗi runtime quan sát được trong route list.

### R1 — Hoàn tất RBAC và audit server-side (2–4 ngày)

1. Chốt ma trận role × permission × store với chủ hệ thống; seed chỉ chạy staging trước, không seed live khi chưa ký.
2. Gắn middleware/policy vào từng route nhạy cảm: KPI, HR, lease ownership, reminder dispatch, GPS mapping/recovery, accounting post/reverse/close/reconcile/export.
3. Mọi controller lấy `store_id` từ record đã khóa và gọi `PermissionAccess::can()`; không tin `store_id` do client gửi nếu không có scope công ty.
4. Chuẩn hóa JSON 403/422; tạo trang 403 riêng, không biến lỗi quyền thành 404.
5. Audit service lọc secret/PII, lưu request ID, actor, subject, store, before/after và lý do. Bổ sung audit cho export PDF, cấp quyền, void/reverse và webhook trạng thái.
6. Viết `PermissionMatrixTest` bằng request thật: staff cơ sở A gọi URL cơ sở B = 403; HR không đọc kế toán; BGĐ xem nhưng không post; kế toán không sửa HR; quyền có hiệu lực sau refresh/re-login.

**Điều kiện qua R1:** tất cả route trong ma trận trả đúng 200/403/422 trên PostgreSQL staging; mỗi thao tác nhạy cảm có đúng một audit event không lộ PII.

### R2 — Snapshot PDF và chuyển quyền an toàn (3–6 ngày)

1. Thêm migration backward-compatible cho `lease_contracts.document_snapshot`, `document_snapshot_hash`, `document_snapshot_version`, `document_snapshot_locked_at`; kiểm tra `hasColumn` trước khi thêm.
2. Khi tạo/chốt hợp đồng, trong cùng transaction chụp đầy đủ bên A/B, CCCD/ngày/nơi cấp, xe, giá, cọc, kỳ, phụ kiện, người ký, ghi chú và logo asset version. Snapshot chỉ ghi một lần sau khi hợp đồng hợp lệ.
3. Tạo `LeaseDocumentSnapshotService`; PDF contract chỉ đọc snapshot, tuyệt đối không fallback sang tên/giá hiện tại nếu snapshot đã khóa. Debt statement ghi rõ `as_of` và nguồn allocation tại thời điểm xuất.
4. Dùng renderer/font Unicode đã pin tương thích PHP 7.4/Laravel 5.8; nhúng logo HIMOTO đã được duyệt; kiểm `application/pdf`, A4, số tiền bằng số/chữ, header mã/version/hash và tên file an toàn.
5. Sửa schema/test fixture dùng đúng cột production (`license/name/chassis/engine`, `period_amount/installment_count`); thêm test đổi khách/xe sau khóa nhưng PDF/hash không đổi.
6. Với ownership: thêm unique active request theo contract ở PostgreSQL (partial unique index hoặc cơ chế lock tương đương), lock contract → vehicle → request, kiểm dư nợ bằng allocation active/null status, maker-checker và idempotency.
7. `execute` vẫn bị feature-flag khóa tới khi chính sách chuyển quyền được `APPROVED`; sai sót dùng reversal/correction, không delete. Nếu có phí/chiết khấu, posting rule phải được kế toán duyệt trước.

**Điều kiện qua R2:** PDF snapshot/hash ổn định sau refresh và thay dữ liệu quan hệ; workflow draft → submitted → approved → executed/rejected/cancelled xử lý đúng quyền, nợ, retry và concurrency trên staging.

### R3 — Provider nhắc nợ thật, nhưng bật theo whitelist (3–5 ngày/provider)

1. Chốt kênh/provider/template/brandname/quiet hours/consent/opt-out và lấy sandbox credential. Nếu thiếu, giữ `BLOCKED_EXTERNAL_PROVIDER` và không viết adapter giả.
2. Bind `ReminderProviderInterface` theo `REMINDER_PROVIDER`; adapter chỉ nhận secret từ env/secret store, có timeout, retry, correlation/idempotency key và không log auth header.
3. Đưa dispatch vào queue database/Redis; job claim bằng row lock, kiểm lại paid/cancel/consent/quiet hours ngay trước HTTP call. `dry_run` chỉ đọc, không đổi `sent`.
4. Mở rộng outbox/event với provider message ID, attempt/next retry/lock/dead-letter; webhook public không JWT, chỉ HMAC + timestamp/replay guard + rate limit, payload đã rút gọn.
5. Controller gọi đường dispatch mới; đường tương thích cũ chỉ được giữ cho dry-run và trả trạng thái rõ ràng, không giả thành “đã gửi”.
6. Test hai worker, paid-before-send, timeout/429/5xx/4xx, webhook trùng và retry idempotency bằng sandbox; chỉ whitelist số nội bộ.

**Điều kiện qua R3:** provider sandbox trả message ID và webhook delivered/failed đọc lại từ DB staging; live flag vẫn false cho tới khi chủ hệ thống ký bật.

### R4 — GPS provider thật và cảnh báo (3–5 ngày/provider)

1. Nhận tài liệu API, base URL, auth, device map và retention; thiếu đầu vào thì giữ sandbox + `BLOCKED`, không sinh tọa độ giả.
2. Bind adapter theo config; implement polling/webhook, cursor/batch, timeout/retry/circuit breaker, status mapping và provider event dedupe.
3. Bổ sung route/service cho mapping device, sync thủ công/job, history giới hạn thời gian, alert acknowledge/resolve và recovery action; mọi route kiểm store scope server-side.
4. Lưu `provider_recorded_at` và `received_at`; loại 0,0/missing; stale theo timestamp provider; alert mở/đóng idempotent, retention có job và chính sách.
5. Test payload ẩn danh moving/stopped/stale/offline/never-connected, timeout không xóa vị trí cuối, webhook/poll trùng, khác cơ sở = 403.

**Điều kiện qua R4:** ít nhất một device sandbox thật của provider trả dữ liệu, đọc lại sau refresh từ Supabase staging, có ảnh UI và query chứng minh alert/position không trùng.

### R5 — Kế toán sổ kép, khóa kỳ, đối soát (7–12 ngày)

1. Kế toán ký chart of accounts, normal balance, VAT, nguồn tiền, opening balance, ngày bắt đầu và posting rules version. Không tự đoán mã tài khoản sản xuất.
2. Dùng decimal string/tiền theo minor unit cho invariant; không dùng `!==` giữa float. DB constraint bảo vệ debit/credit không âm, không đồng thời và entry posted có ≥2 lines.
3. Tạo sequence/lock riêng cho số chứng từ theo tháng; không sinh số bằng “last row” khi concurrent. Unique source/idempotency ở DB.
4. `JournalPostingService` khóa period row trong transaction rồi mới validate/post; `PeriodCloseService` khóa cùng period và chặn post/backdate đồng thời. Reversal giữ entry gốc bất biến và tạo entry đối ứng.
5. Tách quyền `view/post/reverse/close/reopen/reconcile/export`; reconciliation bắt buộc tồn tại account mapping, không dùng account ID 0. Cross-store query phải bị scope ở service.
6. Tạo report mapping transactions cũ: mapped/ambiguous/invalid; shadow mode chỉ draft. Backfill chỉ sau mốc được ký, lưu source/checksum. VAT/tài sản đổi hard-delete thành void/dispose/reverse có lý do/audit.
7. Test invariant/property, duplicate idempotency, concurrent post/close, rollback sau header trước line, reversal, trial balance, GL, VAT, asset và cash/bank reconciliation.

**Điều kiện qua R5:** dataset chuẩn do kế toán ký có debit = credit, trial balance/ledger/VAT/tài sản/két/ngân hàng khớp; kỳ đóng chặn đúng và mọi điều chỉnh để lại dấu vết.

### R6 — UAT staging, Render và cổng production (3–6 ngày)

1. Tạo Supabase staging riêng và rotate ngay credential từng xuất hiện trong màn hình/chat; Render staging trỏ đúng project, `APP_DEBUG=false`, queue/scheduler rõ ràng.
2. Backup và thử restore sang database khác; migration dry-run, review SQL, checksum; không chạy migration trên DSN production.
3. Deploy API trước rồi web cùng SHA; health/schema readiness, runtime query `current_database/current_schema/now()` chỉ log kết quả đã che.
4. Mỗi test run có `test_run_id`, request ID và danh sách record; CRUD create/read/update/audit/read-after-refresh/re-login/worker; tài chính cleanup bằng reversal, không hard-delete phiếu thật.
5. Browser E2E không mock API ở 360/390/768/1024/1440px: RBAC, PDF/download, reminder sandbox, GPS stale, journal/close/reconcile, loading/error/empty, deep-link/refresh/logout-login, keyboard/focus.
6. Lưu `docs/supabase-verification-<date>.md`, ảnh/PDF, HTTP status, query checksum, migration SHA, record IDs đã cleanup và kết luận PASS/BLOCKED.
7. Production chỉ sau chữ ký UAT: backup/restore đã thử, deploy window được duyệt, provider live mở từng whitelist; rollback app về SHA cũ, dữ liệu sửa bằng forward-fix/reversal.

**Điều kiện qua R6:** mọi dòng Excel có bằng chứng PASS hoặc BLOCKED có người chịu trách nhiệm/ngày mở khóa; không dùng số test pass để thay cho provider/UAT/legal/accounting sign-off.

## 17. Danh sách việc cần giao ngay

| Chủ trì | Đầu vào cần giao | Chặn phần nào |
|---|---|---|
| Chủ hệ thống | Supabase/Render staging ref và xác nhận rotate secret | R0, R6 |
| Chủ hệ thống + nhà cung cấp | GPS API/device map/sandbox credential | R4 |
| Chủ hệ thống + nhà cung cấp | Kênh nhắc, template, brandname, webhook, sandbox credential, consent | R3 |
| Chủ hệ thống + kế toán | Quy tắc chuyển quyền, chart of accounts, posting rules, kỳ khóa | R2, R5 |
| Chủ hệ thống | Danh sách người BGĐ/kế toán/HR và phạm vi cơ sở | R1 |
| Kỹ thuật | Font/logo tài liệu đã duyệt và quyết định renderer PHP 7.4 | R2 |

Trong thời gian chờ đầu vào, chỉ làm R0–R2 ở staging/local; giữ provider live và `ownership execute` ở trạng thái khóa. Không commit/push bản này như “hoàn thiện toàn bộ” cho tới khi R6 đóng tất cả cổng.
