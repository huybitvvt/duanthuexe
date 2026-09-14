# HIMOTO Fleet Dashboard — Đặc Tả Hợp Đồng API (API Contracts & Adapters)

Tất cả các adapter frontend tuân thủ nguyên tắc: **Không thay đổi backend controller, không đổi schema database, giữ nguyên API Laravel chuẩn.**

## 1. Paginator Adapter (`normalizePaginator`)

Tệp: `resources/js/src/utils/paginatorAdapter.js`

Hàm `normalizePaginator(response)` chuẩn hóa đa hình các cấu trúc phản hồi từ Laravel và các endpoint tùy biến:

```javascript
// Hỗ trợ đồng thời 3 dạng response:
// 1. Laravel Paginator: response.data.data (mảng) + response.data.current_page + response.data.last_page
// 2. Custom Wrapper: response.data (mảng) + response.pagination.current_page + response.pagination.last_page
// 3. Flat Array: response.data (mảng)
```

Kết quả trả về thống nhất:
```json
{
  "items": [],
  "currentPage": 1,
  "lastPage": 1,
  "total": 0,
  "perPage": 10
}
```

## 2. Vehicle Model Adapter (`adaptVehicle`)

Chuẩn hóa các trường backend trả về theo quy ước:

| Trường Gốc (Backend) | Trường Chuẩn Hóa | Ý Nghĩa / Định Dạng |
|---|---|---|
| `license` | `license` / `license_plate` | Biển kiểm soát xe (ví dụ: `29B1-888.88`) |
| `odometer` | `odometer` / `total_km` | Số km đồng hồ (ODO) |
| `status` | `status` | `ready`, `repairing`, `using`, `renting`, `broken`, `sold` |
| `store.store_name` | `store_name` | Tên chi nhánh quản lý |
| `maintenanceVehicle` | `maintenanceVehicle` | Nhật ký bảo dưỡng |

Bản đồ trạng thái HIMOTO (`VEHICLE_STATUS_MAP`):
- `ready` / `1`: Sẵn sàng (`label-light-success`)
- `using` / `renting` / `2`: Đang thuê (`label-light-primary`)
- `repairing` / `3`: Đang sửa (`label-light-warning`)
- `broken` / `4`: Hỏng hóc (`label-light-danger`)

## 3. Quản Lý Lỗi Chuẩn Hóa (`apiErrorHandler`)

Tệp: `resources/js/src/utils/apiErrorHandler.js`

- `getApiStatus(error)`: Lấy mã HTTP status an toàn từ `error.response.status` hoặc fallback.
- `getApiMessage(error)`: Trích xuất thông báo lỗi thân thiện:
  - `401`: "Phiên làm việc đã hết hạn. Vui lòng đăng nhập lại."
  - `403`: "Bạn không có quyền truy cập vào tài nguyên hoặc chi nhánh này."
  - `404`: "Không tìm thấy dữ liệu yêu cầu."
  - `422`: Lỗi xác thực dữ liệu từ Laravel Validation (trích xuất lỗi chi tiết đầu tiên).
  - `429`: "Quá nhiều yêu cầu. Vui lòng thử lại sau giây lát."
  - `500`: "Lỗi máy chủ nội bộ. Vui lòng liên hệ quản trị viên."
  - Network Error: "Không thể kết nối đến máy chủ. Vui lòng kiểm tra đường truyền."
