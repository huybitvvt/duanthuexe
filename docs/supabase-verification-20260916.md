# Báo cáo Kiểm chứng Kỹ thuật & Hiện trạng Nghiệp vụ HIMOTO

- **Thời gian thực hiện ban đầu**: 2026-09-16
- **Tình trạng nghiệm thu**: **CHƯA ĐẠT NGHIỆM THU — Cần hoàn thiện theo review 38b330a**.
- **Tài liệu căn cứ nghiệm thu chính thức**: Vui lòng tham chiếu [`docs/himoto-review-and-completion-plan-38b330a.md`](./himoto-review-and-completion-plan-38b330a.md).

> [!CAUTION]
> Báo cáo ban đầu ghi nhận "PASS 100%" dựa trên bài thử kỹ thuật với bảng canary và unit test in-memory. Kết quả rà soát độc lập tại commit `38b330a` đã phát hiện các lỗi nghiêm trọng về tiền (tất toán 0 đồng, đảo thu xóa phân bổ, két bỏ sót chi ngân hàng) và lỗ hổng phân quyền (nhân viên đọc két cơ sở khác, thiếu kiểm soát quyền HR). Tài liệu này được lưu giữ cho mục đích lịch sử kỹ thuật; mọi tiêu chí nghiệm thu phải căn cứ theo kế hoạch rà soát tại `docs/himoto-review-and-completion-plan-38b330a.md`.

---

## 1. Kết quả Kiểm thử Kỹ thuật Canary Run (Tham khảo)

Quy trình canary run kỹ thuật qua PDO (`scripts/verify_supabase_crud.php`) với biến môi trường và dọn dẹp an toàn theo `test_run_id`:

```text
=== HIMOTO SUPABASE CRUD VERIFICATION ===
- Kết nối thông qua biến môi trường SUPABASE_DATABASE_URL
- Tạo bản ghi kiểm tra canary với checksum SHA-256
- Đọc và xác minh checksum toàn vẹn dữ liệu
- Cập nhật bản ghi và xác minh thay đổi
- Xóa bản ghi thuộc test_run_id (không TRUNCATE toàn bảng)
```

---

## 2. Các hạng mục đang hoàn thiện theo Review 38b330a

| Nhóm | Hạng mục | Hiện trạng theo Review 38b330a | Giải pháp khắc phục |
| :--- | :--- | :---: | :--- |
| **Bảo mật** | Credential trong source | Đã khắc phục | Chuyển hoàn toàn sang biến môi trường `SUPABASE_DATABASE_URL`, làm sạch lịch sử Git trước push |
| **Tài chính** | Tất toán hợp đồng | Đang xử lý | Chặn tất toán 0 đồng khi còn nợ, bắt buộc phân bổ tiền thực và trừ hết nợ |
| **Tài chính** | Đảo thu (Reversal) | Đang xử lý | Giữ bản ghi allocation (`status = reversed`), liên kết `reversal_transaction_id`, điền đủ `cash_id`/`bank_id` |
| **Sổ két** | Thu chi ngân hàng & chốt két | Đang xử lý | Đưa đầy đủ chi ngân hàng vào báo cáo, chặn ghi đè sổ két đã chốt, tính tổng nhiều cơ sở |
| **Phân quyền** | Phân quyền két & HR | Đang xử lý | Chặn nhân viên xem/sửa két chi nhánh khác (HTTP 403), che CCCD trong danh bạ, kiểm soát quyền HR |
| **Giao diện** | Chuẩn 100% No Icon | Đang xử lý | Thay thế toàn bộ SVG và thẻ `<i>` còn sót trong header, drawer, preview và form nghiệp vụ |

---

## 3. Lệnh kiểm thử độc lập

```powershell
# Chạy toàn bộ test suite dự án
.\php.cmd ..\tools\phpunit.phar -c phpunit.xml

# Chạy kiểm thử hồi quy các lỗi đã sửa
.\php.cmd ..\tools\phpunit.phar -c phpunit.xml --filter testReview docs/review-38b330a/ReproduceReviewTest.php

# Biên dịch frontend
npm.cmd run build:static
```
