# Triển khai HIMOTO

Kiến trúc đã chuẩn bị:

- `himoto-web`: Vue 2 dạng static site trên Render.
- `himoto-api`: Laravel 5.8/PHP 7.4 chạy Docker trên Render.
- PostgreSQL: Supabase, schema `himoto`.
- Ảnh tải mới: Cloudinary, thư mục mặc định `himoto/vehicles`.

## 1. Thông tin cần có

- Một Git repository **private** chứa thư mục dự án này.
- Supabase Session Pooler URI (cổng 5432, SSL bắt buộc).
- Cloudinary cloud name, API key và API secret.
- Tài khoản Render đã kết nối với Git repository.

Không commit `.env`, database dump, file backup hoặc khóa dịch vụ vào Git.

## 2. Chuyển MySQL sang Supabase

Database dump đã được chuyển lên Supabase: 37 bảng, 683.548 dòng, không lỗi.
Sau migration Cloudinary, database dùng khoảng 206 MB. Schema là `himoto`, vì vậy
biến `DB_SCHEMA` của backend phải giữ giá trị `himoto`.

Trước khi chạy với Supabase thật, lấy Session Pooler URI trong Supabase Dashboard.
Mật khẩu có ký tự đặc biệt phải được URL-encode trong URI. Pgloader 3.6.x có lỗi xác
minh certificate với Supabase pooler, vì vậy chuyển MySQL sang PostgreSQL local trước,
sau đó dùng `pg_restore` (libpq/SSL) để nhập lên Supabase:

```powershell
docker network create himoto-migration
docker run -d --name himoto-mysql --network himoto-migration `
  -e MYSQL_ROOT_PASSWORD=local-mysql-only -e MYSQL_DATABASE=himoto `
  --mount type=bind,source="E:\duanthuexe\database-qfsfaedgjh.sql",target=/docker-entrypoint-initdb.d/01.sql,readonly `
  mysql:5.7
docker run -d --name himoto-postgres --network himoto-migration `
  -e POSTGRES_PASSWORD=local-postgres-only -e POSTGRES_DB=himoto postgres:17-alpine
docker run --rm --network himoto-migration --entrypoint pgloader `
  dimitri/pgloader:latest --on-error-stop `
  mysql://root:local-mysql-only@himoto-mysql/himoto `
  postgresql://postgres:local-postgres-only@himoto-postgres:5432/himoto
docker volume create himoto-pg-dump
docker run --rm --network himoto-migration `
  -e PGHOST=himoto-postgres -e PGDATABASE=himoto -e PGUSER=postgres `
  -e PGPASSWORD=local-postgres-only `
  --mount type=volume,source=himoto-pg-dump,target=/backup `
  postgres:17-alpine pg_dump --format=custom --no-owner --no-acl `
  --file=/backup/himoto.dump
$env:SUPABASE_DATABASE_URL = "postgresql://...session-pooler.../postgres?sslmode=require"
docker run --rm -e SUPABASE_DATABASE_URL `
  --mount type=volume,source=himoto-pg-dump,target=/backup `
  postgres:17-alpine sh -c `
  'pg_restore --dbname="$SUPABASE_DATABASE_URL" --no-owner --no-acl --exit-on-error /backup/himoto.dump'
```

Sau khi kiểm tra số dòng và dữ liệu, xóa đúng hai container, volume và network tạm.
Không dùng `docker system prune` vì có thể xóa dữ liệu Docker khác.

## 3. Tạo dịch vụ Render

Ở Render Dashboard, chọn **New > Blueprint**, kết nối Git repository và dùng
`render.yaml`. Blueprint tạo `himoto-api` và `himoto-web`.

Điền các biến bí mật/giá trị còn thiếu:

### Backend `himoto-api`

- `APP_KEY`: tạo bằng lệnh bên dưới; phải giữ nguyên tiền tố `base64:`.
- `DATABASE_URL`: Supabase Session Pooler URI, thêm `sslmode=require`.
- `CORS_ALLOWED_ORIGINS`: URL HTTPS chính xác của `himoto-web`, không có dấu `/` cuối.
- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`
- `WORDPRESS_LEADS_TOKEN`: token mới của API WordPress; thu hồi token từng nằm trong source cũ.

```powershell
docker run --rm php:7.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

### Frontend `himoto-web`

- `MIX_API_URL`: URL HTTPS của `himoto-api`, không thêm `/api` ở cuối.

Sau khi có URL của cả hai dịch vụ, cập nhật hai biến URL trên và deploy lại cả hai.
Blueprint hiện đặt `RUN_MIGRATIONS=false`, nên backend không tự chạy migration khi deploy.
Sau khi thử trên bản sao staging, chạy thủ công migration cần thiết với `DATABASE_URL`
của môi trường đích. Với index doanh thu theo xe, kiểm tra trạng thái bằng
`php scripts/check_vehicle_revenue_index.php` sau khi nạp biến môi trường.
Kiểm tra sức khỏe backend tại:

```text
https://<backend-render>/api/health
```

Kết quả hợp lệ là JSON có `status: ok`.

## 4. Backup Supabase hằng ngày

Đặt URI vào biến môi trường của máy chạy backup, không ghi URI vào file script:

```powershell
$env:SUPABASE_DATABASE_URL = "postgresql://...session-pooler.../postgres?sslmode=require"
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\backup-supabase.ps1
```

Script tạo file custom-format trong `backups/`; thư mục này bị Git bỏ qua. Lập lịch
lệnh trên bằng Windows Task Scheduler trên một máy luôn bật, đồng thời sao chép các
file backup sang nơi lưu trữ mã hóa khác. Thử khôi phục định kỳ vào một project/test
database riêng; không thử restore trực tiếp lên production.

## 5. Giới hạn trước khi bàn giao production

- Laravel 5.8, PHP 7.4 và nhiều package hiện đã hết vòng đời; cần kế hoạch nâng cấp.
- [Render Free](https://render.com/docs/free) ngủ sau 15 phút không có truy cập, có
  thể mất khoảng một phút để khởi động lại và Render ghi rõ không dùng cho production.
- [Supabase Free](https://supabase.com/docs/guides/platform/database-size) chuyển sang
  read-only khi database vượt 500 MB; [bảng giá](https://supabase.com/pricing) xác nhận
  gói này không có automatic backup. Database hiện khoảng 202 MB (40,4% quota), còn
  khoảng 298 MB nên phải theo dõi tăng trưởng.
- Supabase Free có thể pause project sau một tuần không hoạt động.
- [Cloudinary Free](https://cloudinary.com/documentation/developer_onboarding_faq_free_plan)
  dùng được cho production trong phạm vi credit của gói; cần theo dõi Usage Dashboard.
- File ảnh cũ vẫn trỏ tới DigitalOcean Spaces. Database dump không chứa các file ảnh;
  muốn bỏ Spaces phải tải và chuyển riêng các file cũ sang Cloudinary.
