# HIMOTO — Plan hoàn thiện theo Excel và handoff 324 dòng

Ngày lập: 16/09/2026. Tài liệu này đối chiếu `himoto-pilot-implementation-handoff.md` với `E:/duanthuexe/Thông tin yêu cầu về APP.xlsx` (Sheet1, 10 nhóm yêu cầu). Đây là kế hoạch giao việc cho AI thực hiện, không phải xác nhận các tính năng đã xong.

## 1. Kết quả đối chiếu hiện trạng

| Excel | Đã có trong pilot | Còn phải làm để đạt yêu cầu |
|---|---|---|
| Màu sắc | Có Inter, responsive một số màn hình | Audit toàn bộ theme, giảm màu chói, bỏ icon |
| Tạo hợp đồng | Form, preview, in A4, mã cũ `YYYY/MM/DD-0001`, snapshot, thu tiền | Xác nhận đổi sang `YYYYMMDD-0001`; đủ trường theo PDF; preview mọi kích thước |
| Đơn trong ngày | Có danh sách nền | Bố cục dễ đọc, bộ lọc ngày, vùng ảnh sau này |
| Két tính tiền | Có transaction/cash/bank cơ bản | Sổ két theo ngày/cơ sở, số đơn, tiền mặt, ngân hàng, đối soát và khóa kỳ |
| Kho xe | Thẻ kho, quyền admin, điều chuyển cơ bản | Đổi xe khác cơ sở, ledger đầu-cuối, idempotency và UAT đầy đủ |
| Thuê sở hữu | Form, lịch kỳ, nợ, ghi chú, Excel | Tách KPI/kho, tất toán, đảo thu, chuyển quyền, PDF, GPS/provider |
| Báo cáo/KPI | Một số dashboard có sẵn | Quyền BGĐ/kế toán, KPI sale/lead/chiến dịch/phòng ban |
| Kế toán | Transaction nền | Sổ tài khoản, VAT, tài sản, liên kết cơ sở, kỳ đóng sổ |
| HCNS | Chưa đủ | Nhân sự, sơ đồ, liên hệ, ca, chấm công, lịch trực |

Ước lượng theo **nhóm chức năng**: hợp đồng/thu tiền/kho cơ bản đạt khoảng 55–60%; toàn bộ Excel chưa đạt. Các mục phụ thuộc provider hoặc chính sách chưa được tự suy đoán.

## 2. Quy tắc giao việc bắt buộc

1. **Bỏ toàn bộ icon** trong UI nghiệp vụ: icon trong menu, nút, tiêu đề, badge, bảng, thông báo và placeholder đều phải bỏ; dùng chữ, màu trạng thái, đường viền và khoảng cách. Không thêm icon mới để thay thế.
2. Giữ Inter; nội dung 400–500, tiêu đề 600–700. Màu nền dịu, tương phản WCAG, không dùng gradient/chớp/chói.
3. Không sửa hoặc xóa giao dịch/số HĐ cũ. Dữ liệu mới phải có audit log, người sửa, thời điểm và lý do.
4. Mọi API phải kiểm quyền ở server; ẩn nút không được coi là phân quyền.
5. Không đánh dấu hoàn thành nếu chỉ có mock/fixture. GPS, SMS/Zalo/email phải ghi BLOCKED khi chưa có provider và credential.
6. Không chạy migration live trong lúc phát triển. Migration phải backward-compatible, có backup, dry-run, rollback và kiểm checksum.

## 3. Cách kiểm tra Supabase thật

### 3.1 Xác định kết nối

- Kiểm tra Render environment đang trỏ đúng Supabase project; không in giá trị secret.
- Ghi lại host/project ref dạng đã che một phần, schema, migration version, commit deploy.
- Xác nhận Laravel dùng PostgreSQL/Supabase thật ở runtime bằng `select current_database(), current_schema(), now()` và log chỉ kết quả không nhạy cảm.
- Kiểm tra frontend không gọi trực tiếp Supabase bằng anon key để ghi dữ liệu nếu API Laravel là lớp nghiệp vụ.

### 3.2 Ma trận CRUD phải kiểm

Với mỗi bảng nghiệp vụ (`stores`, `vehicles`, `orders`, `order_vehicle_details`, `transactions`, `banks`, `cash`, `lease_contracts`, `lease_installments`, `lease_payment_allocations`, `debt_notes`, `vehicle_transfers`, `vehicle_location_events`, `customer_reminder_outbox`):

1. Tạo một record có `test_run_id` và dữ liệu giả lập.
2. Đọc lại qua API và truy vấn Supabase bằng khóa chính; so sánh field, timezone, kiểu số và người tạo.
3. Sửa một field; xác nhận API response, DB row và audit log cùng giá trị.
4. Xóa chỉ record test nếu chính sách cho phép; nếu bảng tài chính thì kiểm tra soft-delete/đảo giao dịch, tuyệt đối không hard-delete phiếu thật.
5. Chạy lại sau refresh/re-login và từ một worker/request khác để chứng minh không chỉ nằm trong state/cache.
6. Chụp kết quả count/checksum trước–sau; xóa dữ liệu test theo `test_run_id`; xác nhận không có record mồ côi.

