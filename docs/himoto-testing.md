# HIMOTO Fleet Dashboard — Hướng Dẫn & Báo Cáo Kiểm Thử (Testing Guide)

## 1. Các Quy Trình Kiểm Thử Tự Động

### 1.1. Kiểm thử Contract API & Xử lý lỗi (Playwright)
Kịch bản: `audit-prototype/integration-review-round2/verify_contract.py`
Lệnh thực thi:
```bash
python audit-prototype/integration-review-round2/verify_contract.py
```
Các hạng mục kiểm tra:
1. **Tìm kiếm xe theo `name` & `keyword`**: Gửi đúng tham số, lọc chính xác theo model xe.
2. **Drawer thông tin chi tiết**: Focus trap bên trong drawer, phím tắt `Ctrl+K` không bị lộ ra nền, phím `Escape` đóng drawer và khôi phục focus chính xác về nút/ô tìm kiếm đã kích hoạt.
3. **Admin 403 Forbidden**: Khi nhận HTTP 403, giao diện hiển thị thẻ thông báo phân quyền rõ ràng, không hiển thị số liệu KPI giả.
4. **Role 4 Guard**: Người dùng Role 4 (Sale/CTV) tự động chuyển sang `/leads`, không gọi bất kỳ API quản trị nào (`/api/auth/dashboard/report`).
5. **Zero Page Errors**: Không phát sinh bất kỳ lỗi unhandled exception hoặc `PAGEERROR` trong console.

### 1.2. Kiểm thử Responsive & Điều hướng SPA
Kịch bản: `brain/.../scratch/verify_responsive_and_nav.py`
Các kích thước màn hình kiểm thử:
- **Mobile nhỏ (360x800)**: Không tràn ngang (`scrollWidth <= innerWidth`).
- **Mobile chuẩn (390x844)**: Thẻ xe, danh sách hợp đồng hiển thị responsive.
- **Tablet dọc (768x800)**: Bố cục lưới và menu co giãn hợp lý.
- **Tablet ngang (1024x800)**: Bố cục desktop linh hoạt.
- **Desktop (1440x900)**: Bố cục toàn diện tối ưu.

Kiểm tra điều hướng SPA:
- Chuyển trang qua lại giữa Dashboard, Xe, Khách hàng, Đơn thuê không reload trang.
- Trình duyệt Back / Forward duy trì chính xác URL và trạng thái bộ lọc.

## 2. Quy Trình Xây Dựng Bản Phân Phối (Production Build)

Khi triển khai hoặc đóng gói tĩnh:
```bash
# Cài đặt thư viện nếu cần
npm install

# Biên dịch gói sản phẩm tối ưu
npm run production

# Đồng bộ tài nguyên tĩnh sang static-dist
node scripts/build-static.js
```

Khởi chạy máy chủ phục vụ tĩnh cục bộ:
```bash
python serve_spa.py 8091
```
