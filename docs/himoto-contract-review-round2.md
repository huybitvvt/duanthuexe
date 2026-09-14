# Review hợp đồng HIMOTO — vòng 2

> Đã có [review vòng 3](himoto-contract-review-round3.md) cho bản sửa tiếp theo. Giữ nội dung bên dưới làm lịch sử bằng chứng vòng 2.

Ngày: 14/09/2026. Base `5f2f3fc`, working tree chưa commit trên `feature/himoto-complete-integration`. Đối chiếu báo cáo AI “đã xử lý triệt để 8 vấn đề” với source và các ca tái hiện mới.

**Kết luận: có sửa đúng một số lỗi, nhưng chưa đạt để phát hành.** Chạy lại frontend build thành công và PHPUnit đạt **39 tests, 157 assertions**. Các kết quả đó không bao phủ các lỗi dưới đây.

## 1. P1 — Gửi lại xác nhận trả xe chưa thanh toán làm cộng phí lần nữa

Nguồn: `app/Http/Services/OrderService.php:1166–1202`, đặc biệt phép gán `total = $order->total + $outdate_or_early_amount` tại dòng 1174.

Bản sửa chuyển cập nhật tổng tiền lên trước nhánh `isPaid=false`. Tổng đang lưu đã có khoản điều chỉnh nhưng request tiếp theo cộng lại khoản đó. Không có kiểm trạng thái/idempotency hoặc cách tính lại từ tiền gốc.

Đã gọi `complete()` hai lần với cùng dữ liệu trả xe chưa thanh toán, đọc lại đơn giữa hai lần:

| Dữ liệu | Kết quả |
|---|---:|
| Tổng gốc | 500.000 |
| Phí quá hạn | 50.000 |
| Sau lần đầu | 550.000 |
| Sau request lặp | **600.000** |

Đây là lỗi tổng tiền lưu trong DB, tái hiện trên SQLite độc lập. Chưa thực hiện thu/chi tiền thật. Nhánh chưa thanh toán trước bản sửa không cộng tổng nên đây là ảnh hưởng mới của cách sửa lưu xác nhận trả xe.

Cần sửa: tách lưu bàn giao khỏi cộng tiền; tính tổng từ dữ liệu gốc và các khoản điều chỉnh đã xác định hoặc áp dụng chênh lệch so với khoản đã ghi, bảo đảm lặp request không tăng tổng. Kiểm thêm trả xe chưa thanh toán → tất toán sau và retry sau mất response.

## 2. P2 — Snapshot vẫn lấy tổng tiền nhiều ngày làm đơn giá/ngày

Nguồn: `app/Http/Services/OrderService.php:618–627`.

Thêm `pricing_mode` chưa sửa nguồn đơn giá. Khi không có giá tùy chỉnh hoặc giá chốt, code lấy `hiring_fee`/`total_money` làm `unit_price`; đây là tổng tiền của nhiều ngày, không phải giá một ngày từ bảng giá.

Đã tái hiện xe thuê `type=day`, 14/09 09:00 → 17/09 09:00, tổng 450.000, `substitute_unit_price=0`, `handler_price=0`. Snapshot trả `pricing_mode=day`, **`unit_price=450000`**, trong khi ví dụ này tương ứng 150.000/ngày.

Test mới chỉ kiểm nhánh đã có `substitute_unit_price=150000` nên không phát hiện nhánh giá mặc định. Trường hợp `handler_price>0` cũng cần xác định đúng giá chốt tổng, kể cả item vẫn có `type=day`.

Cần sửa: lấy đơn giá hiệu lực từ đúng nhánh bảng giá/tính tiền, chụp chế độ/đơn vị/số ngày và giá chốt riêng. Không chia tổng theo ngày lịch một cách tùy ý vì hệ thống có quy tắc giờ lẻ và giá gói.

## 3. P2 — Snapshot bị khóa ngay lần lưu đầu, thông tin bổ sung sau đó không xuất hiện ở chi tiết

Nguồn: `app/Http/Services/OrderService.php:77,108,584–589`; `resources/js/src/view/pages/Order/components-order/OrderShow.vue:233–238`.

