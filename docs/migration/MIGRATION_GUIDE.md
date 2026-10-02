# Hướng dẫn phát triển & Đối chiếu HIMOTO (Next.js + NestJS)

Tài liệu hướng dẫn vận hành môi trường phát triển cục bộ và quy trình đối chiếu tính năng giữa hệ thống cũ (Vue 2 + Laravel) và hệ thống mới (Next.js + NestJS).

---

## 1. Mốc đối chiếu chuẩn (Baseline)
- Commit đóng băng: `2b4911645cdeb388e43c8d3210d8eb0c48bac93a` (`2b49116`).
- Nhánh làm việc: `migration/nextjs-node-parity`.
- `2b49116` là mốc đối chiếu lịch sử. `main` đã nhận các bản sửa Laravel/Vue và UAT sau mốc này; ngày 02/10/2026 chủ dự án yêu cầu gộp PR #12 vào `main`.
- Các dịch vụ hiện tại dùng `render.yaml` (Laravel/Vue). Mã trong `apps/web`, `apps/api` và blueprint `infra/render-next.yaml` là phần migration; việc gộp mã không chuyển dịch vụ hiện tại sang Next/Nest.

---

## 2. Cấu trúc thư mục
- `apps/web`: Ứng dụng Next.js 15 App Router, React 19, TypeScript.
- `apps/api`: Ứng dụng NestJS, Node.js 24 LTS, TypeScript, `pg` connection pool.
- `packages/contracts`: Định nghĩa types và DTO dùng chung giữa frontend và backend.
- `docs/migration`:
  - `feature-matrix.csv`: 241 mục kiểm kê chi tiết (UI pages, API endpoints, Cron jobs).
  - `baseline.json`: Snapshot cấu hình môi trường và mốc commit.
  - `api-contracts.json`: Danh sách 197 API endpoints runtime của Laravel.
- `tests/parity`: Bộ kiểm thử đối chiếu tự động.

---

## 3. Chạy ứng dụng cục bộ

### 3.1 Chạy Web (Next.js):
```bash
cd apps/web
npm run dev
# Ứng dụng chạy tại: http://localhost:3000
# Endpoint phiên bản: http://localhost:3000/version.json
```

### 3.2 Chạy API (NestJS):
```bash
cd apps/api
npm run start:dev
# API chạy tại: http://localhost:8000/api
# Health check: http://localhost:8000/api/health
```

---

## 4. Quy trình đối chiếu từng module (Phase P2 - P6)
1. **Chuyển giao diện (P2):** Chuyển component Vue sang React, giữ nguyên CSS scoped, icon và định dạng. Kết nối API tới Laravel test để xác minh giao diện trước.
2. **Chuyển API (P3 - P5):** Triển khai service trong NestJS, sử dụng parameterized SQL trực tiếp trên schema `himoto`.
3. **Cập nhật ma trận:** Cập nhật trạng thái trong `docs/migration/feature-matrix.csv` từ `PENDING` sang `PASS` sau khi có bằng chứng test đạt.

## 5. Trạng thái kiểm chứng migration

- Build thành công chưa chứng minh tương đương nghiệp vụ. Các nhãn `PASS` trong ma trận cũ cần đối chiếu với bằng chứng kiểm thử độc lập.
- Review commit `7f01121` phát hiện thiếu route, thao tác giao diện báo thành công khi API lỗi, dữ liệu mẫu thay dữ liệu thật và một số thiếu sót API. Bằng chứng và script tái hiện nằm tại [reviews/7f01121](reviews/7f01121/README.md); đây là kết quả của commit đó, không phải chứng nhận cho mã sau sửa.
- Bản sửa tài khoản Node bổ sung chặn quản lý tài khoản trái quyền, giới hạn danh sách nhân viên theo cơ sở, xóa mềm và loại tài khoản đã xóa khỏi xác thực. Chạy `cd apps/api && npm test` để build và kiểm tra 8 ca này với database giả lập.
- Chưa nghiệm thu Next/Nest để thay dịch vụ Laravel/Vue hiện tại. Cần hoàn tất kiểm tra route và nghiệp vụ trước khi triển khai blueprint migration.
