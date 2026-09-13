# HIMOTO Fleet Dashboard — Kiến Trúc Tích Hợp Hệ Thống (Integration Architecture)

## 1. Cấu Trúc Shell & Điều Hướng SPA

Hệ thống HIMOTO Fleet Dashboard được triển khai dưới kiến trúc Single Page Application (SPA) với một Layout Shell duy nhất khởi tạo một lần (`resources/js/src/view/layout/Layout.vue`):

- **Sidebar Cố Định**: Menu trượt mượt mà, hỗ trợ thu gọn (collapsed 72px) và mở rộng (expanded 240px). Trên màn hình di động (< 992px), sidebar hoạt động dưới dạng overlay drawer có backdrop tự đóng khi chọn mục hoặc bấm ngoài.
- **Header Cố Định**: Giữ nguyên vị trí cuộn, chứa Store Selector (chọn chi nhánh), Search Box thông minh (hỗ trợ phím tắt `Ctrl+K`), nút "Tạo đơn nhanh", cụm thông báo và User Menu.
- **Không Reload Trang**: Tất cả chuyển hướng sử dụng `vue-router` (`<router-link>` hoặc `this.$router.push`), loại bỏ hoàn toàn `window.location.href` hay `location.reload()`.
- **Loại Bỏ Loader Toàn Màn Hình 2 Giây**: Xóa bỏ hoàn toàn cơ chế giả lập `setTimeout(..., 2000)` và loader che toàn màn hình. Khu vực nội dung trang sử dụng các component Skeleton trực quan (`HimotoPageSkeleton`, `HimotoTableSkeleton`, `HimotoCardSkeleton`).

## 2. Quản Lý Trạng Thái & Cache (State & Cache Layer)

Dữ liệu dùng chung được quản lý qua Vuex Store với cơ chế kiểm tra tính mới (TTL Cache) nhằm tối ưu hiệu năng và hạn chế gọi API trùng lặp:

- **Chi Nhánh (`store.module.js`)**: Cache 60 giây cho danh sách cửa hàng (`STORE_GET_ALL`).
- **Dashboard KPI (`dashboard.module.js`)**: Cache 30 giây cho báo cáo tổng quan (`DASHBOARD_GET_REPORT`), tách biệt theo `store_id`.
- **`<keep-alive>` View Caching**: 
  - Khởi tạo trong `Layout.vue`: `<keep-alive :include="cachedViews"><router-view :key="$route.name || $route.path" /></keep-alive>`.
  - Danh sách cache: `Dashboard`, `VehicleIndex`, `CustomerIndex`, `OrderCarRental`, `LeadIndex`, `ReportCardRental`, `ReportVehicleRevenue`.
  - Giữ nguyên bộ lọc tìm kiếm (`query.keyword`, `query.status`, `query.dates`), trang hiện tại (`page`), chế độ xem (`viewMode: table | grid`), và vị trí cuộn khi người dùng quay lại từ trang khác.

## 3. Hệ Thống Component Trạng Thái HIMOTO

- `HimotoPageSkeleton.vue`: Khung xương tải trang toàn phần (Header, KPI Cards, Bảng dữ liệu).
- `HimotoTableSkeleton.vue`: Khung xương bảng dữ liệu với số dòng/cột tùy biến và hiệu ứng shimmer nhẹ.
- `HimotoCardSkeleton.vue`: Khung xương cho chế độ xem thẻ lưới (Grid View).
- `HimotoEmptyState.vue`: Trạng thái rỗng chuẩn hóa với tiêu đề, mô tả và nút hành động (Call To Action).
- `HimotoErrorState.vue`: Trạng thái lỗi phân loại theo mã HTTP (401, 403, 422, 500, Network) kèm nút thử lại ("Thử lại").

## 4. Danh Sách Phân Quyền (Roles & Permissions)

1. **Role 1 (Admin)**: Toàn quyền truy cập tất cả chi nhánh, quản lý phương tiện, hợp đồng, tài chính, báo cáo, người dùng và cài đặt.
2. **Role 2/3 (Staff / Quản lý chi nhánh)**: Giới hạn dữ liệu theo chi nhánh được gán (`store_id`). Không thể chọn chi nhánh khác ngoài quyền hạn.
3. **Role 4 (Sale / CTV Lead)**:
   - Tự động chuyển hướng sang `/leads` khi truy cập hệ thống.
   - Ẩn toàn bộ menu quản trị hệ thống (Xe, Khách hàng, Đơn hàng, Tài chính, Cài đặt).
   - Chặn tuyệt đối việc gọi ngầm các API quản trị (`/api/auth/dashboard/report`, `/api/auth/order/car-rental`).
   - Nút "Tạo đơn" trên Header chuyển hướng trực tiếp tới form tạo Lead.
