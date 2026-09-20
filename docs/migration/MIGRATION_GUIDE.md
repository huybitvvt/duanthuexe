# Hướng dẫn phát triển & Đối chiếu HIMOTO (Next.js + NestJS)

Tài liệu hướng dẫn vận hành môi trường phát triển cục bộ và quy trình đối chiếu tính năng giữa hệ thống cũ (Vue 2 + Laravel) và hệ thống mới (Next.js + NestJS).

---

## 1. Mốc đối chiếu chuẩn (Baseline)
- Commit đóng băng: `2b4911645cdeb388e43c8d3210d8eb0c48bac93a` (`2b49116`).
- Nhánh làm việc: `migration/nextjs-node-parity`.
- Nhánh `main`: Giữ nguyên ở `2b49116`, không tự ý commit đè.

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
