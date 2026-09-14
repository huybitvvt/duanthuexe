# Review hợp đồng HIMOTO — vòng 3

Ngày: 14/09/2026. Base `5f2f3fc`, nhánh `feature/himoto-complete-integration`, các thay đổi chưa commit. Đối tượng: bản sửa tiếp theo sau review vòng 2.

**Kết luận: chưa đạt để phát hành.** Các ca đơn giản của vòng 2 đã được cải thiện, nhưng kiểm chuỗi tính trước → hoàn tất phát hiện lỗi thiếu phí quá hạn. Còn sai bậc đơn giá, thời điểm khóa hợp đồng, ngày giờ hiển thị và số HĐ của đơn cũ.

## 1. P1 — Bỏ sót phí quá hạn khi chạy bước tính trước rồi hoàn tất

Nguồn: `app/Http/Services/OrderService.php:1235–1242`; `app/Helpers/CarRentalHelper.php:90–93`; `app/Http/Controllers/Order/OrderController.php:431–436`.

`complete()` mới tính tổng bằng chênh lệch giữa khoản điều chỉnh request và `orders.outdate_or_early_amount`. Nhưng field này không phải bằng chứng khoản điều chỉnh đã được cộng vào tổng. Khi mở modal trả xe, luồng `calc_order_before_complete` gọi helper ghi **số tiền tính trước** vào field đó, trong khi giữ nguyên `orders.total`.

Đã chạy đúng hai helper của bước tính trước, sau đó gọi `complete()` trên SQLite riêng:

| Bước | Tổng đơn | Khoản điều chỉnh |
|---|---:|---:|
| Trước tính | 500.000 | 0 |
| Tính trước: xe côn trả muộn 2 giờ, 25.000/giờ | 500.000 | 50.000 |
| Hoàn tất trả xe chưa thanh toán | **500.000** | 50.000 |
| Tổng cần có | **550.000** | 50.000 |

Do `delta = 50000 - 50000 = 0`, phí không được đưa vào tổng. Đây là ca tái hiện service/helper theo trình tự của UI, chưa phải browser E2E. Test mới chỉ gọi `complete()` trực tiếp từ khoản điều chỉnh 0 nên không phát hiện lỗi.

Sửa: phân biệt khoản tính trước và khoản đã hạch toán vào tổng; hoặc tính tổng từ nguồn dữ liệu nghiệp vụ xác định, không dùng chung field này làm dấu đã cộng. Kiểm cả tính trước → trả xe, gọi lặp, đổi giờ trả, trả sớm và tất toán sau. Tổng không tăng khi retry chưa đủ chứng minh số tiền đúng.

## 2. P2 — Snapshot chọn sai bậc giá khi bảng giá có nhiều dòng

Nguồn: `app/Http/Services/OrderService.php:648–659`; `app/Helpers/CarRentalHelper.php:184–195`; đối chiếu `resources/js/src/view/pages/Order/components-order/OrderUpdate.vue:949–969`.

Snapshot mới gọi `CarRentalHelper::getUnitPrice()`, nhưng helper chỉ lọc loại xe và `price_type`, rồi lấy dòng đầu. Form hiện tại còn lọc khoảng số ngày (`from_date/to_date`) và năm sản xuất (`from_year/to_year`). Vì vậy giá snapshot có thể khác giá nhân viên đã chọn/tính trên form.

Đã tạo hai dòng giá xe ga theo ngày cùng khoảng năm 2020–2026:

- 1 ngày: 200.000/ngày, được insert trước.
- 2–5 ngày: 150.000/ngày.

Xe năm 2023 thuê 3 ngày: snapshot ghi **200.000/ngày**, trong khi bậc áp dụng là **150.000/ngày**. Nhánh lọc thiếu điều kiện đã có trong helper cũ, nhưng bản sửa mới dùng helper đó để chụp giá nên đưa lỗi vào snapshot.

