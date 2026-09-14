# Review phần bổ sung hợp đồng do AI khác thực hiện

> Review mới nhất: [kết quả vòng 3](himoto-contract-review-round3.md). Lịch sử: [vòng 2](himoto-contract-review-round2.md). Nội dung bên dưới là bằng chứng vòng 1, không phải trạng thái mới nhất.

Ngày: 14/09/2026. Base commit `5f2f3fc`, nhánh `feature/himoto-complete-integration`. Đối tượng review là working tree chưa commit và 6 file PHP mới (service, model, 4 migrations).

**Kết luận: chưa đạt để phát hành.** Form đã có nhiều trường mới và frontend build được, nhưng có lỗi mất dữ liệu, đổi số HĐ và ghi đè snapshot. Nhận định “đã hoàn thành toàn bộ”, “chống trùng tuyệt đối” và “chỉ migrate là dùng được” trong báo cáo bàn giao chưa được chứng minh.

## Lỗi cần sửa

### 1. P1 — Hai yêu cầu kích hoạt có thể thay số của cùng hợp đồng

File: `app/Http/Services/OrderService.php:237–246`, `app/Http/Controllers/Order/OrderController.php:165–189`.

Điều kiện cấp số dựa vào `$order->contract_number` của model đã được route binding load trước transaction. Không khóa và đọc lại đơn trước kiểm tra. Hai request đã load cùng đơn cọc chưa có số đều có thể cấp số; request sau ghi đè số của request trước. Counter atomic và unique constraint chỉ ngăn hai đơn cùng số, không ngăn một đơn đổi số.

Đã tái hiện với hai model cùng đơn được load trước hai transaction: lần đầu commit `2026/09/14-0003`, lần sau cùng đơn thành `2026/09/14-0004`. Đây là mô phỏng hai request giữ model cũ, chưa phải thử tải đồng thời trên PostgreSQL.

Sửa: khóa row đơn rồi đọc lại trạng thái/số trong transaction; chỉ cấp khi trạng thái DB thực sự cho phép và chưa có số. Retry trả lại số đã lưu. Tạo đơn mới cũng cần xét idempotency để gửi lại request không sinh hai đơn.

### 2. P1 — Snapshot không cố định, sửa hoặc trả xe làm thay bản hợp đồng

File: `app/Http/Services/OrderService.php:77,105,573–672,1132`.

`maybeGenerateContractSnapshot()` luôn dựng lại rồi gán `contract_snapshot`, được gọi khi tạo, sửa và trả xe. Không có trạng thái chốt, điều kiện giữ snapshot đã phát hành hoặc version/phụ lục. `OrderShow.vue` vẫn đọc hồ sơ khách/xe hiện tại, không đọc snapshot.

Đã tái hiện: snapshot ban đầu tên `Fixture A`; thay tên hồ sơ trong relation rồi gọi lại hàm, snapshot lưu thành `Changed after signing`. Ngoài ra `loadMissing()` có thể giữ quan hệ đã load trước các thao tác update/sync, nên snapshot ngay lúc sửa còn có nguy cơ lấy dữ liệu cũ.

Sửa: xác định bước chốt hợp đồng, lưu snapshot đúng một lần từ dữ liệu vừa reload; chỉnh sửa sau chốt dùng phiên bản/phụ lục. Xác nhận trả xe lưu riêng; màn hình/bản in hợp đồng đã chốt đọc snapshot.

### 3. P1 — Đổi khách có thể lưu người thân của khách trước sang khách sau

File: `resources/js/src/view/pages/Order/components-order/OrderUpdate.vue:1533–1548`.

Khi lookup khách mới có `relatives = null` hoặc `[]`, code không reset `this.order.relatives`. Trường hợp thay trực tiếp CCCD/điện thoại từ khách A sang B mà không xóa trống trước đó giữ nguyên người thân A. Khi submit, array này được gửi và lưu vào hồ sơ B.

