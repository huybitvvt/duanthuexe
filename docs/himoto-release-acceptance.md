# HIMOTO Fleet Dashboard — Biên Bản Nghiệm Thu & Sẵn Sàng Phát Hành (Release Acceptance)

Tài liệu này xác nhận kết quả nghiệm thu toàn diện sản phẩm HIMOTO Fleet Dashboard trên nhánh `feature/himoto-complete-integration` theo đúng các giai đoạn từ A đến G quy định tại [docs/himoto-completion-plan.md](file:///E:/duanthuexe/happyride-1.1/docs/himoto-completion-plan.md).

---

## 1. Thông Tin Phiên Bản & Môi Trường Bàn Giao

- **Kho mã nguồn:** `happyride-1.1`
- **Nhánh phát triển:** `feature/himoto-complete-integration`
- **Mốc commit nghiệm thu:** `284abfa` (kèm các bản vá hoàn thiện test & cache)
- **Môi trường Backend:** Laravel 5.8, PHP 7.4.33
- **Cơ sở dữ liệu:** PostgreSQL trên Supabase (Session Pooler: `aws-0-ap-northeast-1.pooler.supabase.com:5432`, schema `himoto`, 122/122 migrations trạng thái `Yes`)
- **API Staging đang hoạt động:** `https://himoto-api.onrender.com`
- **Đồng bộ mã tĩnh Frontend:** `public/js/app.js` và `static-dist/js/app.js` (Khớp mã SHA-256: `A5F7831854319B6CE47E839E4002DF1F0876D9AFBC3C53FDACA4B0DD11173509`)

---

## 2. Bảng Tổng Hợp Kết Quả Nghiệm Thu Các Bộ Test

| Nhóm Kiểm Thử | Công Cụ & Kịch Bản | Tiêu Chí Đo Lường | Kết Quả Thực Tế | Trạng Thái |
|:---|:---|:---|:---:|:---:|
| **1. SPA Navigation** | `tests/verify_spa_and_modules.py` | Chuyển 7 menu không tải lại tài liệu, giữ nguyên shell ID, 0 document request, Back/Forward giữ shell | **0 extra document requests**<br>Token shell giữ 100% | **ĐẠT (PASS)** |
| **2. Module Render** | `tests/verify_spa_and_modules.py` | 6 module nghiệp vụ (Customer, Order, Lead, Maintenance, Cash, Bank) hiển thị đúng dữ liệu | 6/6 modules render chính xác | **ĐẠT (PASS)** |
| **3. Cache TTL** | `tests/verify_spa_and_modules.py` | Giữ cache trong TTL 60s, không gửi request trùng lặp khi đổi tab qua lại | Request count: 2 → 2 (không tăng) | **ĐẠT (PASS)** |
| **4. Xóa Cache Logout** | `tests/test_user_switching_cache.py` | Đăng xuất (`PURGE_AUTH`) xóa sạch cache Dashboard; phân định cache theo User ID | `reportCacheKeys: []`<br>`chartCacheKeys: []` | **ĐẠT (PASS)** |
| **5. Xử Lý Lỗi 500** | `tests/verify_spa_and_modules.py` | Khi máy chủ lỗi HTTP 500, hiển thị Error State có nút thử lại; bấm thử lại khôi phục được dữ liệu; 0 lỗi unhandled rejection | Khôi phục thành công<br>`page_errors = []` | **ĐẠT (PASS)** |
| **6. Responsive Viewport** | `tests/verify_spa_and_modules.py` | Kiểm tra trên 5 kích thước màn hình: 360, 390, 768, 1024, 1440px | 0 lỗi horizontal overflow | **ĐẠT (PASS)** |
| **7. Backend PHPUnit** | `.\php.cmd artisan test` | Thay 2 test mẫu bằng 12 test nghiệp vụ Model Vehicle, Order và bảo vệ API Contract | **12/12 tests PASS**<br>65 assertions OK | **ĐẠT (PASS)** |
| **8. Live Staging Smoke** | `tests/test_staging_smoke.py` | Kết nối backend Render thật, đăng nhập tài khoản quản trị thật, kiểm tra 8 nhóm API nghiệp vụ | **13/13 endpoints PASS** | **ĐẠT (PASS)** |

---

## 3. Quy Trình Triển Khai (Deployment Runbook)

### 3.1. Các bước triển khai lên Render
1. Duyệt và gộp (merge) nhánh `feature/himoto-complete-integration` vào nhánh triển khai chính (`main`).
2. **Backend API (`himoto-api`):**
   - Render tự động kích hoạt Build theo cấu hình `Dockerfile` / Blueprint.
   - Xác nhận biến môi trường DB kết nối Supabase Pooler (`DB_HOST`, `DB_PORT=5432`, `DB_DATABASE=postgres`, `DB_SCHEMA=himoto`, `DB_SSLMODE=require`).
   - Kiểm tra endpoint sau deploy: `GET https://himoto-api.onrender.com/api/health` trả về `{"status":"ok","database":"ok"}`.
3. **Frontend Web (`himoto-web`):**
   - Build static dist: `npm run production && node scripts/build-static.js`.
   - Cấu hình Rewrite rule trên Render Static Site: Mọi request định tuyến về `/index.html` (SPA fallback).

### 3.2. Quy trình Rollback dự phòng
- Nếu phát sinh sự cố sau khi phát hành frontend:
  1. Sử dụng tính năng "Rollback to previous deploy" trực tiếp trong Render Dashboard.
  2. Hoặc checkout lại commit trước đó trên git (`284abfa`) và chạy lại lệnh build static.
- **Lưu ý cơ sở dữ liệu:** Không thực hiện `migrate:rollback` trên database Supabase khi không có phương án sao lưu trước.

---

## 4. Kết Luận Nghiệm Thu
Toàn bộ các tiêu chí kỹ thuật, giao diện SPA, cơ chế cache theo người dùng, độ tin cậy của bộ test, và kiểm thử nghiệp vụ backend trên nhánh `feature/himoto-complete-integration` đã **hoàn thành 100% và đạt yêu cầu phát hành**.
