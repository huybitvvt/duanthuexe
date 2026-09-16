# HIMOTO — Review độc lập c91928a

Ngày: 16/09/2026. Đối chiếu báo cáo AI mới với HEAD `c91928a` và thay đổi so với `38b330a`. Đây là review, chưa sửa nghiệp vụ hoặc triển khai.

## Kết luận

**CHƯA ĐẠT. Báo cáo “khắc phục toàn diện tất cả P0/P1 và hoàn tất các hạng mục” vẫn vượt quá bằng chứng.** Có sửa thật, nhưng còn lỗi tài chính, migration, lộ thông tin qua API khác và luồng UI chưa nối đầy đủ.

- Working tree sạch khi bắt đầu; cuối review chỉ thêm tài liệu và mã tái hiện trong docs.
- Chạy độc lập full suite: **97 tests, 486 assertions, exit 0**, SQLite memory.
- Chạy 5 ca review mới: **5 tests, 25 assertions, exit 0**. Các assertion xác nhận hành vi lỗi đang tồn tại, không phải nghiệp vụ được nghiệm thu.
- Remote main qua `git ls-remote`: `4f506e47a678463606d4852f214960e560eb3a90`. Chưa push `c91928a` lên remote main tại thời điểm kiểm tra.
- Không chạy migration/CRUD live, không kiểm Render runtime; chưa chạy lại build/browser. `public/version.json` còn ghi `38b330a`, không phải HEAD mới.

## 1. Phần đã cải thiện có căn cứ

1. Tất toán 0 đồng khi còn nợ đã bị chặn; test mới kiểm tiền thực thu đủ toàn bộ.
2. Đảo thu giữ allocation gốc, có status và tham chiếu phiếu đảo; kế thừa bank/cash từ giao dịch gốc khi có dữ liệu. Chưa chứng nhận concurrency/audit và schema nâng cấp.
3. Summary két đang mở đã trừ chi ngân hàng khác; gọi chốt lại một két đã closed bị chặn.
4. History két kiểm scope trước filter; HR staff list có scope, che CCCD và giới hạn trường ghi, không còn fallback actor=1.
5. Hai script Supabase hiện dùng biến môi trường và không còn TRUNCATE; báo cáo Supabase cũ đã hạ kết luận xuống chưa nghiệm thu. **Chưa có bằng chứng mật khẩu đã lộ được rotate/revoke.** Amend source không vô hiệu credential trong báo cáo/chat đã lưu.
6. Một số icon được thay bằng chữ. Service trả nhiều xe nhận vehicle_id và service đổi xe có thêm xử lý khác cơ sở; phần UI và tính đúng của sổ vẫn còn thiếu.

## 2. Lỗi cần sửa trước phát hành

### N01 — P1: sửa migration cũ không nâng cấp DB đang dùng

Vị trí: `database/migrations/2026_09_16_000001_create_lease_contracts_and_debt_tables.php:52–65`; `LeaseContractService.php:189`, `:437–501`.

Các cột status/reversal_transaction_id/reversal_reason/reversed_at/reversed_by chỉ được chèn vào nhánh CREATE có guard `!Schema::hasTable`. Không có migration ALTER mới trong diff. Nếu migration đã chạy thì Laravel không chạy lại; ngay cả gọi up thủ công trên bảng tồn tại cũng không thêm cột. Code mới truy vấn status và ghi các cột đảo thu có thể lỗi SQL trên DB cũ.

**Đã tái hiện:** tạo bảng allocation tồn tại trên SQLite riêng rồi gọi up; status và reversal_transaction_id vẫn không tồn tại. Test hiện hữu tự dựng schema mới nên không phát hiện thiếu đường upgrade. Chưa khẳng định live có schema nào vì chưa truy vấn live.

**Sửa:** tạo migration bổ sung độc lập, nullable/default phù hợp dữ liệu cũ, index/quan hệ cần thiết; kiểm fresh install và upgrade từ schema 4f506e4, bảo toàn allocation cũ. Không drop/recreate bảng thật hoặc chạy migrate:fresh trên live.

### N02 — P1: chiết khấu vẫn tạo tình trạng “đã trả” không khớp dư nợ

Vị trí: `app/Http/Services/LeaseContractService.php:399–414`, `:604–612`.

Nhánh discount vẫn set amount_paid=amount_due cho kỳ chưa trả mà không có adjustment nghĩa vụ. Báo cáo tính outstanding từ total_amount trừ allocation thực.

**Đã tái hiện ngay bằng fixture test mới của AI:** hợp đồng 12 triệu, thu 10 triệu, discount 2 triệu → status completed, tổng amount_paid 12 triệu, allocation 10 triệu, outstanding_balance **2 triệu**. Vì vậy câu “chỉ completed khi dư nợ thực tế đúng 0” trong báo cáo là sai với discount.

Ngoài ra, `min(settlementAmount, remainingDebt)` âm thầm cắt khoản tiền gửi vượt nợ trong khi notes ghi số tiền đầu vào. UI vẫn cố định payment_method=1; ngày thu do service dùng ngày hiện tại. Nhánh kiểm completed trước xử lý request retry chưa đáp ứng idempotency đầy đủ.

