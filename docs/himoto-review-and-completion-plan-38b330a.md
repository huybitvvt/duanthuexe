# HIMOTO — Review 38b330a và kế hoạch hoàn thiện đến nghiệm thu

Ngày kiểm tra: 16/09/2026. Baseline: local `main` tại `38b330a`, trước đó `4f506e4` và `7913bda`.

## 1. Kết luận và phạm vi bằng chứng

**CHƯA ĐẠT NGHIỆM THU. Không dùng báo cáo PASS 100% của phiên trước làm căn cứ phát hành.** Có bổ sung thật về két, HCNS, tất toán, đảo thu và sửa một phần giao diện; nhưng còn lỗi tiền, quyền truy cập và credential đã nằm trong commit.

- `git ls-remote origin refs/heads/main` trả `4f506e47a678463606d4852f214960e560eb3a90`: tại thời điểm review, `38b330a` chưa nằm trên remote main. Chưa kiểm tra Render runtime/deploy.
- Đã đọc báo cáo AI, bốn tài liệu bàn giao, Excel trực tiếp bằng openpyxl (Sheet1, hàng 2–11), metadata/text PDF gốc (1 trang A4 ngang), diff và các luồng mã liên quan. Chưa nghiệm thu lại bản in bằng hình ảnh/giấy trong phiên này.
- Chạy lại PHPUnit bằng PHP portable + `../tools/phpunit.phar`: **84 tests, 416 assertions, exit 0**. Bootstrap cưỡng chế SQLite memory; đây không phải kiểm thử Supabase/PostgreSQL.
- Chạy riêng bằng chứng tái hiện: **5 tests, 23 assertions, exit 0**. Những assertion này xác nhận hành vi lỗi đang tồn tại, KHÔNG xác nhận nghiệp vụ đúng. File: `docs/review-38b330a/ReproduceReviewTest.php`.
- Chưa chạy lại frontend build/browser/live CRUD; build thành công là kết quả AI trước báo cáo, chưa được xác nhận độc lập trong phiên review này.
- Không chạy hai script kết nối Supabase hiện hữu: có mật khẩu hardcode và script CRUD chứa TRUNCATE toàn bảng canary. Không ghi dữ liệu live, không chạy migration, không sửa code nghiệp vụ trong phiên review.
- Tài liệu này thay thế kết luận nghiệm thu của `docs/supabase-verification-20260916.md`; giữ tài liệu cũ làm lịch sử, không tiếp tục trích PASS 100% từ đó.

## 2. Phát hiện phải xử lý trước phát hành

Mức bằng chứng: **Tái hiện** = đã chạy trên SQLite riêng; **Code** = đọc đường thực thi, chưa chạy HTTP/live; **Thiếu chứng cứ** = không đủ căn cứ nghiệm thu.

