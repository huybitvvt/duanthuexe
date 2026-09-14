# Kiểm tra giao diện đăng nhập HIMOTO

## Cập nhật theo yêu cầu bố cục một cột

Trang login hiện chỉ có logo phía trên và form căn giữa trên desktop/mobile; đã bỏ panel giới thiệu bên trái. Logo `public/images/branding/logo-himoto-original.svg` được xuất trực tiếp từ PDF người dùng cung cấp, giữ nền đỏ, Hi vàng và MOTO trắng. Kết quả kiểm tra và ảnh của bố cục mới lưu tại `audit-prototype/login-centered/` trong workspace cha. Các ghi nhận bên dưới mô tả lần kiểm tra trước thay đổi bố cục này.

Ngày: 2026-09-14. Nguồn được kiểm: a229ef0 và bản sửa bổ sung trong commit chứa tài liệu này.

## Sửa sau kiểm tra

- Logo mobile dùng bản dark để đọc được trên nền sáng; bỏ aria-hidden khỏi header logo.
- Khôi phục ô xác nhận mật khẩu của form referral/signup: backend register yêu cầu password confirmed.
- Bỏ tabindex dương để bàn phím theo thứ tự DOM; bổ sung reduced-motion cho transition/spinner.
- Test chỉ lưu ảnh vào output-dir, bỏ đường dẫn tự copy vào thư mục riêng của AI khác. API fallback dùng fixture, tránh gửi request sang server thật.
- Bổ sung assertion logo mobile được tải đúng và payload đăng ký có password_confirmation/referral_code.

## Đã chạy

- npm run build:static: exit 0.
- python tests/test_login_ui.py --base-url http://localhost:8091 --output-dir ../audit-prototype/login-redesign-independent: exit 0, 9/9 nhóm kiểm tra.
- python tests/test_user_switching_cache.py: exit 0, chuyển phiên A/B và pending-response cùng user khác phiên không ghi cache cũ.
- .\php.cmd artisan test: exit 0, 31 tests / 116 assertions.
- git diff --check: exit 0.
- Xem trực tiếp ảnh desktop 1440x900 và mobile 390x844 sau sửa; logo mobile rõ trên nền sáng, form và nút không bị cắt trong ảnh.

## Phạm vi kết luận

Đạt các kiểm tra local nêu trên, đủ để bàn giao branch thay đổi login. Browser suite dùng mock API; chưa xác nhận đăng nhập backend live, tất cả role, hoặc mọi trạng thái 422/500 và keyboard/zoom bằng suite này. Assertion signup chỉ xác nhận payload frontend, không xác nhận tạo tài khoản thành công ở backend.

Branch feature cần được merge vào main theo quy trình release để Render đang theo main cập nhật. Version local do build sinh tại commit nguồn trước commit đóng gói; kiểm version từ build Render sau deployment, không gõ tay commit mới vào JSON.

Ảnh/log tại workspace cha: audit-prototype/login-redesign-independent/ và audit-prototype/login-redesign/independent-build.log, independent-test.log.