Nhánh fallback `round(diffHours / 24)` cũng không tương đương quy tắc hiện có: form tính dưới 8 giờ lẻ theo giờ, từ 8 giờ trở lên thành một ngày. Không nên suy đoán đơn giá bằng chia tổng khi thiếu bảng giá, rồi lưu như đơn giá đã xác nhận.

Sửa: dùng cùng nguồn đơn giá và điều kiện với nghiệp vụ tính tiền; chụp giá đã áp dụng, đơn vị, số ngày và khoản giờ lẻ. Test cả nhiều bậc ngày, nhiều đời xe, giờ lẻ, giá tùy chỉnh và giá chốt gói.

## 3. P1 — Hợp đồng đã ký vẫn có thể bị ghi lại khi đang thuê

Nguồn: `app/Http/Services/OrderService.php:587–589,714,774`; lời gọi snapshot ở cuối `store()`/`update()`.

Bản sửa mới coi `completed` hoặc `wait_payment` là thời điểm khóa. Hai trạng thái này thuộc trả xe/tất toán; không đại diện cho việc khách đã ký hợp đồng lúc nhận xe. Chưa có bước chốt/phát hành riêng hoặc thao tác đặt `is_locked=true` trong thời gian đang thuê. Việc có `contract_signed_on` và số HĐ không làm khóa bản đã ký.

Đã tái hiện: đơn `renting` có ngày ký `2026-09-14` và số HĐ, snapshot `is_locked=false`; sửa tên khách rồi chạy hàm snapshot như cuối cập nhật làm tên trong snapshot đổi từ `Fixture A` thành `Name changed while signed contract is renting`.

Sửa: xác định bước phát hành/chốt trước ký, validate dữ liệu và khóa snapshot tại bước đó; bản chưa chốt vẫn sửa được. Sau chốt dùng phiên bản/phụ lục. Không đánh đồng `renting` với bản chưa ký hoặc đợi trả xe mới khóa. Đây là vấn đề vòng trước chưa giải quyết, không phải yêu cầu mới.

## 4. P2 — Giờ thuê/hẹn trả trong snapshot vẫn có thể đảo ngày và tháng

Nguồn: `resources/js/src/filters/index.js:24–33`; `resources/js/src/view/pages/Order/components-order/OrderShow.vue:286–304`; các field `rent_at/return_at` trong snapshot service.

Ngày CCCD ISO và dạng ngày cũ đã xử lý đúng. Tuy nhiên snapshot thời gian thuê vẫn dùng `d/m/Y H:i`, còn danh sách parse strict thiếu **`DD/MM/YYYY HH:mm`**. `displayOrderItems()` nay ưu tiên lấy thời gian này rồi gửi vào filter toàn cục; filter rơi về Moment tự đoán định dạng.

Đã chạy filter trực tiếp lấy từ source:

| Giá trị snapshot | Kết quả | Kỳ vọng |
|---|---|---|
| `02/01/2020 09:00` | **01-02-2020 09:00:00** | 02-01-2020 09:00:00 |
| `14/09/2026 09:00` | Giữ nguyên chuỗi `14/09/2026 09:00` | Hiển thị thống nhất theo định dạng của ứng dụng |

Sửa: lưu thời gian có cấu trúc nhất quán, parse rõ các định dạng snapshot cũ gồm cả giờ/phút không có giây. Kiểm các ngày 1–12 để bắt đảo ngày/tháng; không chỉ thử ngày >12 hoặc ngày không có giờ.

## 5. P2 — Trả đơn cũ sinh số HĐ chỉ trong snapshot

Nguồn: `app/Http/Services/OrderService.php:612–614,774–775,780–784`.

Hàm dựng snapshot tự gọi `ContractNumberService::generate()` khi đơn chưa có số, nhưng chỉ lưu chuỗi vào JSON. Nó không ghi `orders.contract_number` hoặc `orders.contract_issued_at`. Luồng trả xe của đơn cũ không qua nhánh cấp số trong `updateOrCreateOrder()`.