### 3.3 Kiểm tra transaction/concurrency

- Hai request tạo cùng mã idempotency: đúng một row và một giao dịch.
- Hai request thu cùng hợp đồng: tổng allocation không vượt dư nợ.
- Hai request nhận cùng phiếu điều chuyển: chỉ một chuyển trạng thái.
- Kiểm rollback khi lỗi giữa transaction: không đổi vehicle, order, ledger hoặc snapshot nửa chừng.
- Chạy trên PostgreSQL/Supabase staging, không suy ra từ SQLite.

### 3.4 Bằng chứng bắt buộc

Lưu `supabase-verification-<date>.md` gồm commit, migration, bảng đã kiểm, test_run_id đã xóa, query checksum, HTTP status, ảnh UI và kết luận PASS/BLOCKED. Không lưu token, mật khẩu, full phone/CCCD hoặc dữ liệu khách thật.

## 4. Chặng A — nền tảng UI và bỏ icon

1. Tìm toàn bộ `<i>`, SVG icon, FontAwesome/Material icon, icon button, `flaticon`, emoji trong `resources`, layout, static bundle.
2. Lập danh sách trước/sau; thay nút icon-only bằng chữ rõ nghĩa (`Xem`, `Sửa`, `Xóa`, `In`, `Lưu`, `Điều chuyển`).
3. Chuẩn hóa CSS biến màu, font weight, spacing, focus keyboard, mobile breakpoints.
4. Chạy screenshot 360/390/768/1024/1440px; kiểm không có icon còn sót, không tràn ngang, nhãn không xuống dòng sai.
5. Build bundle, kiểm `git diff --check`, chạy accessibility smoke.

## 5. Chặng B — hợp đồng và thu tiền (P0)

1. Chốt format mã: Excel ghi `YYYYMMDD-0001`, handoff ghi `YYYY/MM/DD-0001`; hỏi chủ hệ thống trước khi đổi. Giữ số cũ.
2. Tạo schema field matrix từ PDF: bên A, bên B, CCCD/ngày/nơi cấp, xe, GPLX/ngày cấp, phụ kiện, thời gian, tiền, cọc, tài sản, người ký, ghi chú.
3. Validator phải yêu cầu đủ field trước preview; chỉ field có dữ liệu mới in, không dùng giá trị bịa.
4. Giá lấy từ từng `order_item` và bảng giá theo số ngày/bậc; preview, snapshot, list và print dùng cùng nguồn.
5. Preview thuần: không tạo order, transaction, số HĐ hay snapshot; lỗi mạng không ghi đè dữ liệu mới.
6. Chốt hợp đồng: cấp số atomically, snapshot bất biến; in lại luôn dùng snapshot; tìm theo `orders.contract_number` hoặc số trong snapshot legacy.
7. Thu tiền: TM/CK cá nhân/CK công ty; tài khoản đúng cơ sở; idempotency; ngày thu; không vượt nợ; đối soát allocation–transaction–két.
8. Nút `Xem trước` cạnh `Lưu`; nút `In` sau khi lưu ở danh sách/chi tiết; in bằng tài liệu A4 độc lập.
9. UAT P0: nhập ngày 02/01/2020, thuê 2/3 ngày, bậc 150.000, tính phí quá hạn 550.000, sửa blur GPLX, retry thu, chốt rồi sửa khách.

## 6. Chặng C — Đơn trong ngày và két

1. Query theo timezone Asia/Ho_Chi_Minh, ngày tạo/nhận/trả tách rõ; bảng chữ không icon, cột ưu tiên khách–SĐT–xe–giờ–tiền–trạng thái.
2. Thêm filter cơ sở/trạng thái/keyword, tổng số đơn và phân trang server.
3. Tạo bảng két ngày: opening, số đơn, tiền cọc, phí thuê, gia hạn, hoàn, phạt, TM, CK cá nhân, CK công ty, chênh lệch.
4. Chốt ngày chỉ admin/kế toán; sau chốt dùng phiếu điều chỉnh, không sửa lịch sử.
5. So sánh số liệu API, transactions, cash/bank ledger và Supabase query; xuất Excel/PDF.
6. Vùng ảnh để placeholder chữ “Ảnh biên bản (sắp hỗ trợ)”; chưa giả vờ upload/chụp thật.

## 7. Chặng D — Kho và điều chuyển

