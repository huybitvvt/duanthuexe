# SePay + VPBank/MBBank: thu tiền tự động

## Cách hoạt động

Trong **Phiếu thu chi → Thu tiền qua QR SePay**, nhân viên chọn cơ sở, loại khoản thu, số tiền và nội dung. Khoản **cọc, phí thuê, cọc bổ sung** cần ID hợp đồng; **gia hạn** cần chọn xe trong hợp đồng và ngày trả mới. Khi nhập ID hợp đồng, màn hình tải trạng thái thu và danh sách xe; phí thuê được điền theo tổng phí hợp đồng. Hệ thống tạo mã `HMT` + 10 ký tự riêng và VietQR có sẵn số tài khoản, số tiền, mã chuyển khoản. Chưa có tiền vào ngân hàng thì chưa ghi thu.

Khi SePay báo tiền vào, API kiểm tra API Key, đúng ngân hàng và số tài khoản đã cấu hình. Mỗi `id` giao dịch SePay chỉ được xử lý một lần. Tiền thực nhận được ghi theo đúng loại: phiếu thu chung, cọc, phí thuê, cọc bổ sung hoặc gia hạn. Cọc và phí thuê cập nhật số đã thu trên hợp đồng; gia hạn cập nhật ngày trả của xe **sau khi nhận đủ tiền**. Khoản chuyển thiếu ghi số thực nhận và chờ thêm; tiền chuyển dư tạo phiếu thu riêng chưa phân bổ vào hợp đồng. Giao dịch không khớp mã chưa tạo phiếu thu. Nếu hợp đồng đã có bút toán thủ công, đã đóng hoặc ngày trả đã thay đổi trong khi chờ chuyển khoản, tiền vẫn ghi phiếu thu ngân hàng nhưng được đánh dấu **Cần đối chiếu**, không tự phân bổ lần hai.

Hệ thống chỉ cho tạo QR cọc/phí thuê ban đầu khi hợp đồng được lập với tùy chọn **chưa thu** khoản đó. QR phí thuê phải bằng tổng phí thuê hợp đồng; khách có thể chuyển nhiều lần để trả đủ. Không tạo QR cho khoản đã ghi thu khi lập hợp đồng. Mỗi hợp đồng chỉ có một mã QR đang chờ cho cùng loại khoản thu; gia hạn giới hạn theo từng xe. Sau khi SePay ghi thu, không sửa số tiền khoản đó bằng màn hình chỉnh hợp đồng thông thường; nghiệp vụ điều chỉnh cần quy trình đối chiếu riêng.

Giao dịch SePay và hợp đồng đã nhận tiền SePay không thể xóa thủ công từ các màn hình tương ứng. Hãy đối chiếu và xử lý sai lệch bằng nghiệp vụ điều chỉnh riêng; không xóa dấu vết giao dịch ngân hàng.

## Cần chuẩn bị

