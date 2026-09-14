# HIMOTO — Ma Trận Bàn Giao Tuyến Đường & Nghiệp Vụ (Release Matrix)

Tài liệu này đối chiếu chi tiết 100% các tuyến đường (routes), Vue component thực tế, quyền hạn, backend API và trạng thái nghiệm thu trên nhánh `feature/himoto-complete-integration` (Commit: `284abfa`).

---

## 1. Thông Tin Phiên Bản Bàn Giao

- **Tên dự án:** HIMOTO Fleet Dashboard (happyride-1.1)
- **Commit mốc:** `284abfafdd018665f15172b0952d61e01a06cfdf` (ngắn: `284abfa`)
- **Nhánh phát triển:** `feature/himoto-complete-integration`
- **Môi trường Staging API:** `https://himoto-api.onrender.com`
- **Cơ sở dữ liệu Staging/Prod:** Supabase PostgreSQL (`aws-0-ap-northeast-1.pooler.supabase.com`), schema `himoto`
- **Bộ mã bundle Frontend:** `public/js/app.js` đồng bộ `static-dist/js/app.js`

---

## 2. Ma Trận Chi Tiết Tuyến Đường (Route Matrix)

| STT | Route URL | Route Name | Vue Component | Quyền (Role) | API Backend Chính | Trạng Thái Giao Diện | Test Case Xác Minh |
|:---|:---|:---|:---|:---|:---|:---|:---|
| 1 | `/dashboard` | `dashboard` | `Dashboard.vue` | Admin (1), Store Mgr (2), Staff (3) | `/api/auth/dashboard/report`<br>`/api/auth/dashboard/report-chart`<br>`/api/auth/stores/all` | Chuẩn HIMOTO: KPI cards, charts, bảng phân bổ xe | `verify_spa_and_modules.py`<br>`test_staging_smoke.py` |
| 2 | `/vehicles` | `vehicle` | `VehicleIndex.vue` | Tất cả role có quyền xe | `/api/auth/vehicle/vehicles`<br>`/api/auth/stores/all` | Grid/Table view, bộ lọc trạng thái xe chuẩn (`using`, `ready`, `repairing`, `broken`), ODO, biển số | `verify_spa_and_modules.py`<br>`VehicleBusinessTest.php` |
| 3 | `/car-rental` | `car-rental` | `OrderCarRental.vue` | Quản trị, Nhân viên cửa hàng | `/api/auth/order/car-rental`<br>`/api/auth/stores/all` | Danh sách đơn thuê, lọc theo chi nhánh, tìm kiếm hợp đồng, chi tiết thanh toán, hoàn cọc | `verify_spa_and_modules.py`<br>`OrderBusinessTest.php` |
| 4 | `/customers` | `customers` | `CustomerIndex.vue` | Quản trị, Nhân viên | `/api/auth/customers`<br>`/api/auth/stores/all` | Danh sách khách hàng, tìm kiếm tên/SĐT/CCCD, cảnh báo blacklist | `verify_spa_and_modules.py`<br>`test_staging_smoke.py` |
| 5 | `/customers-create` | `customers-create` | `CustomerCreate.vue` | Quản trị, Nhân viên | `POST /api/auth/customers` | Form nhập thông tin khách: Họ tên, CCCD, GPLX, ảnh giấy tờ, địa chỉ | Manual / Component Audit |
| 6 | `/customers-update/:id` | `customers-update` | `CustomerUpdate.vue` | Quản trị, Nhân viên | `PUT /api/auth/customers/{id}` | Form cập nhật thông tin và lịch sử thuê xe của khách | Component Audit |
| 7 | `/leads` | `leads` | `LeadIndex.vue` | Sale / Role 4, Quản trị | `/api/auth/leads`<br>`/api/auth/leads/unique-users` | Danh sách lead, trạng thái chuyển đổi, phân công nhân sự phụ trách | `verify_spa_and_modules.py`<br>`test_staging_smoke.py` |
| 8 | `/maintenance-schedule` | `maintenance-schedule` | `MaintenanceSchedule.vue` | Quản trị, Kỹ thuật | `/api/auth/maintenance-schedules` | Lịch hẹn bảo dưỡng, nhắc kỳ thay dầu máy, kiểm định định kỳ | `verify_spa_and_modules.py`<br>`test_staging_smoke.py` |
| 9 | `/maintenance-log` | `maintenance-log` | `MaintenanceLog.vue` | Quản trị, Kỹ thuật | `/api/auth/maintenance-logs` | Nhật ký bảo dưỡng từng xe, lịch sử chi phí sửa chữa | Component Audit |
| 10 | `/maintenance-type` | `maintenance-type` | `MaintenanceType.vue` | Quản trị viên | `/api/auth/maintenance-types` | Danh mục các hạng mục bảo dưỡng định kỳ | Component Audit |
| 11 | `/maintenance-rule` | `maintenance-rule` | `MaintenanceRule.vue` | Quản trị viên | `/api/auth/maintenance-rules` | Cấu hình quy tắc chu kỳ bảo dưỡng theo km/ngày | Component Audit |
| 12 | `/banks` | `banks` | `BankIndex.vue` | Quản trị viên, Kế toán | `/api/auth/banks/all` | Danh sách tài khoản ngân hàng, số dư thực tế, quản lý tài khoản | `verify_spa_and_modules.py`<br>`test_staging_smoke.py` |
| 13 | `/banks-create` | `banks-create` | `BankCreate.vue` | Quản trị viên | `POST /api/auth/banks` | Form mở tài khoản ngân hàng chi nhánh | Component Audit |
| 14 | `/banks-update/:id` | `banks-update` | `BankUpdate.vue` | Quản trị viên | `PUT /api/auth/banks/{id}` | Cập nhật thông tin ngân hàng | Component Audit |
| 15 | `/cash` | `cash` | `CashIndex.vue` | Quản trị viên, Thủ quỹ | `/api/auth/cash/all` | Sổ quỹ tiền mặt từng chi nhánh, số dư đầu kỳ/cuối kỳ | `verify_spa_and_modules.py`<br>`test_staging_smoke.py` |
| 16 | `/cash-create` | `cash-create` | `CashCreate.vue` | Quản trị viên | `POST /api/auth/cash` | Khởi tạo quỹ tiền mặt chi nhánh mới | Component Audit |
| 17 | `/cash-update/:id` | `cash-update` | `CashUpdate.vue` | Quản trị viên | `PUT /api/auth/cash/{id}` | Cập nhật quỹ tiền mặt chi nhánh | Component Audit |
| 18 | `/transactions` | `transactions` | `Transaction.vue` | Quản trị, Kế toán | `/api/auth/transactions` | Lịch sử giao dịch thu/chi tổng thể của hệ thống | Component Audit |
| 19 | `/receipt` | `receipt` | `ReceiptIndex.vue` | Quản trị, Kế toán | `/api/auth/receipts` | Phiếu thu/phiếu chi tiền mặt và chuyển khoản | Component Audit |
| 20 | `/receipt/create` | `receipt-create` | `ReceiptCreate.vue` | Quản trị, Kế toán | `POST /api/auth/receipts` | Lập phiếu thu cọc, thu phí thuê, chi trả cọc | Component Audit |
| 21 | `/stores` | `stores` | `StoreIndex.vue` | Quản trị viên | `/api/auth/stores/all` | Danh sách các cửa hàng/chi nhánh trên toàn quốc | `test_staging_smoke.py` |
| 22 | `/store-create` | `stores-create` | `StoreCreate.vue` | Quản trị viên | `POST /api/auth/stores` | Tạo chi nhánh mới kèm thông tin liên hệ | Component Audit |
| 23 | `/store-update/:id` | `stores-update` | `StoreUpdate.vue` | Quản trị viên | `PUT /api/auth/stores/{id}` | Cập nhật địa chỉ, hotline, quản lý chi nhánh | Component Audit |
| 24 | `/pricing` | `pricing` | `PriceIndex.vue` | Quản trị viên | `/api/auth/price-vehicles` | Bảng giá thuê xe theo ngày/tuần/tháng theo từng dòng xe | Component Audit |
| 25 | `/user` | `user` | `UserIndex.vue` | Quản trị viên | `/api/auth/users`<br>`/api/auth/roles` | Danh sách tài khoản người dùng, phân quyền vai trò (Role 1-4) | Component Audit |
| 26 | `/car-sell` | `car-sell` | `OrderSellIndex.vue` | Quản trị viên | `/api/auth/sell-orders` | Quản lý đơn bán thanh lý xe | Component Audit |
| 27 | `/report/detail-report` | `report-car-rental` | `ReportCardRental.vue` | Quản trị, Kế toán | `/api/auth/order/car-rental/report` | Báo cáo chi tiết doanh thu đơn thuê | Component Audit |
| 28 | `/report/vehicle-revenue` | `report-vehicle-revenue` | `ReportVehicleRevenue.vue` | Quản trị, Kế toán | `/api/auth/report/vehicle-revenue` | Báo cáo hiệu suất doanh thu theo từng xe | Component Audit |
| 29 | `/login` | `login` | `Auth.vue` | Khách / Chưa đăng nhập | `POST /api/auth/login` | Màn hình đăng nhập hệ thống, hỗ trợ ghi nhớ phiên | `test_staging_smoke.py` |
| 30 | `/404` | `404` | `Error-1.vue` | Tất cả | Không có | Màn hình thông báo không tìm thấy trang | Component Audit |

---

## 3. Các Tuyến Đường Chuyển Hướng (Redirects)
- `/` → Chuyển hướng sang `/dashboard` (hoặc `/leads` nếu là Sale/Role 4).
- `/orders` → Chuyển hướng sang `/car-rental`.
- `/roles` → Chuyển hướng sang `/user`.
- `*` (Wildcard) → Chuyển hướng sang `/404`.