1. Admin thấy 5 thẻ cơ sở vật lý + kho Thuê sở hữu; cơ sở chỉ thấy aggregate của mình.
2. Chi tiết xe: biển số, loại, trạng thái, kho quản lý, vị trí thực tế, km, lịch sử; không lộ xe khác quyền.
3. Flow A kho–kho: dispatch → in transit → receive/cancel, khóa xe theo ID tăng dần, retry idempotent.
4. Flow B trả khác cơ sở: chỉ đơn đã hoàn tất, đơn một xe hoặc từng xe rõ ràng; giữ kho doanh thu, cập nhật vị trí và ledger.
5. Flow C đổi xe: xe cũ thuộc đơn đang thuê, xe mới ready, cùng/khác cơ sở qua phiếu điều chuyển, phụ lục và chênh lệch giá có hạch toán.
6. Test đồng thời, lỗi giữa transaction, nhận trùng, hủy, xe đang thuê, xe không thuộc đơn.

## 8. Chặng E — Thuê sở hữu và công nợ

1. Kho độc lập, KPI/doanh số tách 5 kho vật lý; quyền admin/kế toán/nhân viên theo store.
2. Hợp đồng: kỳ 0 đặt cọc phải thu, kỳ tháng không tràn ngày, số dư và trạng thái tính từ allocation.
3. Công nợ: current/1–7/8–30/>30, danh sách đỏ, phân loại normal/reminder/warning/bad_debt, note lịch sử và tag đầu việc.
4. Luồng thu một phần/nhiều kỳ/tất toán/đảo thu/điều chỉnh có audit; quy định chuyển quyền sở hữu phải được duyệt trước khi code.
5. Excel/PDF phải lấy đúng snapshot/giao dịch và phân trang đủ, không dùng dữ liệu bán xe cũ để suy thành thuê sở hữu.
6. GPS: adapter provider thật, mapping thiết bị, stale/offline/stopped, cảnh báo và kế hoạch thu hồi; chưa có credential thì BLOCKED.
7. Nhắc: outbox/job retry, không trùng, không gửi đã trả, template/kênh được duyệt; dry-run không đổi trạng thái sent.

## 9. Chặng F — Báo cáo, kế toán, HCNS

### Báo cáo/KPI

- Quyền BGĐ/kế toán; KPI ngày/tháng sale, lead, tiến độ tìm khách, chiến dịch, phòng ban.
- Định nghĩa từng chỉ số, timezone, nguồn bảng, công thức và snapshot kỳ báo cáo.
- Drill-down có scope; export CSV/Excel/PDF; kiểm số liệu với ledger.

### Kế toán/công nợ

- Chart of accounts, thu–chi, tiền tương đương tiền, ngân hàng/két, VAT xe bán, hợp đồng, tài sản nội bộ/phòng giao dịch.
- Khóa kỳ, điều chỉnh có bút toán đối ứng; không sửa phiếu đã chốt.
- Đối chiếu ngày/tháng/cơ sở với Supabase và báo cáo hiện hữu.

### HCNS

- Nhân sự, sơ đồ, liên hệ, cửa hàng/phòng ban, ca/chấm công, lịch trực theo ngày.
- Phân quyền HR; bảo vệ dữ liệu cá nhân; audit thay đổi; export theo quyền.

## 10. Cổng nghiệm thu và lệnh chạy

Không chuyển chặng nếu còn lỗi P0/P1 hoặc dữ liệu Supabase chưa đối chiếu. Mỗi chặng phải có commit, migration, test, screenshot/PDF, query kiểm DB, rollback note và trạng thái PASS/BLOCKED.

```powershell
git status --short
.\php.cmd artisan test
npm.cmd run production
npm.cmd run build:static
git diff --check
```

Browser E2E phải dùng staging Supabase riêng với test_run_id; kiểm 360/390/768/1024/1440px, keyboard, preview–save–print, CRUD refresh và quyền từng vai trò. Sau deploy kiểm lại commit trên Render, migration version, health endpoint, một CRUD canary đã xóa/đảo đúng và không có secret trong log/bundle.

## 11. Prompt giao nguyên văn cho AI khác

> Đọc `docs/himoto-pilot-implementation-handoff.md`, tài liệu này, `E:/duanthuexe/Thông tin yêu cầu về APP.xlsx` và mẫu PDF. Đối chiếu từng dòng, không coi code/mock là đã nghiệm thu. Làm theo chặng A–F, ưu tiên P0 hợp đồng/thu tiền/két. Bỏ toàn bộ icon trong UI, giữ Inter và màu dịu. Kiểm quyền server, snapshot, số cũ, giá theo từng xe và ngày Việt Nam. Mọi tạo/sửa/xóa phải kiểm chứng đọc lại từ Supabase thật sau refresh, có test concurrency, audit và bằng chứng checksum; không dùng cache/state làm bằng chứng. Không chạy migration live khi chưa được duyệt, không gửi nhắc khách/GPS thật khi thiếu provider, không tự đặt chính sách tài chính. Mỗi chặng phải báo file/commit/test/screenshot/query Supabase và PASS/BLOCKED; chỉ tuyên bố hoàn thiện khi mọi yêu cầu Excel đã có bằng chứng.