| ID / ưu tiên | Vị trí | Lỗi, tác động và bằng chứng |
|---|---|---|
| R01 / P0 | `scripts/test_supabase_connection.php:6`, `scripts/verify_supabase_crud.php:11` | Mật khẩu DB ghi trực tiếp trong source đã commit; cũng lộ trong báo cáo đính kèm. **Code**. Phải thu hồi/rotate trước khi phát hành, không chỉ xóa dòng trong working tree. Không chép lại giá trị secret. |
| R02 / P0 | `LeaseContractService.php:315–359` | `settlement_amount=0` vẫn ghi mọi kỳ `amount_paid=amount_due` và hợp đồng completed. Tái hiện hợp đồng 12 triệu: 12 triệu được đánh dấu đã trả, 0 transaction, 0 allocation. Discount chỉ ghép ghi chú, không có điều chỉnh nghĩa vụ tương ứng. **Tái hiện**. |
| R03 / P0 | `DailyCashRegisterController.php:110–120` | Truyền `store_id` đi vào nhánh lọc trước kiểm scope, nhân viên đọc được lịch sử cơ sở khác. **Tái hiện controller**. Nhánh không truyền store gọi `isSuperAdmin()/hasRole()` không thấy khai báo trong User, cần HTTP regression riêng. |
| R04 / P0 | `HrController.php`, `routes/api.php:276`, `Middleware/NonSale.php` | HR chỉ nằm trong nhóm non.sale; middleware này chỉ chặn role_id 4, không cấp quyền HR/admin. Controller/service không scope người đọc/ghi/xóa, nhận `$request->all()`, model có field CCCD/user_id. Không có policy tương ứng trong AuthServiceProvider đã kiểm. **Code**: không thể tuyên bố RBAC/che dữ liệu đã PASS. |
| R05 / P1 | `LeaseContractService.php:366–425` | Đảo thu hard-delete allocation gốc; phiếu chi không có cash_id/bank_id, không có liên kết reversal chuẩn. **Tái hiện**. Khóa allocation nhưng không thống nhất khóa contract/installment với allocatePayment; retry/concurrency chưa được chứng minh. |
| R06 / P1 | `CashRegisterService.php:145–180` | Phân loại bằng chữ trong name/desc, CK chưa rõ chủ tự rơi vào cá nhân; bỏ qua chi CK không phải hoàn cọc. **Tái hiện**: chi công ty 250.000 vẫn trả total_bank_company=0. Đổi mô tả có thể đổi cột báo cáo. |
| R07 / P1 | `CashRegisterService.php:246–320` | close dùng updateOrCreate, không chặn két đã closed, không khóa bản ghi; chốt lại ghi đè tiền đếm/người chốt/thời điểm. **Tái hiện**: chốt 0 rồi chốt 100 ghi đè cùng row. Log::info không thay được audit bất biến. |
| R08 / P1 | `CashRegisterService.php:23–100` | Khi store_id null, query lấy `first()` két bất kỳ; nếu két đó đã closed thì trả snapshot một cơ sở như tổng hệ thống. Opening balance cũng chỉ lấy một két trước đó. **Code**. Không thấy guard khóa ngày nối vào các đường ghi giao dịch. |
| R09 / P1 | `ModalInstallmentSchedule.vue:351`, `LeaseContractService.php:325–344` | UI gửi `notes`, service đọc `note`; UI không chọn TM/CK nên mặc định tiền mặt. Service gọi allocatePayment không chuyển bank_id/idempotency_key và gửi note sai key của allocatePayment. CK có thể lỗi tìm ngân hàng; mất ghi chú và thiếu retry an toàn. **Code**. |
| R10 / P1 | `scripts/verify_supabase_crud.php:27–145` | Chỉ INSERT/UPDATE/DELETE JSON trong `canary_verification_runs`; chuỗi module_name=lease_contracts không ghi vào bảng lease_contracts. Không qua Laravel/UI, không kiểm migrations, audit nghiệp vụ, re-login, rollback/concurrency. UPDATE chỉ in kết quả, không assert checksum mới; cuối script TRUNCATE xóa cả run khác. **Code**. |
| R11 / P1 | Header, drawer, form, preview, tài chính | Còn SVG và icon-only trong `HimotoHeader.vue`, `HimotoDrawer.vue`, `ItemsOrder.vue`, `OrderUpdate.vue`, `ModalContractPreview.vue`, `TransactionHistory.vue`, `ReceiptIndex.vue`, `Transaction.vue`… **Code**. Tuyên bố bỏ 100% icon là sai. |
| R12 / P1 | `VehicleTransferService.php:277–420` | Trả khác cơ sở vẫn chặn đơn nhiều xe; đổi xe vẫn chặn khác cơ sở và price_difference khác 0. File service không thay đổi trong commit mới. **Code**. Ba luồng đầy đủ chưa đạt như báo cáo. |
| R13 / quyết định | `ContractNumberService.php:45` | Đã đổi sang Ymd dù handoff yêu cầu chốt trước. Validator hỗ trợ hai format là tốt nhưng không thay bằng chứng chủ hệ thống duyệt đổi mã. Không renumber số đã phát hành. |
| R14 / phạm vi | Báo cáo nghiệm thu cũ | Tự chia lại “10 nhóm” làm mất nhóm KPI/chiến dịch, kế toán/VAT/tài sản; HR chỉ danh bạ/lịch trực không bao hết sơ đồ và chấm công. GPS BLOCKED cũng không thể được tính là đã hoàn thành toàn bộ. |

## 3. Ma trận yêu cầu gốc — không dùng phần trăm ước lượng

Trạng thái: PARTIAL = có một phần code, chưa đủ nghiệm thu; FAIL = có phản chứng; UNVERIFIED = chưa kiểm đủ; BLOCKED = thiếu quyết định/provider. PASS cuối cùng chỉ dành cho mục có bằng chứng UI/API + DB + quyền + hồi quy tương ứng.

