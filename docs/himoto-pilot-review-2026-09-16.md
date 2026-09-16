# Kiểm tra bản pilot ngày 16/09/2026

Baseline của AI trước: `f89bb52`, chưa có trên `origin/main` lúc bắt đầu kiểm tra. Phạm vi ưu tiên: hợp đồng để demo theo `himoto-pilot-implementation-handoff.md`.

## Kết luận

Bản trước chưa hoàn thành toàn bộ kế hoạch. Đã sửa các lỗi dưới đây để bàn giao bản demo có giới hạn rõ ràng. Không dùng kết quả unit test hoặc giao diện giả lập để chứng nhận hệ thống đã vận hành thật.

## Lỗi đã sửa

| Phần | Sai trước sửa | Kết quả sửa |
|---|---|---|
| Xem trước/in | Tổng tiền được truyền làm đơn giá/ngày; sai key tên xe, địa chỉ/điện thoại chi nhánh; `App\User` không tồn tại | Dùng đơn giá từng xe, schema snapshot và model thực tế |
| Ngày và thông tin in | Có thể đảo ngày `02/01`; tự điền Honda, màu “Tiêu chuẩn”, năm sản xuất dù không có dữ liệu | Parse ngày Việt Nam tường minh; trường thiếu để trống |
| Tiền trên hợp đồng | Có thể in tổng phải thu/cả cọc thành tiền thuê đã thanh toán; tiền hoàn dự kiến thành tiền đã trả | Tiền thuê thực nhận lấy giao dịch thuê; tiền hoàn lấy giao dịch chi hoàn tất |
| Khóa tài liệu | Bản in còn lấy dữ liệu hiện tại khi trường snapshot trống | Lưu DTO tài liệu khi chốt; giữ nội dung đã chốt khi dữ liệu khách/cấu hình thay đổi; xác nhận trả xe độc lập |
| Số cũ | Chốt đơn chỉ có số trong snapshot có thể cấp số khác | Giữ số lịch sử, không tăng bộ đếm |
| In và mobile | In trực tiếp modal đang scale/scroll; toolbar tràn màn hình nhỏ | In tài liệu trong iframe riêng, chờ stylesheet/font/ảnh; toolbar xuống hàng và thu vừa màn hình |
| Thuê sở hữu | Form gửi `cash`/`bank_transfer` cho API số; ngày thu sai key; dư nợ/lịch kỳ sai key | Đồng bộ payload và hiển thị; ghi đúng ngày giao dịch |
| Thu tiền | Retry có thể thu hai lần; chấp nhận thu vượt nợ, tài khoản khác kho | Khóa hợp đồng + khóa idempotency; kiểm dư nợ, trạng thái, quyền kho/ngân hàng; TM gắn quỹ đang hoạt động |
| Lịch kỳ | Trả trước không có khoản thu/kỳ tương ứng; ngày cuối tháng tràn sang tháng sau | Trả trước là kỳ 0 phải thu, không tự tạo phiếu thu; kỳ cuối cân tổng; lịch tháng không tràn |
| Công nợ | Lọc tuổi nợ sau phân trang; thiếu phạm vi quyền; export gọi sai API URL | Lọc trước paginate, scope cơ sở; Excel tải blob và duyệt các trang; thêm phân loại khi ghi chú |
| Kho | GET summary tự tạo kho; chi nhánh được xem chi tiết ngoài yêu cầu; thiếu xe điện | Summary chỉ đọc; chi tiết kho cho admin; thêm xe điện, sửa tổng xe đang chuyển theo phiếu |
| Trả/đổi xe | Có thể giải phóng xe khi đơn đang thuê, đổi xe không thuộc đơn | Chặn trạng thái sai, xe không thuộc đơn, trả trùng/nhiều xe không rõ đích; giới hạn luồng chưa hạch toán đầy đủ |
| Nhắc nợ | Dry-run và nhánh chưa có provider vẫn đánh dấu đã gửi | Không đánh dấu sent; live thiếu provider báo lỗi, giữ pending; API vận hành giới hạn admin |