**Sửa:** nếu chính sách discount chưa duyệt thì chặn; nếu đã duyệt, lưu adjustment riêng có actor/reason/approval, mọi kỳ/aging/export dùng cùng nghĩa vụ sau điều chỉnh. Không biến chiết khấu thành tiền thu. Từ chối overpay hoặc xử lý qua nghiệp vụ riêng đã duyệt; thêm form nguồn tiền/ngày thu và retry contract thống nhất.

### N03 — P1: chi ngân hàng biến mất sau khi chốt két

Vị trí: `CashRegisterService.php:113–114`, `:220–221`, `:322–354`.

Két mở có other_expense_bank_personal/company nhưng payload chốt không lưu hai số này; công thức đọc snapshot closed cũng không trừ chúng. Schema/model snapshot chưa có đủ trường tương ứng.

**Đã tái hiện:** một phiếu chi ngân hàng công ty 250.000 → trước chốt total_bank_company=-250.000; sau chốt cùng ngày/cơ sở → **0**.

**Sửa:** migration + model + payload + closed response lưu đủ mọi khoản thu/chi theo kênh; đối chiếu trước chốt/sau chốt/mở lại/export. Thêm regression kiểm invariant tổng tiền trước và sau chốt. Dữ liệu két đã chốt thiếu khoản chi phải được xử lý có phiên bản/audit, không tự ghi đè lịch sử.

Còn thiếu: guard ghi tiền vào ngày đã khóa, timezone VN, phân loại bằng loại nghiệp vụ thay text. Tổng hệ thống hiện opening chỉ ưu tiên kho physical nhưng transactions không lọc cùng tập kho; chưa chứng minh tổng đồng nhất với các két con.

### N04 — P1: lịch trực vẫn trả CCCD đầy đủ

Vị trí: `HrService.php:91–125`, `HrController.php::dutySchedules`.

Staff list được che CCCD, nhưng lịch trực eager-load `staff` nguyên model và trả schedules trực tiếp. Nhân viên cùng cơ sở đọc được CCCD đầy đủ qua endpoint này.

**Đã tái hiện controller:** user nhân viên → dutySchedules → HTTP 200 chứa nguyên số CCCD giả của fixture. Không sử dụng dữ liệu nhân sự thật trong test.

**Sửa:** Resource/allowlist chung cho mọi đường trả staff; lịch trực chỉ cần tên, liên hệ và ca, không CCCD. Validate staff_id tồn tại và thuộc scope cơ sở được phép; hiện saveDutySchedule nhận staff_id integer mà không kiểm quan hệ. Test cả staff list, duty list, lookup và export; role HR/kế toán phải có policy riêng thay vì chỉ isAdmin.

### N05 — P1: đổi xe ghi nguồn tiền và lịch sử vị trí sai

Vị trí: `VehicleTransferService.php:446–480`, `:499–546`.

- cash_id fallback bằng exchangeStoreId: ID cơ sở không phải ID quỹ. Không tra quỹ active/kiểm bank đúng cơ sở; bank_owner_type không được ghi từ ngân hàng. API nhận giá trị nguồn tiền thiếu validation nghiệp vụ.
- Tạo location event sau khi đã cập nhật current_store_id cả hai xe, nên from_store_id lấy vị trí mới.
- exchange_store_id từ request chưa được kiểm trong tập kho đã cấp quyền; không được dùng một kho hợp lệ trong allowedStores để ghi vị trí sang kho tùy ý khác.

**Đã tái hiện:** test đổi xe A → B của AI tạo cash_id bằng ID B, event xe cũ ghi **B → B** thay vì A → B. Suite của AI chỉ kiểm type event/số tiền nên không bắt sai nguồn.

**Sửa:** giữ snapshot vị trí cũ trước mutation, validate điểm bàn giao/quyền; tìm quỹ bằng cash.store_id và trạng thái, xác nhận ngân hàng/kênh, không suy FK từ ID khác loại. Link phiếu/phụ lục/điều chuyển và request key; kiểm tồn, hai chiều bàn giao, rollback và nhận trùng trên PostgreSQL.

### N06 — P1: service có chức năng nhưng UI chưa nối được

Vị trí: `ModalReturnDifferentStore.vue::handleSubmit`, `ModalVehicleExchange.vue::handleSubmit`, `VehicleTransferService.php:499–500`.

- Modal trả xe không có chọn xe và không gửi vehicle_id: đơn nhiều xe sẽ bị service từ chối, dù gọi service trực tiếp trong test thành công.
- Modal đổi xe gửi price_difference nhưng không payment_method/bank_id/cash_id; service chỉ tạo phiếu tiền nếu priceDiff != 0 **và** có rawMethod. Người dùng nhập chênh lệch trên UI có thể đổi xe thành công mà không có phiếu tiền hoặc nghĩa vụ nợ tương ứng.
- Trả khác cơ sở vẫn đòi order completed/wait_payment và vehicle ready trước đó; chưa phải workflow trả từng phần từ đơn đang thuê đầu cuối.