| ID | Nguồn Excel / yêu cầu | Hiện trạng | Việc còn lại / bằng chứng phải giao |
|---|---|---|---|
| UI01 | Hàng 2: giảm màu chói | UNVERIFIED | Audit theme và mọi route, ảnh 5 kích thước, đo style thực tế |
| UI02 | Yêu cầu bổ sung: bỏ toàn bộ icon | FAIL R11 | Inventory source + runtime, thay bằng chữ, kiểm thư viện và pseudo-element |
| UI03 | Inter 400–500 / 600–700, mobile | PARTIAL | Kiểm computed font, modal/bảng/focus/zoom; không chỉ sửa sidebar |
| C01 | Hàng 3.1: vừa màn hình/điện thoại | PARTIAL | Browser 360/390/768/1024/1440 và 125/150% zoom |
| C02 | Hàng 3.2: đủ trường theo PDF | PARTIAL | Mỗi field đi hết nhập → API → DB → mở lại → snapshot → in |
| C03 | Hàng 3.3: xem trước/in | PARTIAL | No mutation khi preview; PDF/giấy một và nhiều xe, dữ liệu dài |
| C04 | Hàng 3.4: mã YYYYMMDD-0001 | BLOCKED quyết định | Ghi quyết định format/ngày/phạm vi counter, giữ legacy, test PostgreSQL |
| C05 | Hàng 3.5: TM/CK cá nhân/CK công ty đẩy sổ | PARTIAL | Chọn tài khoản, nguồn tiền, retry, ledger và két khớp nhau |
| O01 | Hàng 4: đơn trong ngày dễ xem | PARTIAL | Lọc ngày VN/cơ sở/status, tổng và phân trang; kiểm refresh/live |
| O02 | Hàng 4: chừa vùng ảnh | PARTIAL | Placeholder trung thực; không nghiệm thu upload chưa có |
| K01 | Hàng 5: số đơn/tiền ngày | FAIL R06–R08 | Tổng hợp nguồn chuẩn, không suy loại thu bằng nội dung chữ |
| K02 | Hàng 5: đối chiếu ngân hàng/TM thực tế | PARTIAL | Số đếm TM + sao kê từng tài khoản + chênh lệch/phê duyệt |
| K03 | Plan: khóa ngày, audit, điều chỉnh | FAIL R07 | Không sửa phiếu đã khóa; mở lại có quyền/lý do/phiên bản |
| W01 | Hàng 6: 5 thẻ cơ sở và quyền aggregate | PARTIAL | Store thật, admin/branch API/export, không hardcode ID |
| W02 | Hàng 6.1: kho → kho | PARTIAL | dispatch/receive/cancel, đồng thời, retry, tồn và ledger |
| W03 | Hàng 6.2: trả khác cơ sở | PARTIAL R12 | Nhận theo biển số/item, nhiều xe, giữ cơ sở doanh thu |
| W04 | Hàng 6.3: đổi cùng cơ sở | PARTIAL | Phụ lục, tình trạng xe cũ, phí tăng/giảm có hạch toán |
| W05 | Hàng 6.3: đổi khác cơ sở | FAIL R12 | Bàn giao hai chiều, vị trí/kho quản lý, rollback toàn bộ |
| L01 | Hàng 7–8: kho/doanh số thuê sở hữu riêng | PARTIAL | Kiểm truy vấn KPI/kho, không chuyển lịch sử bán xe thành nợ |
| L02 | Hàng 7.1: nợ tháng/khách lẻ quá hạn | PARTIAL | Ngày cuối tháng, kỳ 0, overdue và số dư thật |
| L03 | Hàng 7.2: tự nhắc khách đỏ | PARTIAL/BLOCKED gửi thật | Nhắc nội bộ riêng; provider/template/lịch được duyệt cho gửi thật |
| L04 | Hàng 7.3, 8.2: GPS/mất tín hiệu/ngừng lâu | BLOCKED provider | Mapping thiết bị, threshold, event trễ/trùng, bằng chứng thật |
| L05 | Hàng 7.4/6, 8.3: note/thu hồi | PARTIAL | Lịch sử bất biến, quyền, audit, giao việc/người phụ trách |
| L06 | Hàng 7.5, 8.1: phân loại nợ | PARTIAL | Bucket/nhãn khớp nghĩa vụ sau thu/đảo/điều chỉnh |
| L07 | Hàng 8.2: tag đầu việc/phương án thu hồi | UNVERIFIED | Workflow trạng thái, người phụ trách, hạn và lịch sử |
| L08 | Hàng 7.7, 8.4: Excel | PARTIAL | Lọc/scope/phân trang/số tổng và dữ liệu đã đảo |
| L09 | Hàng 7.7, 8.4: PDF | UNVERIFIED | Endpoint/template riêng, cùng dataset với Excel |
| L10 | Plan: tất toán, đảo thu, chuyển quyền | FAIL/BLOCKED chính sách | R02/R05/R09; duyệt chiết khấu/chuyển quyền trước triển khai phụ thuộc |
| B01 | Hàng 9: quyền BGĐ/kế toán | UNVERIFIED | Policy server, API/export/drill-down theo role |
| B02 | Hàng 9: KPI sale ngày/tháng, lead | UNVERIFIED | Định nghĩa KPI, nguồn, query và đối soát mẫu |
| B03 | Hàng 9: tiến độ tìm khách tháng | UNVERIFIED | Mục tiêu/kết quả, cách tính trạng thái và nguồn lead |
| B04 | Hàng 9: chiến dịch/phòng ban | UNVERIFIED | Attribution và báo cáo drill-down/export có quyền |
| A01 | Hàng 10: thu/phải thu, tiền tương đương tiền | PARTIAL | Sổ chuẩn và đối soát số dư |
| A02 | Hàng 10: hạch toán từng mục | UNVERIFIED | Hệ tài khoản, bút toán đối ứng, kỳ khóa |
| A03 | Hàng 10: xe bán xuất VAT | BLOCKED chính sách/tích hợp | Chốt dữ liệu/quy trình/provider; không coi PDF thường là hóa đơn VAT |
| A04 | Hàng 10: hợp đồng | PARTIAL | Liên kết hồ sơ với khoản thu/nghĩa vụ và sổ |
| A05 | Hàng 10: tài sản nội bộ/phòng giao dịch | UNVERIFIED | Danh mục, bàn giao, vị trí, nguyên giá/khấu hao theo chính sách |
| A06 | Hàng 10: thu chi ngày/tháng/cơ sở nối sổ | FAIL/PARTIAL | Đối soát bank/cash/ledger/két; R05–R08 |
| H01 | Hàng 11: danh sách nhân sự | PARTIAL | CRUD, unique mã không dùng count+1, scope/allowlist |
| H02 | Hàng 11: sơ đồ nhân sự | UNVERIFIED | Phòng ban/cấp quản lý, chống chu kỳ, quyền xem |
| H03 | Hàng 11: liên hệ | PARTIAL | Danh bạ dùng được, tách CCCD/dữ liệu nhạy cảm khỏi lookup |
| H04 | Hàng 11: chấm công ca | UNVERIFIED | Ca, giờ vào/ra, qua đêm, điều chỉnh được duyệt |
| H05 | Hàng 11: lịch trực ngày | PARTIAL | Thêm/sửa/xóa, ca trùng, staff/store đúng, audit và quyền |
| X01 | Plan: CRUD Supabase thật | UNVERIFIED R10 | UI/API → bảng nghiệp vụ → refresh/re-login, audit và cleanup |
| X02 | Plan: concurrency/rollback | UNVERIFIED | Hai kết nối PostgreSQL độc lập; fault injection |
| X03 | Plan: deploy/UAT | UNVERIFIED | SHA runtime, migrations, nhân viên ký nhận, smoke live |