Mọi đơn thuê mới đều tạo snapshot ngay trong `store()`, kể cả khi còn thiếu các trường bổ sung. Guard mới giữ snapshot vĩnh viễn, trong khi form vẫn cho sửa hồ sơ/người ký và báo lưu thành công. Không có bước chốt riêng, trạng thái phân biệt đã ký hoặc luồng phiên bản/phụ lục để xử lý sửa sai.

Đã tái hiện: tạo snapshot lúc nơi cấp CCCD trống → điền nơi cấp vào hồ sơ → gọi lại hàm snapshot như cuối update. DB khách có `Issuer filled after initial save` nhưng snapshot vẫn `null`. `displayCustomer()` luôn ưu tiên snapshot nên màn hình chi tiết tiếp tục hiện thiếu, trong khi form sửa đọc DB mới.

Cần sửa: cho cập nhật dữ liệu trước bước chốt; chỉ khóa khi phát hành/ký và đã kiểm dữ liệu bắt buộc. Sau chốt, giao diện cần khóa trường liên quan hoặc có luồng sửa bằng phiên bản/phụ lục. Không bỏ guard rồi quay lại ghi đè hợp đồng đã ký.

Ngoài ra, `OrderShow.vue` vẫn đọc xe/người lái/ngày thuê/ngày ký/ủy quyền/tài sản thế chấp từ dữ liệu hiện tại, nhưng khách/người ký từ snapshot. Cần nhất quán nguồn dữ liệu khi xem bản hợp đồng đã chốt.

## 4. P2 — Snapshot xác nhận trả xe ghi giờ xử lý, không phải giờ trả thực tế

Nguồn: `app/Http/Services/OrderService.php:737,1147,1175`.

Thời gian nhân viên nhập được lưu vào item, nhưng `orders.completed_at` vẫn gán `now()`; `updateReturnConfirmationInSnapshot()` lấy chính giá trị này.

Đã cố định đồng hồ xử lý là 19:00 rồi gửi giờ trả 15:00 cùng ngày:

- Item lưu đúng `14/09/2026 15:00:00`.
- Snapshot xác nhận trả xe ghi **`14/09/2026 19:00:00`**.

Cần sửa: dùng giờ trả thực tế của lần trả/item cho bản xác nhận; lưu riêng thời điểm xử lý/tất toán nếu cần. Test hiện mới assert tên hai bên và ghi chú, chưa assert giờ trả.

## 5. P2 — Xóa thông tin khách chưa vô hiệu hóa request lookup đang chờ

Nguồn: `resources/js/src/view/pages/Order/components-order/OrderUpdate.vue:1530–1551`.

Sequence token tăng khi gọi lookup mới, nhưng `resetCustomerInfo()` không tăng token. Khi nhân viên xóa CCCD/điện thoại lúc request đang chạy, response cũ vẫn hợp lệ và điền lại khách/người thân vừa xóa. Tình huống hai lookup liên tiếp đã được cải thiện, nhưng clear/reset chưa được xử lý.

Đã chạy đúng hai method lấy từ AST component: gọi lookup → reset → trả response cũ. Kết quả form lại chứa `Customer from cleared search`, CCCD cũ và `Old relative`.

Cần sửa: vô hiệu hóa lookup khi reset, khi bắt đầu đơn mới/đổi đơn hoặc hủy component; kiểm response ứng với đầu vào hiện tại. Thêm test clear khi pending, hai lookup đảo thứ tự và lookup thất bại.

## 6. P2 — Ngày CCCD từ snapshot bị hiển thị sai hoặc Invalid date

Nguồn: `app/Http/Services/OrderService.php` chụp ngày CCCD bằng `format('d/m/Y')`; `resources/js/src/view/pages/Order/components-order/OrderShow.vue:132–135`; `resources/js/src/filters/index.js:11–16`.

Màn hình mới lấy `displayCustomer` từ snapshot rồi truyền ngày dạng `DD/MM/YYYY` vào filter gọi `moment(String(value))` mà không chỉ định định dạng. Node/Moment hiện tại tái hiện:

