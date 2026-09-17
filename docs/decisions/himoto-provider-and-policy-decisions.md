# HIMOTO — Dự thảo quyết định Chính sách, Nhà cung cấp & Phân định Môi trường

Ngày soạn dự thảo: 17/09/2026  
Baseline: `a1a20bc` trên nhánh `main`  
Tài liệu căn cứ: `docs/himoto-remaining-completion-plan-20260917.md`

> Các trạng thái dưới đây là đề xuất kỹ thuật, chưa phải chữ ký/phê duyệt của chủ hệ thống. Không được dùng tài liệu này để bật provider live, chạy migration production hoặc thay đổi quyền người dùng thật.

---

## 1. Trạng thái Quyết định Phê duyệt (Decision Matrix)

| Nhóm nghiệp vụ | Hạng mục | Quyết định chính sách | Trạng thái kỹ thuật | Ghi chú & Ràng buộc an toàn |
|---|---|---|:---:|---|
| **Bảo mật & Môi trường** | Mật khẩu & Secrets | Toàn bộ secret cũ được gỡ bỏ; chỉ đọc biến môi trường từ hệ thống hosting/Render. | **PENDING_OWNER_ACTION** | Credential đã từng xuất hiện cần được rotate; cấm commit file `.env` hoặc secret vào git. |
| **Bảo mật & Môi trường** | Database Staging | Tách riêng biệt staging database (`HIMOTO_UAT_DATABASE=true`) với production. | **PENDING_OWNER_ACTION** | Cần xác nhận project staging; cấm chạy lệnh phá hủy (`migrate:fresh`, `truncate`) trên database vận hành thật. |
| **Phân quyền (RBAC)** | Ma trận 6 vai trò | Tách biệt quyền: `quan-tri-vien`, `ban-giam-doc`, `ke-toan`, `nhan-su`, `quan-ly-cua-hang`, `nhan-vien`. | **PROPOSED** | Chờ chủ hệ thống duyệt danh sách người dùng, phạm vi công ty/cơ sở và quyền từng thao tác. |
| **Kiểm toán (Audit)** | Nhật ký `audit_events` | Bắt buộc ghi audit mọi giao dịch tài chính, nhân sự, chuyển quyền, đóng kỳ, cấu hình. | **PROPOSED** | Schema/code là đề xuất; cần duyệt retention, người được xem và cách xử lý PII. |
| **Thuê sở hữu** | Chuyển quyền sở hữu | Điều kiện chuyển quyền: Hợp đồng đã tất toán, dư nợ thực tế = 0, đủ hồ sơ, maker-checker. | **BLOCKED_OWNER_POLICY** | Chờ duyệt điều kiện, checklist, cấp duyệt, ngày hiệu lực và quy tắc hoàn tác. |
| **Thuê sở hữu** | PDF Hợp đồng & Công nợ | Xuất PDF từ snapshot hợp đồng/công nợ đã ký; hiển thị logo Himoto, font Unicode tiếng Việt. | **PROPOSED** | Cần duyệt mẫu PDF, trường pháp lý, logo/font và cách ký/lưu trữ; chưa coi PDF prototype là bản pháp lý. |
| **Nhắc nợ tự động** | SMS / Zalo Provider | Chạy Outbox Worker qua `ReminderProviderInterface`; hỗ trợ Sandbox adapter. | **APPROVED_SANDBOX** | Mặc định `REMINDER_LIVE_ENABLED=false`. Chỉ gửi thật khi có whitelist & duyệt template ZNS. |
| **Định vị GPS** | GPS Fleet Tracking | Chạy qua `GpsProviderInterface` với các model `gps_devices`, `gps_positions`, `gps_alerts`. | **APPROVED_SANDBOX** | Khung adapter & sandbox vị trí sẵn sàng; chờ thông tin API phần cứng thật từ đối tác để bật live. |
| **Kế toán chuẩn mực** | Sổ kép & Kỳ kế toán | Bút toán kép: Tổng Nợ (Debit) = Tổng Có (Credit); khóa kỳ kế toán; sửa sai bằng bút toán đối ứng. | **PROPOSED** | Cần kế toán duyệt chart of accounts, posting rules, ngày bắt đầu và số dư đầu kỳ trước khi post/backfill. |

---

## 2. Đề xuất Phân quyền & Giới hạn Thao tác (chờ duyệt)

1. **Ban Giám Đốc (`ban-giam-doc`)**:
   - Quyền: Xem toàn bộ KPI công ty, xem báo cáo doanh thu các cơ sở, xem bảng cân đối kế toán, duyệt yêu cầu chuyển quyền thuê sở hữu.
   - Giới hạn: Không tự ý sửa bút toán kế toán hay thay đổi hồ sơ nhân viên nếu không có thẩm quyền kiêm nhiệm.

2. **Kế toán (`ke-toan`)**:
   - Quyền: Quản lý sổ quỹ, tài khoản ngân hàng, chứng từ VAT, tài sản cố định, hạch toán bút toán kép, đối soát sổ cái, đóng/mở kỳ kế toán.
   - Giới hạn: Không đọc dữ liệu vị trí GPS chi tiết hay quản lý hồ sơ nhân sự cơ sở.

3. **Nhân sự (`nhan-su`)**:
   - Quyền: Quản lý hồ sơ nhân viên, phân ca trực, chấm công, quản lý phòng ban.
   - Giới hạn: Không xem báo cáo tài chính kế toán, không truy cập quản trị thiết bị GPS.

4. **Quản lý Cửa hàng (`quan-ly-cua-hang`) & Nhân viên (`nhan-vien`)**:
   - Quyền: Vận hành giao nhận xe, tạo hợp đồng thuê xe, thu tiền cọc/tiền thuê, kiểm tra két ngày tại cơ sở được phân công.
   - Giới hạn: Tuyệt đối bị chặn (HTTP 403) khi cố truy cập dữ liệu của cơ sở khác hoặc các chức năng KPI/kế toán/HR toàn công ty.

---

## 3. Đề xuất Bút toán Đảo & Bảo toàn Dữ liệu (chờ duyệt)

- Không hỗ trợ lệnh `DELETE` vật lý đối với: Hợp đồng đã kích hoạt, Giao dịch tiền tệ (`Transaction`), Bút toán kế toán (`JournalEntry`), Bản ghi phân bổ nợ (`LeasePaymentAllocation`), Lịch sử vị trí xe (`VehicleLocationEvent`).
- Khi phát hiện sai sót kế toán hoặc tài chính:
  - Bắt buộc tạo bút toán đối ứng (`Reversal Entry`) ghi rõ lý do và người phê duyệt.
  - Bản ghi gốc được đánh dấu trạng thái `reversed` kèm liên kết đến bản ghi điều chỉnh.