Hàng 7 và 8 là hai nhóm thuê sở hữu riêng trong Excel, có nội dung trùng; giữ traceability đến cả hai thay vì bỏ nhóm để thay bằng “kiểm thử”.

## 4. Chặng 0 — xử lý credential, baseline và môi trường

1. Ghi HEAD/status/remote và danh sách thay đổi đang có; không git add toàn bộ, không ghi đè việc người khác.
2. Chủ quản credential rotate mật khẩu đã lộ; cập nhật nơi sử dụng qua secret store (runtime, worker, môi trường phát triển được cấp phép), kiểm kết nối bằng health/query chỉ đọc đã che thông tin. Xác nhận credential cũ bị vô hiệu; không đưa giá trị vào chat/log.
3. Đổi script sang biến môi trường không có fallback secret, fail closed nếu thiếu cấu hình. Bỏ TRUNCATE; chỉ cleanup theo run ID và tập khóa đã tạo, cleanup trong finally. Không chạy bản script cũ.
4. Quét working tree, commit sẽ push và bundle, chỉ báo đường dẫn/loại phát hiện. Vì 38b330a chưa trên remote main tại review, chuẩn bị nhánh publish sạch từ baseline + patch đã loại secret. Không chỉ thêm commit xóa secret rồi push cả lịch sử nhiễm; không tự force-push/rewrite main đang dùng chung. Nếu secret đã lên ref khác thì chủ repo xử lý lịch sử liên quan sau rotation.
5. Đính chính báo cáo cũ: canary chỉ chứng minh một bài thử PDO trên bảng phụ theo báo cáo AI, chưa chứng nhận app. Bỏ mọi tỷ lệ nghiệm thu suy đoán.
6. Dựng PostgreSQL/Supabase staging riêng, cấu hình API staging; disable kênh nhắc thật. Kiểm migration chain từ schema sạch và schema baseline, bao gồm 000003 payment requests, 000004 két, 000005 HCNS.
7. Lưu manifest môi trường (không secret), schema/migration version, commit, timestamp, run ID và tài khoản test theo role. Không chạy migrate vào live trong chặng phát triển.

**Gate:** credential đã được xử lý, nhánh phát hành sạch, staging cô lập và migrate/restore thử thành công. Thiếu quyền rotate/cấu hình thì ghi BLOCKED mục này, vẫn sửa/test local các phần độc lập.

## 5. Chặng 1 — sửa P0/P1 về tiền và phân quyền

### 5.1 Tất toán và đảo thu

- Sửa trong LeaseContractService/Controller, modal, models/migrations liên quan. Tách trạng thái hoàn tất nghĩa vụ khỏi chuyển quyền sở hữu; không tự coi completed là xe đã sang tên.
- Tất toán nhận amount/payment_date/payment_method/bank_id hoặc cash_id/notes/idempotency_key; allowlist và kiểm quyền. Chuẩn hóa key notes từ UI đến transaction.
- Khóa contract trước, rồi installments/allocations theo thứ tự ổn định; đọc lại dư nợ trong transaction. Chỉ active/defaulted theo chính sách được duyệt, không xử lý cancelled/completed như hợp đồng đang thu.
- Dư nợ = nghĩa vụ được duyệt − khoản thu hiệu lực − điều chỉnh được duyệt. Tất toán phải khớp dư nợ; 0 đồng không hoàn tất hợp đồng còn nợ. Nếu cho chiết khấu: lưu adjustment riêng có quyền duyệt/lý do, không biến discount thành tiền thực thu.
- Không set toàn bộ amount_paid bằng amount_due. Giá trị kỳ phải được suy từ phân bổ/điều chỉnh hiệu lực; trạng thái, dư nợ, aging, báo cáo dùng cùng nguồn.
- Đảo thu giữ allocation gốc và sự kiện đảo riêng liên kết allocation/transaction gốc; phiếu đối ứng giữ đúng bank_id/cash_id/store/currency/channel. Quy định rõ đảo cả phiếu hay một phần phân bổ; kiểm tổng đảo không vượt khoản hiệu lực.
- Idempotency có unique constraint + fingerprint chuẩn hóa: cùng key/cùng dữ liệu trả cùng kết quả, khác dữ liệu trả conflict; retry sau đảo không trả kết quả cũ gây hiểu là đang còn thu.
- Audit append-only chứa actor, action, entity, nguồn/đích, số tiền, lý do, request ID và thời điểm; không lưu secret/PII dư thừa.

