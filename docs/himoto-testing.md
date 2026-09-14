# HIMOTO Fleet Dashboard — Hướng Dẫn & Báo Cáo Kiểm Thử (Testing Guide)

## 1. Môi Trường & Phạm Vi Kiểm Thử (Environment & Scope)

### 1.1. Hiện trạng Môi Trường Cục Bộ (Local Environment)
- **Môi trường Frontend**: Node.js v20+, Webpack / Laravel Mix, static server Python threaded (`serve_spa.py 8091`).
- **PHP CLI & Database Cục bộ**: Máy trạm Windows phát triển hiện **chưa cài đặt PHP CLI trên biến môi trường PATH** (`where.exe php` không tìm thấy và Docker service đang dừng).
- **Phạm vi kiểm thử tự động cục bộ**: Toàn bộ kiểm thử được thực thi tự động qua Playwright trên gói bundle Production thực tế (`public/` và `static-dist/`), kiểm tra tính toàn vẹn của Single Page Application (SPA), Vue Router HTML5 History, API Contract Adapters, Cache TTL, Resilience khi gặp lỗi mạng/500, và Responsive viewports.

### 1.2. Hướng Dẫn Nghiệm Thu Backend / Staging Server (Production Acceptance Gate)

Để hoàn tất nghiệm thu toàn diện từ mức **Staging** sang **Production Ready**, cần thực hiện kiểm tra backend, database và API trực tiếp theo các kịch bản dưới đây:

#### Kịch bản A: Thực thi trực tiếp trên máy chủ Staging / Render Shell (Khuyến nghị)
Khi container backend `himoto-api` đã khởi chạy trên Render hoặc máy chủ Staging:
```bash
# 1. Kiểm tra danh sách route API đã đăng ký đầy đủ
php artisan route:list --path=api

# 2. Chạy bộ unit / feature test của Laravel
php artisan test

# 3. Kiểm tra trạng thái cơ sở dữ liệu PostgreSQL / Supabase
php artisan migrate:status
```

#### Kịch bản B: Thực thi cục bộ qua Docker CLI (Nếu khởi động Docker Desktop)
Nếu máy trạm Windows khởi động Docker Desktop:
```powershell
# Chạy route list qua container PHP 7.4
docker run --rm -v "E:\duanthuexe\happyride-1.1:/var/www/html" -w /var/www/html php:7.4-cli php artisan route:list --path=api

# Chạy test qua container
docker run --rm -v "E:\duanthuexe\happyride-1.1:/var/www/html" -w /var/www/html php:7.4-cli php artisan test

# Chạy migrate status (khi kết nối database Supabase / Docker MySQL)
docker run --rm --env-file .env -v "E:\duanthuexe\happyride-1.1:/var/www/html" -w /var/www/html php:7.4-cli php artisan migrate:status
```

#### Kịch bản C: Kiểm thử Tự Động Toàn Diện Live Staging API (Smoke Test Suite)
Sau khi backend staging hoạt động, chạy script kiểm thử tự động trực tiếp từ repo:
```bash
python tests/test_staging_smoke.py --base-url https://<backend-staging-url> --email admin@himoto.vn --password <password>
```
Kịch bản này tự động:
1. Kiểm tra `/api/health` và kết nối database thực tế (`status: ok, database: ok`).
2. Gửi request `POST /api/auth/login`, nhận JWT Bearer Token.
3. Xác minh `/api/verify-token` và quyền tài khoản.
4. Kiểm tra dữ liệu thực tế và hình dạng paginator trên 8 endpoint chính: `/api/auth/stores/all`, `/api/auth/vehicle/vehicles`, `/api/auth/customers`, `/api/auth/order/car-rental`, `/api/auth/leads`, `/api/auth/maintenance-schedules`, `/api/auth/banks/all`, `/api/auth/cash/all`, `/api/auth/dashboard/report`.
5. Đánh giá thời gian phản hồi (latency ms) và đảm bảo 0 lỗi runtime.


---