Đã chạy đúng method lấy từ AST của component với response fixture: `customer_after_lookup = Fixture B`, `relatives_after_lookup = Relative of A`.

Sửa: luôn gán đủ hai liên hệ theo response, kể cả khi trống; xử lý cả kết quả không tìm thấy và response lookup cũ đến chậm.

### 4. P2 — Ngày/nơi cấp CCCD nhập trên form không được lưu

File: `resources/js/src/view/pages/Order/components-order/OrderUpdate.vue:1419–1420`; `app/Http/Services/OrderService.php:547–552`.

Form gửi `customer_id_card_issued_on` và `customer_id_card_issued_by`; backend chỉ đọc `id_card_issued_on` và `id_card_issued_by`.

Đã gọi đúng method lưu khách với payload giống form: ngày `2020-01-02`, nơi cấp `Fixture issuer`; cả hai giá trị đọc lại từ DB đều `null`. Lưu request không báo lỗi nhưng dữ liệu bị bỏ qua.

Sửa: thống nhất tên field request, mapping và validation; test tạo/sửa/mở lại đúng payload từ UI.

### 5. P2 — Xác nhận trả xe bị mất khi chưa thanh toán

File: `app/Http/Services/OrderService.php:1098–1105,1122–1132`.

Nhánh `isPaid = false` đổi trạng thái sang `wait_payment` rồi return trước khi lưu tên hai bên và ghi chú trả xe. Các thông tin bàn giao phải lưu khi xe được trả, không phụ thuộc đã tất toán hay chưa.

Đã tái hiện: gửi người nhận và ghi chú vào `complete()` với `isPaid=false`; DB có trạng thái `wait_payment` nhưng tên người nhận/ghi chú vẫn `null`.

Sửa: lưu xác nhận trả cùng giờ trả thực tế trước khi tách nhánh tất toán; chỉ xác nhận số tiền hoàn sau khi giao dịch chi thành công.

### 6. P2 — Tìm số HĐ vẫn đưa chuỗi không phải số vào điều kiện ID

File: `app/Repositories/OrderRepositoryEloquent.php:119–126`.

Nhánh tìm kiếm mới thêm `OR contract_number LIKE ...` nhưng giữ điều kiện `orders.id = substr(keyword, 1)`. Với `2026/09/14-0001`, binding cho ID là `026/09/14-0001`, không phải giá trị bigint hợp lệ trên PostgreSQL. Nhánh ID không hợp lệ đã có sẵn cho keyword chữ; thay đổi mới chưa xử lý nó nên chức năng tìm số HĐ vẫn bị ảnh hưởng.

Đã kiểm câu SQL và binding sinh từ repository; chưa chạy truy vấn trên PostgreSQL. SQLite không chứng minh nhánh này hoạt động trên PostgreSQL.

Sửa: chỉ thêm điều kiện ID nếu keyword là số hoặc `#` theo sau bởi số; các keyword khác tìm các cột chuỗi. Thêm test query thực trên PostgreSQL test riêng.

### 7. P2 — API chưa validate các trường bổ sung

File: `app/Validators/OrderValidator.php:27–47`; `app/Http/Services/OrderService.php:158–181,503–507,554–555`.

Validator không được cập nhật. Đã thử validator tạo đơn với ngày ký/GPLX `not-a-date`, `relatives` là string và số áo mưa `-3`: `passes() = true`, không có lỗi field. Sau đó service có thể lỗi parse, lưu kiểu JSON không đúng cấu trúc hoặc lưu số lượng âm; `(int)`/`parseInt()` còn làm mất phần thập phân thay vì báo nhập sai.

Sửa: validate kiểu, độ dài, ngày, mảng tối đa hai liên hệ và integer ≥0 cho từng item; kiểm ID người phụ trách theo quyền; dùng cùng quy tắc trên create/update/complete. Không bắt các trường trả xe khi tạo đơn.

### 8. P2 — Snapshot giá/tiền chưa ánh xạ đúng nghiệp vụ

File: `app/Http/Services/OrderService.php:613,653–658`.