**Test bắt buộc:** 0 đồng/nợ dương; thiếu tiền; chiết khấu chưa duyệt; CK đúng/sai ngân hàng; note; partial/multi-period; completed/cancelled; thu và đảo đồng thời; retry timeout; rollback giữa tạo phiếu và phân bổ. Kỳ, khoản thu và sổ phải đối chiếu bằng tiền, không chỉ assert status.

### 5.2 Quyền server

- Tạo ma trận action × role × store cho admin, kế toán, BGĐ, HR, nhân viên cơ sở, sale, user chưa gán cơ sở. PilotAccess::isAdmin hiện không bao kế toán; không mở kế toán bằng cách biến mọi role thành admin.
- Gắn authentication/policy đúng trên route; scope trước filters cho summary/history/list/show/export/mutations. Query parameter không được mở rộng scope. User chưa gán cơ sở phải bị từ chối trừ role có quyền toàn hệ thống.
- HR dùng FormRequest/validated allowlist, Resource giới hạn field; không trả nguyên hồ sơ có CCCD/user relation cho danh bạ. Validate exists staff/store/department và quan hệ được phép.
- Thay gọi method User không tồn tại bằng cơ chế quyền thực của repo; kiểm 401/403/422 qua HTTP, không chỉ gọi service.
- Ca trực cập nhật/xóa kiểm ownership và quyền, bỏ actor fallback ID=1; audit cũ/mới/lý do.

**Gate:** R02–R05/R09 và lỗi quyền đã có regression theo hành vi đúng; không còn đường xóa nghĩa vụ bằng 0 đồng, không đọc/ghi khác scope. Chạy đầy đủ suite + integration PostgreSQL.

## 6. Chặng 2 — hợp đồng, thu tiền, két và đối soát

1. Ghi quyết định mã hợp đồng (format mới, ngày cấp/ngày ký, counter chung/cơ sở); default giữ mã đã cấp, không backfill số cũ. Kiểm cùng ngày có cả hai format và legacy snapshot.
2. Mở `himoto-contract-fields.md` + PDF render; tạo field matrix gồm input/API/persisted path/snapshot/template/validation/test. Kiểm CCCD/GPLX số 0 đầu, ngày VN, thông tin bên A/B, xe/phụ kiện, tài sản cọc, người ký, trả xe.
3. Preview không ghi DB/consume counter; save/issue/lock tách đúng lifecycle, snapshot bất biến; invoice tiền thuê thực nhận khác nghĩa vụ/cọc/hoàn dự kiến.
4. Thu TM/CK cá nhân/CK công ty có bank/cash đúng cơ sở; unknown lịch sử giữ unknown, không suy cá nhân. Luồng kết hợp chia dòng tiền xác định được tổng.
5. Chuẩn hóa loại nghiệp vụ giao dịch bằng field enum/reference từ nguồn tạo (thu cọc, thuê, gia hạn, hoàn, phạt, khác, điều chỉnh). Dữ liệu cũ chưa phân loại đưa danh sách rà soát, không đoán bằng text rồi coi chính xác.
6. CashRegisterService lấy khoảng ngày Asia/Ho_Chi_Minh [đầu ngày, đầu ngày kế tiếp), quy đổi theo storage timezone. UI không dùng ISO UTC để chọn ngày VN lúc 00–07h. Chốt định nghĩa “số đơn”: tạo/thuê/thu tiền, tránh một số đếm không rõ nghĩa.
7. Mỗi cơ sở/quỹ có opening + inflow − outflow = closing; bank theo từng tài khoản, đủ mọi khoản chi. Tổng hệ thống cộng từng cơ sở, không dùng first(). Trạng thái mixed open/closed phải hiển thị rõ.
8. Chốt có unique(store,date), khóa nhất quán với đường ghi tiền; request lặp không thay snapshot. Giao dịch vào ngày đã khóa phải bị chặn hoặc qua điều chỉnh theo chính sách. Mở lại đòi quyền/lý do/version/audit, xử lý số dư ngày sau.
9. Nhập/đối soát sao kê ngân hàng staging: matched/unmatched/duplicate/fee, người duyệt; nhập số đếm TM, lý do lệch. Không coi tổng CK theo hai nhóm chủ tài khoản là đã đối soát ngân hàng thực tế.
10. Xuất Excel/PDF két/công nợ từ cùng dataset API có scope; số dư và phân trang đủ. Dựng đơn trong ngày với bộ lọc server, vùng ảnh placeholder rõ nghĩa.

**Bộ dữ liệu nghiệm thu:** hai cơ sở, nhiều tài khoản/quỹ, TM/CK/kết hợp/unknown, thu cọc/thuê/gia hạn/phạt/hoàn/chi vận hành/đảo/adjustment; 23:59–00:01 giờ VN; chốt/mở lại/retry/song song; ngày chưa chốt xen ngày đã chốt. So sánh ledger, UI, API, export và query DB độc lập.

**Gate:** R06–R08 hết lỗi; C01–C05/O01–O02/K01–K03 có bằng chứng staging. UAT hợp đồng/két được ký nhận trước mở rộng pilot.

## 7. Chặng 3 — hoàn thiện UI trên toàn ứng dụng

