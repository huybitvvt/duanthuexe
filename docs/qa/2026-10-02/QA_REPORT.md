# Kiểm thử hệ thống HIMOTO — 02/10/2026

## Phạm vi và kết quả

- API Tester ghi nhận **898 ca kiểm tra** trên bản `2bed34d`, lập inventory **213 route**. Đã gọi toàn bộ **100 route GET** và kiểm tra chặn đăng nhập của **107 route ghi**. Đây là độ phủ route/guard, không phải mọi tổ hợp nghiệp vụ.
- **77 GET hợp lệ** và **9 endpoint xuất XLSX** qua ở lượt rà ban đầu. **360 ca theo vai trò** nằm trong tổng 898.
- PHP sau sửa: **291 test, 1.514 assertions, qua toàn bộ**. Có 28 test mới cho đầu vào API, tài khoản, bảng giá, báo cáo, bán xe, khách hàng và phiếu thu/chi.
- Frontend production build: exit 0. Có cảnh báo Sass/Browserslist sẵn có.
- API Node trong nhánh migration: build qua; **8/8 test quyền tài khoản qua**. Các chỉnh sửa Node không thuộc hai commit sửa Laravel ban đầu; được đưa vào lượt gộp PR #12 theo yêu cầu ngày 02/10.
- Đã đẩy hai commit sửa lên Git: `main` **f90c2dd**, **4f6ee0a**; tương ứng nhánh migration **f303086**, **77843cb**. Web và API đều xác nhận đang chạy commit cuối **4f6ee0a52a217aa2b3cb03ac34423ab61c5084eb**; API health/database báo `ok`.
- Kiểm lại API trên `f90c2dd`: **119 ca, 0 lỗi**. ID sai: 23 ca JSON 404; ngày/phân trang/nguồn sai: 37 ca JSON 422; guard đăng nhập: 24 ca 401; báo cáo/tìm kiếm/XLSX/đầu vào hợp lệ: 30 ca 200; các từ chối đúng quyền: 5 ca 403. Bộ đếm admin/Ban giám đốc/vận hành đều **2.685 đơn**.
- Kiểm tra API bổ sung trên `4f6ee0a`: **9 ca, 0 lỗi**. Admin/kế toán lấy tài khoản ngân hàng công ty với `store_id=0` trả 200; giá trị âm/sai kiểu trả 422; nhân viên cơ sở truy cập tài khoản công ty trả 403. Có đối chiếu commit trên cả hai dịch vụ. Tổng kiểm lại API hai đợt: **128 ca, 0 lỗi**; không chạy lại toàn bộ 898 ca ban đầu.
- Trình duyệt trên bản triển khai cuối: **18 ca, 0 lỗi**, gồm quản trị viên, nhân viên CS1, nhân sự, telesale và kế toán. Kiểm tra quyền nút lịch sử xe tại CS1/CS2, tải hộp thoại lịch sử, chọn cơ sở lập phiếu, quyền tải danh sách hợp đồng, lỗi JavaScript và HTTP API. Lượt admin đầu bị ngắt do script đọc route chưa khởi tạo; đã sửa cách chờ và chạy lại đủ 6 ca admin. Giữ artifact lần đầu để đối chiếu.
- Trình duyệt dùng bundle build local: **12 ca quyền/lịch sử xe, 0 lỗi** trước khi triển khai. Không cộng các ca này vào 18 ca giao diện live.

## Lỗi đã sửa

1. **Đầu vào API gây 500:** ID không phải số, phân trang sai kiểu, ngày sai định dạng/thứ tự và nguồn khách không phải array. Thêm validation trước truy vấn; API không tồn tại trả 404 thay vì HTML ứng dụng. Giữ đường dẫn xóa hợp đồng theo danh sách ID.
2. **Đăng nhập trước khi bind model:** route có ID không tồn tại được kiểm tra JWT trước khi tra dữ liệu; request không đăng nhập trả 401.
3. **Báo cáo kế toán/Ban giám đốc/vận hành:** sửa phụ thuộc sai vào tài khoản quản trị có `role_id = 1` và cơ sở bắt buộc; bộ đếm toàn công ty dùng quyền thực tế. Phạm vi cơ sở của quản lý vẫn được kiểm tra bằng test.
4. **Tìm kiếm báo cáo và xuất Excel:** từ khóa chữ bị đưa vào phép so sánh ID dạng số trên PostgreSQL. Sửa tìm kiếm theo chữ, biển số, tên xe, khách và mã hợp đồng; giữ tìm kiếm ID và `#ID`.
5. **Tài khoản/mật khẩu:** chặn mật khẩu rỗng/ngắn, kiểm tra mật khẩu cũ trên API yêu cầu trường này, chặn quản trị viên xóa tài khoản đang đăng nhập; kiểm tra role/cơ sở và email trùng khi tạo/sửa tài khoản.
6. **Bảng giá:** từ chối giá âm và khoảng năm/ngày đảo ngược; kiểm tra toàn bộ danh sách trước khi lưu và lưu trong transaction. Dòng ID không tồn tại không còn làm lưu dở danh sách.
7. **Khách hàng:** kiểm tra dữ liệu tạo/sửa; chặn xóa khách đã được tham chiếu bởi hợp đồng thuê, bán hoặc thuê sở hữu, kể cả lịch sử hợp đồng đã xóa mềm.
8. **Bán xe:** chặn xe không sẵn sàng, xe đang thuộc hợp đồng khác, xe không có mặt tại cơ sở bán và xe trùng danh sách. Tính tổng tiền/giá vốn/lợi nhuận ở server; lưu nguyên tử; sửa/xóa đơn giải phóng xe khi không còn hợp đồng khác. Báo cáo dùng giá vốn đã lưu tại thời điểm bán.
9. **Phiếu thu/chi:** tách tiền mặt và chuyển khoản thành phương thức đúng; tính thống kê đúng; lưu hai dòng trong cùng transaction. Test mô phỏng lỗi ở dòng chuyển khoản chứng minh dòng tiền mặt được rollback. Giữ cơ sở kế toán đã chọn và chặn dữ liệu sai trước khi ghi.
10. **API giao dịch:** sửa kiểu trả về thiếu import gây 500 sau khi đã ghi; kiểm tra đầu vào và từ chối sửa ID không tồn tại thay vì tạo giao dịch mới.
11. **Giao diện:** giới hạn nút/lời gọi lịch sử xe theo cơ sở quản lý hoặc cơ sở hiện tại; hiển thị chọn cơ sở cho kế toán lập phiếu; không gọi danh sách hợp đồng từ form phiếu khi tài khoản thiếu quyền đọc hợp đồng.