## Demo và triển khai

1. Triển khai cùng bản API/frontend từ nhánh `main`; push không đồng nghĩa Render đã triển khai xong.
2. Bản này thêm migration `2026_09_16_000003_create_lease_payment_requests.php`. Cần áp dụng migration trước dùng thu tiền thuê sở hữu. `docker/render-start.sh` chỉ tự chạy migration nếu `RUN_MIGRATIONS=true`. Trong phiên kiểm tra không chạy migration vào database live.
3. Demo ưu tiên **Đơn thuê xe → Tạo hợp đồng → nhập thông tin → Xem trước cạnh Lưu → In nháp → Lưu → In ở danh sách/chi tiết**. Số giữ dạng `YYYY/MM/DD-0001` theo cấu hình hiện hành; số cũ giữ nguyên.
4. Dùng dữ liệu thử riêng. Thuê sở hữu cần kho có `kind=lease_to_own`, xe sẵn sàng, quỹ TM đang hoạt động hoặc tài khoản CK đúng cơ sở. Nhập trả trước là nghĩa vụ kỳ 0; bấm Thu tiền để ghi nhận thực thu.
5. Trả khác cơ sở hiện cần hoàn tất trả xe/đối soát trong đơn trước, rồi ghi nhận vị trí cho đơn một xe. Đổi xe hiện hỗ trợ cùng cơ sở, không chênh lệch giá; chuyển xe thay thế về cơ sở nhận trước khi đổi.
6. Rollback ứng dụng bằng commit đã kiểm; không drop bảng đã có phiếu thu/idempotency thật.

## Chưa được coi là xong kế hoạch

- GPS thật, SMS/Zalo/email và worker/scheduler gửi thật: chưa có nhà cung cấp/cấu hình được xác minh. Không gửi khách thật trong kiểm tra.
- Trả khác cơ sở trực tiếp trong một thao tác, đơn nhiều xe, đổi khác cơ sở với bàn giao hai chiều và chênh lệch giá: còn thiếu tích hợp nghiệp vụ đầu cuối.
- Thuê sở hữu: đảo thu, chính sách tất toán/chuyển quyền sở hữu, đối soát dữ liệu lịch sử, báo cáo PDF riêng còn phải hoàn thiện. Có Excel và lịch kỳ, không đồng nghĩa toàn bộ D01 đã đạt.
- Concurrency trên PostgreSQL, UAT cùng nhân viên, máy in giấy và kiểm live sau deploy chưa thực hiện.
- Các phần mở rộng trong Excel như kế toán/VAT, nhân sự/ca làm, KPI và thu chi nâng cao không nằm trong bản demo ưu tiên này.

## Bằng chứng kiểm tra

- PHPUnit chạy trên database kiểm thử, có hồi quy idempotency, thu vượt nợ, phạm vi cơ sở, lọc tuổi nợ trước paginate, cuối tháng/trả trước, bất biến tài liệu, số legacy, không giải phóng xe đang thuê, không đổi xe ngoài đơn, không báo gửi nhắc giả.
- `npm run build:static` build bundle thực, exit 0. Cảnh báo Sass/Browserslist của dependency hiện hữu còn tồn tại.
- Browser dùng bundle biên dịch thực với API fixture, không phải backend live: thu tiền gửi method số + key retry + ngày thu; ghi chú gửi đúng field; không có lỗi JavaScript; xem trước ở 390/768/1440px, in iframe ra PDF A4 ngang.
- Bằng chứng local: `E:/duanthuexe/audit-prototype/pilot-demo/results.json`, ảnh `contract-390.png`, `contract-desktop.png`, PDF `contract-a4.pdf`; script `verify-pilot-demo.py`. PDF một xe xuất 1 trang A4 ngang, không có sidebar/modal trong trang in.

Xem kết quả cuối trong commit bàn giao và phản hồi phiên làm việc; tài liệu này không thay thế nghiệm thu live.