1. Để demo, kết nối tài khoản VPBank cá nhân với SePay theo [hướng dẫn VPBank của SePay](https://docs.sepay.vn/ket-noi-vpbank.html). Khi chuyển sang MBBank, dùng [hướng dẫn MB](https://docs.sepay.vn/ket-noi-mb-api.html). Chuyển một khoản thử để xác nhận SePay thấy giao dịch.
2. Trong **Ngân hàng** của HIMOTO, tạo tài khoản VPBank đang hoạt động, đúng **số tài khoản** và **tên chủ tài khoản**. Ghi lại ID dòng ngân hàng. Nếu một tài khoản nhận tiền cho nhiều cơ sở, dòng ngân hàng phải là **ngân hàng dùng chung** (`store_id=0`); nếu gắn với một cơ sở, chỉ cơ sở đó được tạo QR.
3. Tạo API Key dài, ngẫu nhiên dùng riêng cho webhook này. Lưu trong secret của máy chủ, không đưa vào mã nguồn hoặc biến `MIX_*` của frontend.
4. Chạy migration `2026_09_27_000001_create_sepay_payment_tables.php` trên bản sao/staging trước, rồi chạy migration khi phát hành bản đã được kiểm thử. Không chạy migration trên dữ liệu production từ máy local.

Biến môi trường **backend**:

```dotenv
SEPAY_BANK_CODE=VPB
SEPAY_BANK_ID=<ID dòng ngân hàng VPBank trong HIMOTO>
SEPAY_ACCOUNT_NUMBER=<số tài khoản VPBank, chỉ chữ số>
SEPAY_WEBHOOK_API_KEY=<API Key riêng của webhook>
```

Khi đổi sang MBBank, đặt `SEPAY_BANK_CODE=MB` cùng ID và số tài khoản MB tương ứng. Chỉ cấu hình **một ngân hàng nhận tiền tại một thời điểm**; hoàn tất các mã QR đang chờ và đối chiếu giao dịch trước khi đổi. Sau khi cập nhật biến môi trường, xóa cache cấu hình Laravel và khởi động lại backend để nạp cấu hình mới.

## Thiết lập webhook trong SePay

- URL: `https://<backend-domain>/api/sepay/webhook`
- Sự kiện: **Có tiền vào**; chọn đúng tài khoản VPBank đã cấu hình cho demo.
- Kiểu chứng thực: **API Key**, cùng giá trị `SEPAY_WEBHOOK_API_KEY` trên backend.
- Kiểu dữ liệu: **JSON**.
- Cấu trúc mã thanh toán: tiền tố `HMT`, hậu tố **10 ký tự chữ/số**. Mã được gửi nguyên vẹn trong nội dung chuyển khoản. [SePay giới hạn hậu tố 1–10 ký tự trong giao diện cấu hình](https://docs.sepay.vn/cau-hinh-chung.html).
- Gửi cả giao dịch không có mã để quản trị viên đối chiếu các khoản chưa khớp; không bật tùy chọn bỏ qua khi thiếu mã.

SePay yêu cầu endpoint trả HTTP 200/201 với `{"success":true}`; API chỉ trả kết quả này sau khi đã lưu sự kiện/giao dịch. Các lỗi cấu hình hoặc cơ sở dữ liệu trả lỗi để SePay thử gửi lại theo lịch của họ. Xem [định dạng webhook và cơ chế gửi lại](https://docs.sepay.vn/tich-hop-webhooks.html) và [định dạng QR](https://docs.sepay.vn/tao-qr-code-vietqr-dong.html).

## Kiểm tra trước khi dùng thật

1. Tạo yêu cầu thu trên staging, quét QR và kiểm tra app ngân hàng hiện đúng tên người nhận, số tài khoản, số tiền và mã `HMT...`.
2. Dùng chức năng **Gửi thử** của SePay; xác nhận cọc, phí thuê và gia hạn ghi đúng loại bút toán. Gia hạn chỉ đổi ngày trả khi nhận đủ.
3. Gửi lại cùng `id` SePay; xác nhận không thêm bút toán. Thử tiền thiếu, đủ, dư, mã sai và khoản đã ghi thu thủ công; kiểm tra danh sách **Cần đối chiếu**.
4. Khi test thật, chuyển khoản nhỏ vào đúng tài khoản và đối chiếu ID giao dịch, sao kê VPBank, phiếu thu và số dư ngân hàng trong HIMOTO. Dùng dữ liệu hợp đồng demo trên staging vì giao dịch thật sẽ ghi sổ thu tương ứng.

**Lưu ý vận hành:** Nếu hợp đồng đã nhận tiền SePay, màn hình chỉnh hợp đồng không ghi lại các khoản thu; thay đổi số tiền đã thu bị từ chối để tránh ghi đúp. Các giao dịch **Cần đối chiếu** và tiền chuyển dư cần nhân viên xử lý thủ công trên sao kê trước khi kết luận công nợ.