## 2. Các Bộ Kịch Bản Kiểm Thử Tự Động Trong Repository

Tất cả các kịch bản kiểm thử đều được lưu trữ trực tiếp trong repository tại thư mục `audit-prototype/integration-review-round2/`:

### 2.1. Kịch bản 1: Kiểm thử Toàn Diện SPA & Nghiệp Vụ Các Module
- **Đường dẫn**: `audit-prototype/integration-review-round2/verify_spa_and_modules.py`
- **Lệnh thực thi**:
  ```bash
  python audit-prototype/integration-review-round2/verify_spa_and_modules.py
  ```
- **Hạng mục kiểm tra chi tiết**:
  1. **Chuyển menu không reload trang (True SPA Transition)**:
     - Gán token định danh `window.__himoto_shell_id = 'himoto-shell-token-...'` trên trang gốc `/dashboard`.
     - Click thực tế các liên kết sidebar: Đơn hàng (`/car-rental`), Đội xe (`/vehicles`), Khách hàng (`/customers`), Lead (`/leads`), Bảo dưỡng (`/maintenance-schedule`), Tiền mặt (`/cash`), Ngân hàng (`/banks`).
     - Đo lường số lượt request tài liệu HTML (`request.resource_type == 'document'`): **0 lượt request mới** phát sinh khi chuyển menu.
     - Kiểm tra `window.__himoto_shell_id` giữ nguyên giá trị qua toàn bộ quá trình điều hướng và thao tác Back / Forward lịch sử trình duyệt.
  2. **Kiểm tra chuẩn xác các Endpoint API Backend**:
     - Cửa hàng: `GET /api/auth/stores/all` (không còn gọi endpoint sai `/store/all`).
     - Lead: `GET /api/auth/leads` và `GET /api/auth/leads/unique-users` (không còn gọi `/lead`).
     - Khách hàng: `GET /api/auth/customers`.
     - Đơn hàng: `GET /api/auth/order/car-rental` và các endpoint báo cáo.
     - Đội xe: `GET /api/auth/vehicle/vehicles`.
     - Bảo dưỡng: `GET /api/auth/maintenance-schedules`.
     - Tiền mặt & Ngân hàng: `GET /api/auth/cash`, `GET /api/auth/banks`.
  3. **Nghiệp vụ các Module (Customer, Order, Lead, Maintenance, Cash, Bank)**:
     - Hiển thị danh sách bảng qua adapter paginator chuẩn hóa (`normalizePaginator`).
     - Khách hàng: Hiển thị đúng họ tên, SĐT, định dạng số CCCD/CMTND.
     - Đơn hàng: Hiển thị đúng trạng thái đơn, nhãn hợp đồng, số tiền tổng.
     - Lead: Hiển thị nguồn lead, thông tin liên hệ, màu nhãn trạng thái.
     - Bảo dưỡng: Hiển thị lịch hẹn bảo dưỡng, thông tin phương tiện.
     - Tiền mặt & Ngân hàng: Hiển thị số dư hiện tại theo định dạng tiền tệ VNĐ chuẩn (`15.000.000đ`).
  4. **Kiểm tra Cache TTL & Không phát sinh Request thừa (Keep-Alive)**:
     - Chuyển trang từ Đơn hàng sang Đội xe rồi quay lại Đơn hàng trong khoảng thời gian < 60 giây.
     - Số lượng request `/api/auth/order/car-rental` giữ nguyên (không tăng), dữ liệu được giữ nguyên vẹn từ bộ nhớ đệm `<keep-alive>`.
  5. **Xử lý trạng thái lỗi (HTTP 500) & Khả năng phục hồi (Error State Resilience)**:
     - Giả lập phản hồi 500 khi tìm kiếm khách hàng: Giao diện hiển thị `HimotoErrorState` với thông điệp lỗi rõ ràng và nút "Tải lại trang".
     - Bấm nút thử lại: Hệ thống tự động phục hồi và hiển thị lại dữ liệu bảng bình thường.
     - Kiểm tra toàn bộ console trình duyệt: **0 lỗi unhandled rejection / zero page_errors**.
  6. **Responsive trên 5 Viewport**:
     - Kiểm tra các kích thước: 360px, 390px, 768px, 1024px, 1440px.
     - Kết quả: `scrollWidth <= innerWidth` trên toàn bộ 5 viewport, không có thanh cuộn ngang gây vỡ khung.

