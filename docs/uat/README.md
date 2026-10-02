# Tài khoản và kho xe UAT

Ngày 28/09/2026, chủ project đã cho phép chạy trên Supabase hiện tại `duanthuexe-sg` (`xlxkheprwosozyhkejrl`) vì sản phẩm chưa vận hành. Đã chạy [supabase-setup-current.sql](supabase-setup-current.sql) **một lần**; `run_id` là `20260928055759`. Không chạy lại script này để lấy mật khẩu: nó có chốt kiểm tra số user/xe và từ chối reset lần hai.

## Kết quả đã áp dụng

Phần này ghi nhận lần khởi tạo ngày **28/09/2026**. Ngày 30/09 đã bổ sung 6 tài khoản, tổng 20 tài khoản UAT; xem [biên bản bàn giao cập nhật](handover-20260930.md). Không dùng các số lượng lịch sử bên dưới làm điều kiện reset dữ liệu hiện tại.

- 11 user cũ được chuyển sang `deactive` và gắn `deleted_at`; ID và lịch sử giao dịch được giữ lại. Mã đăng nhập Node đã được chỉnh để loại các user này khỏi xác thực.
- Tạo 14 user mới: 1 quản trị, 1 ban giám đốc, 1 kế toán, 1 nhân sự; mỗi kho `CS1`–`CS5` có 1 quản lý và 1 nhân viên.
- Chuyển 49 xe `ready` đủ điều kiện. Số xe `ready` sau chạy: `CS1=12`, `CS2=11`, `CS3=11`, `CS4=12`, `CS5=11`, `CS6=4`. Tổng `ready=61`; `using=110`. Xe đang thuê, gắn đơn đang mở, điều chuyển mở hoặc thuộc kho thuê sở hữu không được chuyển.
- Có 11 audit vô hiệu hóa user, 49 audit chuyển xe và 49 sự kiện vị trí xe cho lần chạy này.

Backup schema `himoto` trước khi ghi: `backups/himoto-before-uat-20260928-125018.dump` ở thư mục gốc repo (PostgreSQL custom archive, 6.145.390 byte; SHA-256 `650B558014D80AF81E366E331382E8198EA71D35BE26036EE9B7C0B1D09CA448`). File backup và file tài khoản đều bị `.gitignore` loại khỏi Git.

Danh sách email, mật khẩu và vai trò: `backups/uat-credentials-20260928-125758.csv`. Giữ file này riêng tư; chỉ gửi từng người test tài khoản của họ. Email `.test` không nhận thư đặt lại mật khẩu. Nếu thất lạc file, đặt lại mật khẩu cho từng tài khoản; không chạy lại reset.

## Kiểm tra trong Supabase

Chạy toàn bộ [supabase-preflight.sql](supabase-preflight.sql) ở SQL Editor của đúng project; script này chỉ đọc dữ liệu. Sau lần khởi tạo 28/09, kết quả là `active_users=14`, `target_stores_found=5`, `open_transfers=0`; sau bổ sung tài khoản 30/09, số tài khoản hoạt động là 20. Số xe và điều chuyển có thể thay đổi theo nghiệp vụ; đối chiếu theo thời điểm kiểm tra. Website đang dùng Laravel/Vue qua `render.yaml`; mã Node trong `apps/api` là phần migration chưa được nghiệm thu thay thế.

[supabase-setup-staging.sql](supabase-setup-staging.sql) là bản nháp staging cũ, vẫn khóa và dùng mã kho đã lỗi thời. Không dùng file đó cho project hiện tại.