| Ngày snapshot | Filter hiển thị | Kỳ vọng |
|---|---|---|
| `15/05/2022` | **Invalid date** | `15-05-2022` |
| `02/01/2020` | **01-02-2020** | `02-01-2020` |

Cần sửa: lưu ngày có cấu trúc ISO `YYYY-MM-DD` trong snapshot mới; với snapshot cũ dùng parse định dạng tường minh, không đoán theo trình duyệt. Không sửa filter toàn cục mà thiếu kiểm tương thích các màn hình khác.

## Những phần đã được sửa và giới hạn xác minh

| Vấn đề vòng 1 | Kết quả vòng 2 |
|---|---|
| Mapping CCCD | Đã tái hiện lưu đúng `2020-01-02` và `Fixture issuer` từ tên field frontend |
| Lẫn người thân khi chọn khách không có liên hệ | Lookup thông thường đã reset về liên hệ trống; còn ca clear khi pending ở mục 5 |
| Tên/ghi chú trả xe khi chưa thanh toán | Đã lưu được trên đơn và snapshot; còn lỗi giờ trả và cộng tiền ở mục 1, 4 |
| Đổi số do model cũ lúc kích hoạt | `update()` đã khóa và đọc lại model trong transaction của controller. Tái hiện qua đúng entry point với hai model load trước: cùng số `...0001` sau cả hai lần. Các bước tiền/xe được stub trong ca cô lập này; chưa kiểm hai kết nối PostgreSQL đồng thời |
| Tìm số HĐ bị ép sang bigint | Query đã tách nhánh ID; suite kiểm SQL/bindings đạt, chưa chạy PostgreSQL thực |
| Validation mới | Đã chặn ngày không parse được, người thân sai kiểu và áo mưa âm; vẫn thiếu kiểm quan hệ ngày và quyền của `contract_responsible_user_id`, alias `customer_relatives` cũng chưa có rule tương ứng |
| Snapshot bất biến | Có guard, nhưng thời điểm chốt và nguồn hiển thị chưa đúng; xem mục 3 |
| Giá/tiền trong snapshot | Có thêm danh sách giao dịch, nhưng đơn giá vẫn sai ở mục 2; chưa chứng minh tách tiền thuê đã thu, cọc thực giữ và hoàn thực tế |

`ContractNumberService::generateForOrder()` mới chỉ trả số có trong model được truyền vào; không tự khóa/lưu. Luồng production vẫn gọi `generate()` trực tiếp. Test gán số vào model chưa lưu rồi gọi wrapper không phải test đồng thời/idempotency của API tạo đơn. Khóa tại `update()` là thay đổi có tác dụng, không phải wrapper đó.

## Kiểm thử đã chạy

- `.\php.cmd artisan test`: **39 tests, 157 assertions, exit 0**.
- `npm.cmd run production -- --output-path E:\duanthuexe\audit-prototype\contract-review\build-round2`: **exit 0**, vẫn có cảnh báo Browserslist/Sass. Artifact kiểm build nằm ngoài repo.
- `reproduce-round2.php`: dùng schema fixture của test mới, chỉ SQLite `:memory:`, không chạm DB live. Kết quả lưu `E:\duanthuexe\audit-prototype\contract-review\results-round2.json`.
- `reproduce-customer-round2.js`: chạy các method/filter lấy trực tiếp từ source với response giả; kết quả `customer-results-round2.json` cùng thư mục. Đây không phải browser E2E.
- Test mới dùng schema tự dựng, không phải migrations PostgreSQL thật. Chưa có test đồng thời PostgreSQL, chưa kiểm giao dịch qua browser/API thực, chưa có cơ sở kết luận đã xử lý triệt để toàn bộ tính năng.

Lượt này chỉ review, giữ nguyên code của AI kia; chưa commit/push hay chạy migration live. Ưu tiên sửa lỗi cộng tiền lặp trước, sau đó thời điểm chốt snapshot, đơn giá, giờ/ngày hiển thị và lookup đang chờ.
