# Review migration tại commit 7f01121

Các artifact trong thư mục này ghi nhận lỗi tái hiện trên **commit 7f01121**. Không dùng kết quả này làm xác nhận cho commit mới hơn.

- `api-evidence.json`: route, phạm vi dữ liệu, quyền tài khoản, đăng xuất và lỗi ghi kế toán; dùng các controller/service đã compile với database giả lập.
- `coverage-evidence.json`: độ phủ route và các ca health báo thành công sai.
- `ui-evidence.json`: lỗi giao diện khi API trả 503; trình duyệt cục bộ, dữ liệu giả lập, chặn request ngoài.
- Hai ảnh PNG: giao diện chốt két và đơn thuê xe trong điều kiện API lỗi.
- Các file `reproduce-*`: script tái hiện tương ứng. Đọc cấu hình trong script trước khi chạy; không dùng vào dịch vụ live với tài khoản nghiệp vụ.

Bản sửa quyền tài khoản Node sau đó có 8 test trong `apps/api/test/user-access.test.cjs`. Những thiếu sót migration khác vẫn cần kiểm chứng trước khi triển khai Next/Nest thay ứng dụng hiện tại.