### 2.2. Kịch bản 2: Kiểm thử Contract API, Focus Trap & Phân Quyền
- **Đường dẫn**: `audit-prototype/integration-review-round2/verify_contract.py`
- **Lệnh thực thi**:
  ```bash
  python audit-prototype/integration-review-round2/verify_contract.py
  ```
- **Hạng mục kiểm tra**:
  1. Tìm kiếm xe theo tham số `name` và `keyword`, hiển thị đúng dòng xe `Honda Vision` với trạng thái `Đang sửa`, ODO `12500 km`.
  2. Slide Drawer giữ focus nội bộ; phím tắt `Ctrl+K` không lọt focus ra ngoài; phím `Escape` đóng drawer và trả focus về ô tìm kiếm.
  3. Xử lý HTTP 403 Forbidden: Hiển thị thẻ thông báo phân quyền, không để sót KPI giả.
  4. Phân quyền Role 4: Tự động chuyển hướng về `/leads`, không gọi API Dashboard quản trị.
  5. Zero page errors: 0 lỗi console/runtime.

---

## 3. Quy Trình Xây Dựng Bản Phân Phối (Production Build)

Khi triển khai hoặc đồng bộ tài nguyên:
```bash
# 1. Cài đặt các gói phụ thuộc (nếu chưa cài)
npm install

# 2. Biên dịch gói sản phẩm tối ưu (Production Webpack bundle)
npm run production

# 3. Đồng bộ tài nguyên tĩnh sang static-dist
node scripts/build-static.js
```

Khởi chạy máy chủ phục vụ tĩnh cục bộ:
```bash
python serve_spa.py 8091
```

---

## 4. Bảng Tổng Hợp Kết Quả Kiểm Thử Mới Nhất (14/09/2026)

| Hạng Mục Kiểm Thử | Trạng Thái | Ghi Chú |
|:---|:---:|:---|
| SPA No-Reload Menu Transitions | **PASS (7/7)** | 0 lượt document request phát sinh; shell ID duy trì 100% |
| Back / Forward Browser Navigation | **PASS** | Duy trì router state và shell ID không reload |
| Chuẩn hóa Endpoint API Backend | **PASS** | Đã sửa `/api/auth/stores/all`, `/api/auth/leads` |
| Nghiệp vụ Customer | **PASS** | Paginator adapter, tìm kiếm, hiển thị đúng thông tin |
| Nghiệp vụ Order | **PASS** | Paginator adapter, trạng thái hợp đồng, tổng tiền |
| Nghiệp vụ Lead | **PASS** | Badge trạng thái, thông tin liên hệ, nguồn lead |
| Nghiệp vụ Maintenance | **PASS** | Lịch bảo dưỡng, thông tin xe, hạn xử lý |
| Nghiệp vụ Cash & Bank | **PASS** | Số dư tài khoản, chi nhánh, định dạng giá VNĐ chuẩn |
| Cache TTL 60s & Keep-Alive | **PASS** | Không gửi request trùng lặp khi chuyển tab trong 60s |
| Xử lý HTTP 500 Error State | **PASS** | Hiển thị HimotoErrorState, nút thử lại phục hồi thành công |
| Bắt lỗi API phụ (Missing .catch) | **PASS** | Đã bọc `.catch()` và xử lý đầy đủ các API phụ |
| Zero Page Errors | **PASS** | 0 unhandled promise rejections, 0 console exceptions |
| Responsive 5 Viewports (360 - 1440px) | **PASS** | Không tràn ngang trên bất kỳ độ phân giải nào |
