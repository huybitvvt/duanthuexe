# HIMOTO Fleet Dashboard — Biên Bản Nghiệm Thu & Đánh Giá Sẵn Sàng Vận Hành (Release Acceptance)

Tài liệu này xác nhận kết quả kiểm thử và đánh giá mức độ sẵn sàng vận hành của sản phẩm HIMOTO Fleet Dashboard trên nhánh `feature/himoto-complete-integration` theo các tiêu chí tại [docs/himoto-go-live-remaining.md](file:///E:/duanthuexe/happyride-1.1/docs/himoto-go-live-remaining.md).

---

## 1. Thông Tin Phiên Bản & Môi Trường Bàn Giao

- **Kho mã nguồn:** `happyride-1.1`
- **Nhánh phát triển:** `feature/himoto-complete-integration`
- **Mốc commit nguồn:** Đang phát triển trên `feature/himoto-complete-integration` (kế thừa từ `fce6a60` kèm các bản vá hoàn thiện test, tính toán nghiệp vụ & cách ly cache)
- **Tự động sinh metadata phiên bản:** Build script tự động trích xuất Git commit hash & build timestamp vào `version.json` (tại `resources/js/src/version.json`, `public/version.json`, `static-dist/version.json`)
- **Môi trường Backend:** Laravel 5.8, PHP 7.4.33
- **Cơ sở dữ liệu:** PostgreSQL trên Supabase (Session Pooler: `aws-0-ap-northeast-1.pooler.supabase.com:5432`, schema `himoto`, 122/122 migrations trạng thái `Yes`)
- **API Staging đang hoạt động:** `https://himoto-api.onrender.com`
- **Đồng bộ mã tĩnh Frontend:** `public/js/app.js` và `static-dist/js/app.js`

---

## 2. Bảng Tổng Hợp Kết Quả Nghiệm Thu Kỹ Thuật

| Nhóm Kiểm Thử | Công Cụ & Kịch Bản | Tiêu Chí Đo Lường | Kết Quả Thực Tế | Trạng Thái Hiệu Chuẩn |
|:---|:---|:---|:---:|:---:|
| **1. SPA Navigation** | `tests/verify_spa_and_modules.py` | Chuyển 7 menu không tải lại tài liệu, giữ nguyên shell ID, 0 document request, Back/Forward giữ shell | **0 extra document requests**<br>Shell ID giữ 100% | **ĐẠT (PASS)** |
| **2. Module Render** | `tests/verify_spa_and_modules.py` | 6 module nghiệp vụ (Customer, Order, Lead, Maintenance, Cash, Bank) hiển thị đúng dữ liệu | 6/6 modules render chính xác | **ĐẠT (PASS)** |
| **3. Cache TTL** | `tests/verify_spa_and_modules.py` | Giữ cache trong TTL 60s, không gửi request trùng lặp khi đổi tab qua lại | Request count: 2 → 2 (không tăng) | **ĐẠT (PASS)** |
| **4. Cách Ly Cache Đổi Tài Khoản** | `tests/test_user_switching_cache.py` | Luồng User A (`1:all`) → Logout (`PURGE_AUTH`) xóa sạch cache → User B (`2:2`) đăng nhập context cùng browser nhận dữ liệu mới, không rò rỉ; late response từ User A bị huỷ bỏ | **Strict Assertions PASS**<br>Key 1:all bị xoá sạch<br>Key 2:all được tạo mới<br>Late response không ghi cache | **ĐẠT (PASS)** |
| **5. Xử Lý Lỗi 500** | `tests/verify_spa_and_modules.py` | Khi máy chủ lỗi HTTP 500, hiển thị Error State có nút thử lại; bấm thử lại khôi phục được dữ liệu; 0 lỗi unhandled rejection | Khôi phục thành công<br>`page_errors = []` | **ĐẠT (PASS)** |
| **6. Responsive Viewport** | `tests/verify_spa_and_modules.py` | Kiểm tra trên 5 kích thước màn hình: 360, 390, 768, 1024, 1440px | 0 lỗi horizontal overflow | **ĐẠT (PASS)** |
| **7. Tính Toán Nghiệp Vụ & Hợp Đồng API (PHPUnit)** | `.\php.cmd artisan test` | 18 tests bao gồm: tính phụ thu trễ hạn (xeso/xega/xecon/sh theo các mốc <8h và >=8h cả ngày), tính cọc, hoàn tiền cọc, quy tắc giá cố định handler_price, phân quyền chi nhánh (Role 1 vs Role 2) và API contract | **18/18 tests PASS**<br>82 assertions OK | **ĐẠT KIỂM THỬ ĐƠN VỊ**<br>*(Chưa xác minh mutation ghi/xoá trên live DB)* |
| **8. Live Staging Smoke** | `tests/test_staging_smoke.py` | Kết nối backend Render thật, đăng nhập tài khoản quản trị thật, kiểm tra 8 nhóm API nghiệp vụ đọc dữ liệu | **13/13 endpoints PASS** | **ĐẠT KHẢO SÁT ĐỌC (READ-ONLY)** |

> [!IMPORTANT]
> **Giới hạn phạm vi nghiệm thu kỹ thuật:** Các bài test PHPUnit và Live Smoke xác nhận tính toàn vẹn của logic tính toán và khả năng phản hồi của hệ thống. Các thao tác ghi đè dữ liệu kinh doanh thật (tạo đơn thật, trừ tiền thật, xóa xe) chưa được thực thi trên database Supabase live để bảo vệ dữ liệu sản xuất.

---

## 3. Ma Trận Đối Chiếu API Endpoints

Toàn bộ endpoint trong ma trận bàn giao đã được chuẩn hóa đồng nhất với router backend (`routes/api.php`):
- Nhật ký bảo dưỡng: `/api/auth/maintenance-log` (dạng số ít theo router).
- Phiếu thu: `/api/auth/receipt` (dạng số ít theo router).
- Bảng giá xe: `/api/auth/priceVehicles` (dạng camelCase theo router).
- Cập nhật ngân hàng / quỹ: `PUT /api/auth/banks/update/{bank}` và `PUT /api/auth/cash/update/{cash}`.

---

## 4. Kế Hoạch Chạy Thử Nghiệm Có Kiểm Soát (Pilot Rollout) & Đối Soát Vận Hành

Theo chỉ đạo tại mục 5 của `docs/himoto-go-live-remaining.md`, hệ thống sẽ tuân thủ lộ trình phát hành từng bước:

### 4.1. Phạm vi chạy thử (Pilot Scope)
- **Địa điểm áp dụng:** 01 chi nhánh thí điểm (ví dụ: Chi nhánh Đống Đa hoặc Chi nhánh Cầu Giấy).
- **Thời gian thử nghiệm:** 03 ngày làm việc liên tục.
- **Nhóm nhân sự tham gia:** 01 Cửa hàng trưởng và 02 Nhân viên trực ca được cấp tài khoản Role 2 & Role 3.

### 4.2. Quy trình đối soát cuối ca (End-of-Shift Reconciliation)
Cuối mỗi ca làm việc (12h00 và 21h00), Quản lý chi nhánh thực hiện đối chiếu chéo:
1. **Trạng thái xe:** Tổng số xe tại bãi = Xe sẵn sàng (`ready`) + Xe đang bảo dưỡng (`repairing`). Số xe đang chạy ngoài = Xe đang thuê (`using`).
2. **Tiền cọc & Phí thuê:** Khớp nối giữa hợp đồng ký kết với số dư quỹ tiền mặt (`cash`) và biến động tài khoản ngân hàng (`bank`).
3. **Phụ thu & Hoàn cọc:** Kiểm tra các trường hợp trả xe trễ hoặc trả xe sớm có phụ thu / hoàn trả cọc đúng công thức đã được kiểm thử.

### 4.3. Kế hoạch Backup & Khôi Phục Dự Phòng (Disaster Recovery & Rollback)
- **Sao lưu trước phát hành:** Tạo snapshot full backup trên Supabase Dashboard trước khi triển khai chính thức.
- **Rollback Frontend:** Nếu phát hiện lỗi giao diện chặn luồng thao tác, sử dụng tính năng Rollback to Previous Deploy trên Render Dashboard về bản build ổn định trước đó trong vòng 2 phút.
- **Bảo toàn dữ liệu Database:** Nghiêm cấm chạy `migrate:rollback`, `migrate:fresh` hoặc xoá schema trên môi trường vận hành. Nếu có sự cố backend, chỉ rollback mã nguồn app qua Render Dashboard.
- **Đầu mối chịu trách nhiệm kỹ thuật:** Trực ca kỹ thuật giám sát error log Render và tài nguyên Supabase qua dashboard trong suốt giai đoạn Pilot.

---

## 5. Kết Luận Đánh Giá
Sản phẩm HIMOTO Fleet Dashboard đã **hoàn tất toàn bộ các bài test kỹ thuật tự động (18 PHPUnit tests, kiểm thử cách ly cache A→B, kiểm thử SPA không reload, responsive 5 viewports)**. Hệ thống đã đủ điều kiện kỹ thuật để **chuyển sang giai đoạn Chạy thử nghiệm có kiểm soát (Pilot Rollout) tại 01 chi nhánh có đối soát**, trước khi xem xét mở rộng vận hành toàn diện.