## Bằng chứng

- [Audit API](evidence/REPORT.md) và [898 ca đã phân loại](evidence/audit-classified-results.json).
- [119 ca kiểm lại API](evidence/retest-corrected-results.json) và [biên bản](evidence/RETEST_REPORT.md).
- [9 ca cuối](evidence/final-deployment-checks.json) và [đối chiếu commit web/API](evidence/FINAL_DEPLOYMENT_REPORT.md).
- [18 ca giao diện live cuối](evidence/browser-final-results.json); [6 ca admin chạy lại](evidence/browser-live-admin-results.json); [lượt đầu bị ngắt](evidence/browser-live-results.json).
- [PHPUnit sau gộp](evidence/phpunit-merge.xml): 291 test / 1.514 assertions.
- Log gốc trước/sau sửa và log build lưu riêng tại `E:\duanthuexe\audit-prototype\qa-full-20261002\root-audit`; log lượt gộp tại `E:\duanthuexe\audit-prototype\merge-main-20261002`. Không đưa mật khẩu, token hoặc file backup vào Git.

Các ca PHP được lưu trong `tests/Feature/Himoto` và `tests/Unit`; test quyền tài khoản Node nằm ở `apps/api/test/user-access.test.cjs`. Có thể chạy lại bằng `php vendor/phpunit/phpunit/phpunit` và `cd apps/api && npm test`; PHP dùng SQLite riêng trong bộ nhớ.

## Dữ liệu và giới hạn

- Các test ghi dữ liệu, lỗi database, bán xe và tiền đều chạy trên **SQLite riêng trong bộ nhớ**. Lượt này không tạo phiếu, bán xe hay đổi mật khẩu thật trên live.
- Không tái hiện vượt quyền/IDOR trong những ca đã chạy. Một số nghiệp vụ chi tiết không có fixture live, được kiểm tra bằng các test PHP sẵn có và test mới.
- Chưa thử chuyển tiền thật, SMS/Zalo/email thật, webhook thanh toán thực hoặc thiết bị GPS ngoài hệ thống. Chưa chứng minh chịu tải/concurrency PostgreSQL; không chạy stress test vào live dùng chung.
- **Dữ liệu cần cấu hình đã biết từ lượt trước:** 4 xe CS1 thiếu giá thuê 1 ngày phù hợp loại/năm: `986`, `774`, `746`, `511`. Không tự đặt đơn giá. Kết quả đối chiếu lưu cục bộ tại `E:\duanthuexe\audit-prototype\qa-20261002\pricing-and-cleanup-results.json`.
- Những kết quả trên xác nhận các ca đã chạy; không phải cam kết hệ thống không còn bất kỳ lỗi nào.

## Kiểm tra khi gộp PR #12 vào main

- Gộp `origin/main` vào nhánh migration và giữ tài liệu UAT cập nhật từ main; hai xung đột chỉ nằm ở biên bản bàn giao và ma trận quyền.
- Sau gộp, mã `app`, `routes`, `config`, `database`, `resources`, `Dockerfile` và `render.yaml` khớp main đã triển khai. Những thay đổi thêm là mã migration, các bản sửa tài khoản Node, test và tài liệu/bằng chứng.
- Chạy lại PHP trên checkout gộp: **291 test, 1.514 assertions, exit 0**.
- `cd apps/api && npm test`: build NestJS và **8/8 test, exit 0**.
- `cd apps/web && npm run build`: **Next.js production build, exit 0**.
- `npm run build:static`: **Vue production/static build, exit 0**. Có cảnh báo Sass/Browserslist sẵn có.
- Compile `packages/contracts/tsconfig.json` bằng TypeScript đã cài trong API: **exit 0**.
- Báo cáo API/giao diện ở trên thuộc lần kiểm tra commit `4f6ee0a` trước gộp; không tính là chạy lại toàn bộ audit trên merge commit.
- Next/Nest vẫn là phần migration chưa được nghiệm thu tương đương toàn bộ nghiệp vụ; xem [hướng dẫn migration](../../migration/MIGRATION_GUIDE.md). Gộp PR không chuyển runtime của dịch vụ hiện tại.