Đã tạo đơn thuê cũ chưa có số/snapshot rồi trả xe chưa thanh toán. Kết quả:

- `orders.contract_number = null`.
- `contract_snapshot.contract_number = 2026/09/14-0002`.

Danh sách/tìm kiếm đọc cột số HĐ sẽ không thấy số trong snapshot. Nếu sửa đơn cũ sau đó, luồng cấp số thông thường còn có thể tạo số khác trong cột, trong khi snapshot khóa giữ số trước.

Sửa: chỉ một luồng cấp và lưu số HĐ vào cột cùng thời điểm cấp trong transaction có khóa, sau đó snapshot tham chiếu số đó. Hàm dựng snapshot không nên tự cấp số. Chính sách đơn cũ cần rõ ràng; nếu chưa cấp thì để trống nhất quán.

## Các phần đã xác minh cải thiện

- Lặp `complete()` trực tiếp khi chưa thanh toán: tổng giữ 550.000 thay vì tăng 600.000. **Giới hạn:** không đúng khi có bước tính trước, xem mục 1.
- Ngày/nơi cấp CCCD lưu được; bổ sung dữ liệu lúc đang thuê được cập nhật vào snapshot. **Giới hạn:** chưa có bước chốt khi ký, xem mục 3.
- Nhập giờ trả 15:00 khi đồng hồ xử lý 19:00: item và snapshot đều giữ đúng 15:00.
- Xóa khách khi request lookup đang chờ: response cũ bị loại. Lookup bình thường với giá trị hiện tại của form cũng hoạt động và reset người thân khách trước.
- Ngày CCCD `15/05/2022` và `02/01/2020` hiển thị đúng; snapshot ngày mới dùng ISO. **Giới hạn:** thời gian thuê thiếu giây còn lỗi ở mục 4.
- Ví dụ thuê đúng 3 ngày khi không có dòng bảng giá: fallback ra 150.000. **Giới hạn:** bảng giá nhiều bậc và giờ lẻ chưa đúng, xem mục 2.

Script frontend vòng 2 cũ chưa đặt CCCD hiện tại vào fixture nên guard mới bỏ qua lookup bình thường; output giữ khách A không phải bằng chứng lỗi UI. Đã bổ sung fixture đúng đầu vào ở vòng 3, lookup chuyển sang B và reset người thân như kỳ vọng.

## Kiểm tra đã chạy

- `.\php.cmd artisan test`: **46 tests, 179 assertions, exit 0**.
- Các script tái hiện vòng 2 đã chạy lại; kiểm phạm vi từng ca như trên. Script in output không có assertion cho mọi trường hợp, nên exit 0 không tự chứng minh mọi ca đạt.
- Script vòng 3: `E:\duanthuexe\audit-prototype\contract-review\reproduce-round3.php`, kết quả `results-round3.json`; frontend `reproduce-frontend-round3.js`, kết quả `frontend-results-round3.json` cùng thư mục.
- Backend fixture chỉ SQLite `:memory:`, dữ liệu tổng hợp. Không kiểm giao dịch tiền live; chưa chạy PostgreSQL đồng thời hoặc browser E2E.
- `npm.cmd run production -- --output-path E:\duanthuexe\audit-prototype\contract-review\build-round3`: **exit 0**. Log lưu `build-round3.log`; có cảnh báo dependency/Sass. Build output kiểm tra nằm ngoài repo.

Lượt này giữ nguyên code tính năng của AI kia, chỉ thêm tài liệu review/script kiểm chứng. Chưa commit/push hoặc chạy migration live. Ưu tiên sửa mục 1 trước và thêm test đủ chuỗi nghiệp vụ; không chỉ sửa để các fixture đơn lẻ đổi ra giá trị mong muốn.