- Liệt kê route thật, layout dùng chung, modal và các trạng thái loading/empty/error/toast/confirm; tìm `<i>`, `<svg>` icon, inline-svg, v-icon, font icon, emoji, CSS ::before/::after và icon sinh bởi component library.
- Thay nút icon-only bằng chữ có accessible name: Mở menu, Đóng, Phóng to, Thu nhỏ, Sửa giá, Xóa xe, Xem lịch sử… Không xóa hành vi khi bỏ markup; giữ logo thương hiệu và nội dung biểu đồ cần thiết.
- Chuẩn hóa tokens màu dịu/Inter/weight/spacing/focus; kiểm computed style, không chỉ import font. Button/bảng/modal đủ chỗ trên mobile, có nhãn số tiền/ngày rõ.
- Kiểm browser từng vai trò tại 360/390/768/1024/1440, zoom 125/150%, keyboard, A4. Quét source hỗ trợ kiểm kê nhưng không thay screenshot/DOM runtime.
- Build một lần bằng build:static sau sửa, kiểm manifest/version ở source bundle/public/static-dist đồng nhất commit. Không production rồi build:static lặp nếu không cần.

**Gate:** UI01–UI03 và C01 đạt; danh sách trước/sau + ảnh từng route/variant; không tuyên bố 100% chỉ vì sửa 10 file.

## 8. Chặng 4 — điều chuyển đầu cuối

1. Chuẩn hóa kho quản lý, vị trí thực tế và cơ sở doanh thu thành nguồn riêng; kho thuê sở hữu không cộng doanh số/tồn vào 5 kho vật lý.
2. Kho–kho: dispatch → transit → receive/cancel; unique idempotency; khóa xe theo ID tăng dần, trạng thái được phép, lịch sử không đếm kép.
3. Trả khác cơ sở: tìm biển số trong scope nghiệp vụ được cấp, xác định order item; nhiều xe trả từng phần. Tích hợp xác nhận trả/đối soát/vị trí/ledger trong một workflow; giữ kho doanh thu gốc và snapshot hợp đồng.
4. Đổi cùng/khác cơ sở: xác minh xe cũ thuộc đơn active, xe mới ready; ghi hai chiều bàn giao và phụ lục. Phí tăng/giảm từ chính sách giá được duyệt tạo nghĩa vụ/thu/hoàn đúng nguồn, không chỉ ghi price_difference trong JSON.
5. Xe lỗi trả về đúng trạng thái chờ kiểm tra/bảo trì; không tự ready khi chưa đủ điều kiện.
6. Test hai người chuyển/đổi cùng xe, nhận và hủy đua nhau, retry, lỗi giữa các bước, scope export/history. PostgreSQL và UI staging phải cùng kết quả.

**Gate:** W01–W05 và W01–W03 trong handoff đạt, không còn yêu cầu thao tác bù thủ công để gọi là flow đầy đủ.

## 9. Chặng 5 — thuê sở hữu, nợ, nhắc và GPS

- Dựa trên sổ đã sửa, kiểm lại lịch kỳ 0/tháng cuối/ngày 29–31, thu một phần/nhiều kỳ, nợ hiện tại/quá hạn; tổng nợ và bucket không lệch sau tất toán/đảo.
- Thêm lifecycle tất toán → chờ giấy tờ/chuyển quyền → đã chuyển quyền nếu chính sách được duyệt; lưu phụ lục/bàn giao/audit. Không tự điều chỉnh luật hoặc mức phí.
- Workflow thu hồi có tag/người phụ trách/hạn/trạng thái/ghi chú lịch sử. Phân quyền và export che dữ liệu ngoài nhu cầu.
- Excel/PDF nợ cùng bộ lọc/scope và dataset; kiểm nhiều trang, tổng, tiếng Việt, dữ liệu dài.
- Nhắc nội bộ và gửi ngoài là hai gate: outbox/retry/dedupe/check nợ ngay trước gửi; dry-run không sent; provider timeout không báo thành công. Gửi thử chỉ đến đích thử được cấu hình/ủy quyền, không khách thật.
- GPS: cần provider/API/device mapping thật; giữ never_connected/unknown khác stale/offline/stopped. Xác thực webhook, chống trùng/đảo thứ tự, cấu hình threshold, thời gian VN, không vẽ vị trí giả.

**Gate:** L01–L10 có bằng chứng riêng. Thiếu provider/quyết định ghi BLOCKED với người cung cấp và dữ liệu cần cấp; tiếp tục phần độc lập. Không gọi toàn dự án hoàn thành khi còn BLOCKED.

## 10. Chặng 6 — báo cáo/KPI, kế toán và HCNS đầy đủ

### Báo cáo/KPI (B01–B04)

1. Lập từ điển metric: tên/đơn vị/công thức/nguồn/timezone/bộ lọc/chủ trách nhiệm; chốt doanh thu theo nghĩa vụ hay thực thu, không lấy cọc làm doanh thu mặc định.
2. Mapping sale/lead/chiến dịch/phòng ban theo dữ liệu thực; lưu mục tiêu tháng và quy tắc attribution đã duyệt.
3. Query/snapshot/report và drill-down cùng công thức, có policy BGĐ/kế toán; tách kho thuê sở hữu.
4. Export và bộ mẫu đối chiếu thủ công, kiểm giao dịch đảo, đổi cơ sở, mốc tháng. Có bằng chứng API/DB, không chỉ dashboard hiển thị số.

### Kế toán (A01–A06)