`unit_price = substitute_unit_price ?: handler_price` bỏ qua đơn giá mặc định của bảng giá; đơn thuê thông thường có thể chụp đơn giá 0. Ngược lại, `handler_price` là giá chốt tổng nhưng được lưu dưới tên `unit_price`, không kèm đơn vị/số ngày. Đã tái hiện giá cả gói 500.000 được đặt vào `unit_price`.

Snapshot còn lấy `total_rental_fees ?: total`, không lưu rõ tiền thuê đã thanh toán, CK/TM, ID giao dịch nguồn hoặc số tiền thực hoàn; không thể coi đây là dữ liệu hoàn chỉnh để điền mẫu PDF và đối chiếu sổ thu chi.

Sửa: lấy giá hiệu lực từ nhánh tính giá thực tế kèm chế độ/đơn vị/số ngày/gói; phân biệt giá phải thu với tiền đã thu và tiền cọc; chụp giao dịch nguồn/phương thức tương ứng, không tạo nguồn tính tiền riêng.

## Phần chưa khớp đặc tả

- Bên A đang hardcode tên công ty/MST/trụ sở/đại diện/chức vụ trong service, chưa có cấu hình và lựa chọn đại diện ủy quyền đầy đủ; chưa có ô chọn nhân viên phụ trách trên form.
- Tạo thuê mới truyền ngày ký vào bộ cấp số, còn kích hoạt cọc dùng ngày hiện tại. Quy tắc ngày trong mã chưa nhất quán như báo cáo mô tả; cần chọn rõ ngày cấp hay ngày ký khi lập lùi ngày.
- “Gói thuê” chưa có field/mapping rõ. Nhãn phần “Và …” của bảng trả xe đã bị diễn giải thành tình trạng xe trong UI dù đặc tả ghi chưa rõ ý nghĩa; nên giữ nhãn trung tính.
- Đơn thuê cũ chưa có số được hiển thị “Hệ thống tự cấp khi lưu đơn”, nhưng nhánh update thông thường không cấp số. Cần thông báo đúng và luồng xử lý đơn cũ riêng.
- Không có test mới cho tính năng trong working tree; build thành công không kiểm việc lưu CCCD, snapshot, cấp số hoặc trả xe.

## Kết quả kiểm tra và phạm vi

- `.\php.cmd artisan test`: **31 tests, 116 assertions, exit 0**. Đây là suite hiện hữu; các lỗi mới ở trên vẫn tái hiện dù suite đạt.
- `npm.cmd run production -- --output-path E:\duanthuexe\audit-prototype\contract-review\build`: **exit 0**. Có cảnh báo Browserslist/Sass; không đúng với diễn đạt “không có cảnh báo” trong báo cáo kia. Artifact kiểm build được xuất ngoài repo.
- Bốn migration mới chạy được trên schema fixture **SQLite `:memory:`**. Không kết nối DB vận hành; chưa xác minh migration, khóa và truy vấn trên PostgreSQL.
- Chạy script tái hiện bằng dữ liệu tổng hợp ngoài repo: `E:\duanthuexe\audit-prototype\contract-review\reproduce.php`, kết quả `results.json`; test lookup frontend: `reproduce-customer-switch.js`.
- Luồng lưu `relatives` trên fixture hiện tại có lưu được; không ghi nhận lỗi serialize array khi chưa có bằng chứng.
- Chưa sửa code tính năng, chưa commit/push trong lượt review. Các thay đổi code/bundle của AI kia được giữ nguyên. Chỉ bổ sung tài liệu review và script kiểm chứng ngoài repo.

Thứ tự sửa đề xuất: khóa/cấp số ổn định → snapshot → lẫn người thân → mapping CCCD → trả xe → tìm kiếm/validation → snapshot tiền và phần trường còn thiếu. Sau đó chạy lại test tạo/sửa/mở lại, cọc→thuê, trả xe chưa/đã thanh toán và kiểm đồng thời trên PostgreSQL riêng trước phát hành.