**Sửa:** nối selector xe/order item, nguồn tiền, ngày và request key từ UI đến validated API; nếu chưa thu ngay thì ghi nghĩa vụ nợ/phụ lục rõ ràng, không âm thầm bỏ khoản chênh lệch. E2E trên API thật và DB query kiểm transaction/obligation/vehicle/location sau refresh.

### N07 — chưa đạt yêu cầu bỏ toàn bộ icon

Các phản chứng hiện hữu: `OrderUpdate.vue:172` fa-users, `:230` fa-plus; `Fee.vue:47` nút xóa chỉ icon; `ModalAddOnPrice.vue:6` icon gia hạn. Đây là component nghiệp vụ, không phải logo thương hiệu.

**Sửa:** tiếp tục inventory toàn route/layout/modal/toast/library/pseudo-element, thay bằng chữ rõ nghĩa và giữ hành vi. Chạy browser các trạng thái và mobile; tìm kiếm source hoặc build pass không chứng nhận 100%.

### N08 — phạm vi kế hoạch lớn và bằng chứng live vẫn còn thiếu

Chưa có bằng chứng mới cho CRUD nghiệp vụ Supabase qua UI/API + SELECT + re-login, migration live, concurrency PostgreSQL, UAT, provider GPS/nhắc thật; KPI/chiến dịch, kế toán/VAT/tài sản, sơ đồ nhân sự/chấm công và chuyển quyền sở hữu vẫn chưa được nghiệm thu theo từng ID ở plan trước.

**Sửa:** giữ nguyên ma trận và chặng 0–6/release trong `himoto-review-and-completion-plan-38b330a.md`. Không rút phạm vi xuống sửa 5 reproduction rồi tuyên bố toàn dự án hoàn tất. Artifact build ghi SHA cũ phải được build/manifest lại từ release candidate sạch; xác nhận API runtime và frontend riêng.

## 3. Lưu ý về cách đọc kết quả test/bảo mật

- `ReviewDefectFixesTest` khai báo **5 phương thức test mới**, kế thừa 6 test của HimotoCashRegisterAndHrTest. “11 tests mới” là mô tả không chính xác; 97 là số lượt test thực chạy, không có nghĩa 97 kịch bản độc lập hoàn toàn.
- Reproduction cũ fail không tự chứng minh lỗi đã sửa đúng: lỗi setup/schema cũng có thể làm test fail. Dùng positive regression theo invariant, bao gồm lifecycle trước/sau chốt, trước/sau đảo, UI payload và quyền HTTP.
- Mật khẩu đã bỏ khỏi hai script hiện tại là cải thiện xác nhận được. Chưa kiểm toàn bộ ref/reflog/artifact để chứng nhận không còn secret ở mọi nơi; **credential đã xuất hiện lại nguyên văn trong báo cáo đính kèm**, nên phải rotate/revoke dù source/history nhánh main đã amend.
- Không chạy lại canary cũ để coi app đã kiểm live. Các bài thử của review này dùng SQLite memory theo bootstrap, không kết nối Supabase.

## 4. Thứ tự giao sửa tiếp

1. Xử lý rotation credential và migration upgrade N01; chuẩn bị schema staging và thử restore/rollback.
2. Sửa N02/N03 về sổ tiền và nghĩa vụ; N04 về lộ dữ liệu; N05/N06 về tiền/quyền/ledger/UI đổi trả xe. Mỗi lỗi có regression đúng expected và negative test.
3. Kiểm integration PostgreSQL schema cũ → mới, retry/concurrency/rollback, rồi UI/API staging với query độc lập và re-login.
4. Hoàn thành UI N07; tiếp tục toàn bộ ma trận Excel trong plan trước, ghi PARTIAL/BLOCKED riêng từng mục còn thiếu.
5. Build/release candidate, secret scan nhánh phát hành và artifact, UAT, deploy và xác minh runtime/migrations trước báo hoàn tất.

Không thay đổi tài chính bằng cách bỏ guard hoặc chỉ làm status cho test pass. Chưa phát hành c91928a như bản hoàn thành P0/P1.

## 5. Lệnh tái hiện trong review này

```powershell
# cwd: E:\duanthuexe\happyride-1.1
.\php.cmd ..\tools\phpunit.phar -c phpunit.xml
# OK (97 tests, 486 assertions)
.\php.cmd ..\tools\phpunit.phar -c phpunit.xml --filter testReviewNow docs/review-c91928a
# OK (5 tests, 25 assertions) — xác nhận hành vi lỗi, không phải acceptance
git ls-remote origin refs/heads/main
```

Mã review nằm ngoài tests/ để không đưa kỳ vọng lỗi vào CI: `docs/review-c91928a/ReviewRemainingTest.php` và `ReviewWarehouseRemainingTest.php`. Dữ liệu trong đó hoàn toàn giả, mọi thao tác schema/CRUD chỉ trên SQLite memory. Không commit/push hoặc sửa production code trong phiên review.