1. Kế toán duyệt hệ tài khoản, journal lines, tài khoản đối ứng theo từng nghiệp vụ; tổ chức schema/migrations/constraints và dịch vụ ghi sổ.
2. Mỗi bút toán cân tổng nợ/có; idempotency từ source transaction; đảo bằng bút toán đối ứng có liên kết, không sửa sổ đã khóa.
3. Sổ tiền, ngân hàng, phải thu, tài sản theo ngày/tháng/cơ sở; reconciliation với két và hợp đồng. Số dư đầu kỳ có mapping và xác nhận, không tự backfill.
4. Danh mục tài sản, cơ sở/phòng ban sử dụng, lịch sử bàn giao, nguyên giá và khấu hao theo chính sách đã duyệt.
5. VAT: chuẩn bị dữ liệu/luồng duyệt/xuất, kết nối nhà cung cấp theo quyền được cấp; thiếu chính sách/provider ghi BLOCKED, không phát hành giả.
6. Nghiệm thu bởi kế toán bằng dataset đối chiếu, khóa kỳ, đảo và xuất báo cáo.

### HCNS (H01–H05)

1. Hoàn thiện danh bạ CRUD với validation/unique code/soft deactivate; không dùng count+1 làm mã đồng thời.
2. Phòng ban/cấp quản lý/sơ đồ tổ chức, chống tự làm quản lý/vòng lặp; tách quyền danh bạ và hồ sơ nhạy cảm.
3. Ca/lịch trực có staff_id đúng người, store đúng quyền, kiểm trùng thời gian; cập nhật thật thay vì tạo ca mới mỗi lần sửa.
4. Chấm công vào/ra, ca qua đêm, phép/vắng/điều chỉnh có người duyệt theo scope đã chốt. Không suy đã chấm công từ lịch phân ca.
5. Audit thay đổi, export theo quyền, test HTTP role/store và UI refresh/re-login.

**Gate:** từng mục hàng 9–11 Excel có test và bằng chứng. Không dùng màn “Lịch trực” thay chứng nhận toàn HCNS, hoặc két thay toàn kế toán.

## 11. Quy trình kiểm chứng Supabase thật cho từng chặng

### 11.1 Phạm vi bảng/module

Kiểm stores/vehicles/orders/order_vehicle_details/transactions/banks/cash; lease_contracts/installments/allocations/payment_requests/debt_notes; vehicle_transfers/items/location_events; daily_cash_registers; departments/staff_profiles/store_duty_schedules; reminder outbox; và bảng báo cáo/journal/tài sản/attendance/GPS/adjustments/reversals khi được bổ sung. Tra migration để dùng đúng tên bảng thực tế, không tạo bảng phụ giả thay bảng nghiệp vụ.

### 11.2 Cách chạy một ca

1. Xác định allowlist staging project/schema, API base URL và runtime SHA; fail nếu trỏ live không có phạm vi canary được duyệt. Dùng account thử và dữ liệu giả riêng, không sao chép PII khách.
2. Tạo run ID duy nhất. Nếu bảng chưa có test_run_id, dùng registry bên ngoài ánh xạ bảng/PK/run và quan hệ; không mass assign field không tồn tại.
3. Ghi count/tổng/checksum phạm vi fixture trước chạy. Qua UI hoặc HTTP API thật đăng nhập đúng role để CREATE; không INSERT trực tiếp business row làm bằng chứng luồng tạo.
4. Đọc API và dùng kết nối DB độc lập SELECT theo PK đã tạo; đối chiếu field, số tiền, ngày, FK, actor. Commit phải nhìn thấy từ kết nối thứ hai.
5. UPDATE qua UI/API rồi SELECT/audit. Refresh, logout/login, browser context mới và request mới vẫn thấy giá trị đã lưu; không route mock, không fixture intercept.
6. DELETE/deactivate dữ liệu được phép; nghiệp vụ tài chính thử reverse/adjustment thay hard-delete. Đọc lại trạng thái và history/audit qua API + DB.
7. Hai request thật trên hai connection PostgreSQL: cùng key/cùng payload, cùng key/khác payload, thu cạnh tranh, thu–đảo–tất toán, nhận–hủy xe, chốt két–ghi tiền. Assert số row, tổng tiền và state cuối.
8. Fault injection chỉ staging tại điểm giữa transaction: assert rollback hết order/vehicle/ledger/allocation/audit nghiệp vụ chưa commit; retry vẫn đúng một lần.
9. Cleanup theo registry/run ID và FK order, trong finally; không TRUNCATE hoặc DELETE không predicate. Financial audit bất biến giữ theo chính sách test hoặc reset riêng DB staging, không mở đường xóa lịch sử live. So count/tổng/checksum của dữ liệu ngoài run trước/sau, kiểm orphan và số tồn test.

### 11.3 Mẫu bảng bằng chứng bắt buộc

| Requirement ID | SHA runtime / migration | Run ID / role / store | UI/API action + HTTP | Bảng/PK + SELECT/assertion | Audit | Refresh/re-login | Retry/rollback/concurrency | Cleanup | Kết luận |
|---|---|---|---|---|---|---|---|---|---|
| Điền mỗi ca | Không dùng SHA local thay runtime | Che thông tin nhận dạng | Không lưu token/request nhạy cảm | Expected và actual, checksum canonical | Actor/action/reason | Browser/session mới | Số request và kết quả | Theo run | PASS/FAIL/BLOCKED |

Lưu query template và output đã che, screenshot/PDF, test log exit code. Hash JSON cần chuẩn hóa đệ quy key/date/decimal. Chỉ checksum một JSON canary không chứng minh khóa ngoại, tính tiền hoặc quyền đúng.

## 12. Chặng cuối — release, UAT và tiêu chí hoàn thành

1. Lặp sửa → regression → PostgreSQL → UI theo từng chặng cho đến khi không còn lỗi P0/P1; cập nhật ma trận bằng chứng, không tích dấu từ báo cáo cũ.
2. Chạy full suite, build:static, diff check, secret scan và browser flow quan trọng tại SHA chuẩn bị release. Test service không thay integration migration/HTTP.
3. Có UAT nhân viên: tạo/lưu/in hợp đồng, thu đúng nguồn, đối soát/chốt, trả/đổi xe, nợ/đảo/tất toán, báo cáo, HR đúng role. In A4 giấy và dữ liệu nhiều xe.
4. Trước live: backup có kiểm restore; danh sách migration thực sự còn thiếu; dry-run/compatibility, rollback app và phương án giao dịch đang xử lý. Xác nhận cấu hình `RUN_MIGRATIONS`; không suy tự migrate từ push.
5. Commit theo chặng, chỉ stage file thuộc việc mình; không push lịch sử chứa secret. Push nhánh được phép sau gate; ghi SHA/remote/ref rõ ràng.
6. Xác nhận API runtime SHA, frontend bundle SHA, migration version trên Render/Supabase, health và worker/scheduler; không dùng version.json tự ghi để thay bằng chứng runtime API.
7. Smoke live bằng dữ liệu thử riêng trong phạm vi được phép; provider thật chỉ đến đích thử được duyệt. Nếu lỗi, disable feature/rollback app tương thích schema, không drop bảng có giao dịch.
8. Bàn giao hướng dẫn thao tác, phân quyền, đối soát, sửa sai bằng đảo/điều chỉnh, vận hành job, backup/rollback, chủ trách nhiệm và lỗi còn mở.

**Hoàn thành toàn bộ** khi từng ID ma trận có PASS + artifact, mọi quyết định nghiệp vụ đã chốt, provider cần thiết được kiểm thật, migration/deploy/UAT được xác nhận. Nếu còn BLOCKED, chỉ báo hoàn thành phạm vi cụ thể đã nghiệm thu và liệt kê chính xác phần chưa xong.

## 13. Lệnh kiểm tra và bằng chứng review này

Chạy tại `E:\duanthuexe\happyride-1.1`:

```powershell
git status --short --untracked-files=all
git ls-remote origin refs/heads/main
.\php.cmd ..\tools\phpunit.phar -c phpunit.xml
.\php.cmd ..\tools\phpunit.phar -c phpunit.xml --filter testReview docs/review-38b330a/ReproduceReviewTest.php
git diff --check
```

Kết quả phiên review: suite gốc `OK (84 tests, 416 assertions)`; reproduction `OK (5 tests, 23 assertions)`; cả hai exit 0. Reproduction nằm ngoài tests/ và assert hành vi lỗi của baseline; khi sửa phải viết regression kỳ vọng đúng trong suite chính, không giữ kỳ vọng lỗi làm tiêu chí phát hành. File vendor/phpunit không có trong workspace này, vì vậy dùng phar hiện hữu, không khẳng định `artisan test` đã chạy trong phiên review.

## 14. Prompt giao AI thực hiện tiếp

> Đọc tài liệu review này, completion-plan-from-excel, handoff gốc, contract-fields, Excel và PDF tại E:\duanthuexe. Kiểm lại Git trước sửa. Không coi 38b330a đã hoàn thành: có credential hardcode, tất toán 0 đồng xóa nghĩa vụ trên lịch kỳ, mất liên kết đảo thu, lỗi két và scope HR/két. Làm chặng 0–6 rồi release theo thứ tự trong review; ưu tiên quyền và tính đúng tiền trước. Giữ thay đổi người khác, không gửi/ghi log secret, không push lịch sử nhiễm secret, không chạy hai script Supabase cũ. Dùng PostgreSQL/Supabase staging riêng, thao tác UI/API vào bảng nghiệp vụ và SELECT độc lập, refresh/re-login, audit, concurrency, rollback và cleanup theo run ID. Không dùng bảng canary/mocks để chứng nhận CRUD app. Bỏ icon toàn giao diện, Inter đúng weight, test mobile và A4. Hoàn thiện cả KPI/chiến dịch, kế toán/VAT/tài sản, sơ đồ/chấm công, không chỉ két/lịch trực. Không tự đặt chính sách chiết khấu/chuyển quyền/mã HĐ; ghi BLOCKED phần phụ thuộc và tiếp tục phần độc lập. Mỗi ID yêu cầu cần trạng thái, file/commit, ca test, DB query, screenshot/PDF và runtime SHA; không dừng chỉ vì unit test/build pass. Chỉ commit/push bản đã kiểm và báo riêng trạng thái Render, migrations, live UAT; push không phải deploy. Chỉ gọi toàn bộ hoàn thành khi mọi gate có bằng chứng, không còn BLOCKED/P0/P1.
