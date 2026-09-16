# Review bản local mới và giao diện — 16/09/2026

## Cập nhật sau vòng sửa tiếp theo

Các lỗi U01–U07 và B01–B03 trong báo cáo này đã được xử lý trên working tree hiện tại:

- Két/HCNS và tất toán/đảo thu dùng URL đầy đủ `/api/auth/...`; browser audit không còn URL chứa `[object Object]`.
- Dropdown ngân hàng đổi xe chuẩn hóa được cả paginator và các tên field tương thích; option runtime hiển thị `Techcombank - 19031234567890 (CTCP HIMOTO VIỆT NAM)`, không còn `undefined`.
- Preview desktop/mobile, sidebar mobile và thanh header mobile đã được chỉnh responsive; kiểm 390/1440 không tràn ngang, sidebar đóng nằm ngoài viewport.
- Luồng trả khác cơ sở tra cứu đơn và bắt buộc chọn xe theo biển số/tên; server đổi xe kiểm quỹ/ngân hàng active, đúng cơ sở và lấy `owner_type` từ DB.
- Chiết khấu được lưu như adjustment riêng, không cộng vào tiền thực thu. Test bao phủ tất toán 10 triệu + chiết khấu 2 triệu → đảo thu → thu lại; thống kê kết thúc ở thực thu 10 triệu, chiết khấu 2 triệu, dư nợ 0.
- Icon trên các route nghiệp vụ đang dùng và icon do Element UI/BootstrapVue tạo đã được bỏ hoặc đổi thành nhãn chữ. Logo giao diện được trích trực tiếp từ `E:/duanthuexe/logo himoto oke.pdf (1).pdf` và dùng thống nhất tại `/images/branding/logo-himoto-pdf.png`.

Kết quả kiểm lại: PHPUnit **102 tests / 522 assertions**, production build compile thành công, `git diff --check` không có lỗi. Browser audit bundle thật với API giả lập đạt toàn bộ assertion: URL API, icon hiển thị, overflow, sidebar mobile, logo PDF và dropdown ngân hàng. Bằng chứng mới nằm tại `E:/duanthuexe/audit-prototype/review-final-ui/`.

Phần vẫn chưa được chứng minh là Supabase/PostgreSQL live, deploy Render và concurrency trên staging. Không migration live, không commit và không push trong vòng sửa này.

## Kết luận

**Chưa đủ điều kiện gọi N01–N07 đã hoàn thành. Chưa commit/push theo yêu cầu mới nhất.** Bản mới sửa được các lỗi cụ thể về snapshot chi ngân hàng, giới hạn field nhân sự và vị trí gốc khi đổi xe, nhưng kiểm trình duyệt phát hiện lỗi URL API và giao diện ảnh hưởng trực tiếp demo.

Baseline lần này là **working tree**, không phải một commit sạch: thư mục chính đang HEAD `4f506e4`, có thay đổi staged/unstaged và migration untracked. Tôi chụp bản mã sang worktree riêng `E:/duanthuexe/himoto-demo-release` từ `c91928a` để build/kiểm, giữ nguyên index và source AI khác trong thư mục chính. Không sửa nghiệp vụ, không stage, commit, push hay migrate live trong lượt kiểm này. SHA cũ trong version.json không đại diện toàn bộ bản mã chưa commit.

## Kết quả độc lập

- Full suite: **102 tests, 512 assertions**, exit 0, SQLite memory. Vendor được liên kết từ workspace chính; các service liên quan đã đối chiếu trùng snapshot khi kiểm.
- Hai ca bổ sung xác nhận lỗi còn lại: **2 tests, 11 assertions**, exit 0; assertion xác nhận hành vi lỗi, không phải acceptance.
- Build thực trong thư mục riêng: log có `Compiled successfully`, tạo static-dist. PowerShell wrapper trả exit 1 sau NativeCommandError từ cảnh báo stderr Browserslist; không ghi nhận pipeline exit 0. Browser đã tải được bundle tạo ra. Log: `E:/duanthuexe/audit-prototype/demo-build.log`.
- Browser Edge headless, Vue bundle thật, viewport 390/768/1440 cho preview; 390/1440 cho hợp đồng, két, HCNS, kho và modal đổi/trả. API được giả lập, không gửi dữ liệu lên backend thật. CSS Element UI và Google Fonts được phép tải từ CDN; không dùng thiếu CSS để kết luận lỗi giao diện.
- Preview/in PDF A4, payload thu tiền và ghi chú cơ bản chạy được; không có pageerror trong hai script. **HTTP gọi sai vẫn có thể xảy ra dù không có pageerror.**
- Chưa kiểm CRUD Supabase thật, schema runtime, credential rotation, PostgreSQL concurrency, deploy hoặc UAT. Không dùng màn localhost:8091 làm bằng chứng dữ liệu đã lưu thật.

## Lỗi ưu tiên

| ID | Phát hiện / bằng chứng | Cách sửa và nghiệm thu |
|---|---|---|
| U01 / P1 | **Két/HCNS gọi sai API.** Browser bắt GET `/daily-cash-registers/summary/[object%20Object]`, `/daily-cash-registers/history/[object%20Object]`, `/hr/duty-schedules/[object%20Object]`, `/hr/staff/[object%20Object]`, `/stores`. Route backend nằm dưới `/api/auth`. | Dùng `ApiService.query('/api/auth/...', params)` cho GET có filters; `get()` hiện nhận slug, không nhận Axios config. Dùng URL đầy đủ cho post/delete. Dùng endpoint stores/all và parse đúng shape. Kiểm Network URL/query/body/HTTP với API thật. |
| U02 / P1 | **Tất toán/đảo thu từ UI vẫn thiếu prefix.** `ModalInstallmentSchedule.vue:351,384` gọi `lease-contracts/...` trong khi route nằm `/api/auth/lease-contracts/...`. | Đồng bộ URL với Vuex/API service, kiểm click nút từ lịch kỳ đến response và DB, không chỉ gọi service test. |
| U03 / P1 | **Dropdown ngân hàng đổi xe ra `undefined - undefined (TK)`.** BANK_INDEX resolve response body; data là paginator `{data:[...],total,...}`. Modal gán cả paginator làm banks thay vì mảng con. `bank_name` cũng không khớp field Bank `bank`. | Parse `res.data.data`, validate Array.isArray, label dùng field thật, lọc tài khoản theo cơ sở; kiểm lựa chọn account ID đúng và không undefined. Artifact route-results.json ghi banksState và bankOptions. |
| U04 / P1 demo | **Preview desktop bị vỡ header**: modal khoảng 580px ở viewport 1440; tiêu đề xuống thành cột hẹp, toolbar bị cắt, nút in/đóng ở ngoài vùng nhìn thấy. Mobile 390 cũng cắt phần cuối nhóm nút zoom. | Đặt width modal thực thay vì chỉ max-width; tiêu đề và toolbar thành hàng rõ, flex-wrap cả nhóm nút, không ép tiêu đề co. Kiểm nút In/Đóng/100%/Vừa màn hình nhìn thấy, bấm được ở 390/768/1440. |
| U05 / P1 demo | **Mobile sidebar vẫn chiếm gần nửa màn hình 390px**, nội dung két bị ép hẹp, ngày chỉ thấy một phần, nút/money layout kém đọc. scrollWidth=viewport không chứng minh đạt: nội dung có thể bị che/cắt trong container. | Sidebar mobile đóng mặc định và overlay khi mở; main content width 100%, bỏ offset desktop tại breakpoint; kiểm fresh load và resize, đóng menu, mọi số tiền/ngày đầy đủ. |
| U06 / yêu cầu | **Chưa bỏ 100% icon.** Runtime còn icon lịch, caret select, toast error từ Element UI; source còn SVG ở VehicleIndex.vue:26, icon thêm ở ModalVehicleCreate.vue:511 và ModalVehicleEdit.vue:284,308. | Audit cả component library, CSS pseudo-element, upload, toast, modal và trạng thái rỗng; thay control icon-only bằng chữ. Không kết luận 100% từ số file đã thay. |
| U07 / usability | Báo cáo nói dropdown chọn xe trả; thực tế là input **ID Xe**, không có danh sách xe của hợp đồng/biển số. Banner vẫn nói “đơn một xe đã hoàn tất”, label bên dưới nói “đang thuê”. | Lookup order → chọn xe theo biển số/item, hiển thị trạng thái; sửa hướng dẫn nhất quán với flow trả đã hoàn tất hoặc làm workflow trả từng phần rõ. Payload vehicle_id đã được nối, nhưng UX chưa như báo cáo. |
| B01 / P1 | **Chiết khấu vẫn lệch danh sách và dashboard**: fixture 12 triệu, thu 10 triệu + discount 2 triệu → index outstanding=0, getStats total_outstanding=2 triệu. Nhánh index còn ép completed thành 0 thay vì kiểm nghĩa vụ thực. | Một công thức từ nghĩa vụ được duyệt trừ thu hiệu lực/adjustment, dùng chung index/show/stats/export/aging. Không dùng status để che sai số. |
| B02 / P1 | **Discount vẫn ghi amount_paid lớn hơn tiền thu thực.** Sau đảo một allocation, tổng amount_paid các kỳ vẫn vượt allocation hiệu lực 2 triệu. | Discount là điều chỉnh nghĩa vụ được duyệt, không phải tiền đã thu. Lưu phân bổ điều chỉnh/audit, test tất toán → đảo → thu lại, so invariant giữa kỳ/sổ/tổng. Nếu chưa đủ chính sách thì tạm chặn discount trong bản demo. |
| B03 / P1 | **Nguồn tiền đổi xe vẫn chưa được kiểm đủ ở server.** cash_id do client gửi được dùng trực tiếp; fallback có thể chọn quỹ không Active; CK không kiểm bank đúng cơ sở/owner_type, thiếu phương thức có thể bỏ phiếu chênh lệch. exchange_store_id chưa kiểm chặt scope. | Validate ngân hàng/quỹ active/cùng cơ sở, nguồn owner_type lấy DB; reject thiếu payment khi cần hoặc tạo nghĩa vụ nợ riêng; kiểm permission điểm bàn giao; request lặp/rollback/concurrency. Đây là đọc code, chưa chạy live. |

## Những mục đã cải thiện nhưng không nên nghiệm thu quá mức

- N03: snapshot chi ngân hàng và công thức closed đã được bổ sung, positive test đạt.
- N04: lịch trực chỉ chọn field staff cần thiết, test CCCD đạt. `exists:staff_profiles,id` chưa thay được kiểm staff thuộc cơ sở/permission.
- N05: lưu origin trước mutation, tra quỹ theo store là sửa đúng hướng; còn validation B03.
- N01: có migration 000006/000007 mới cho upgrade. Nhưng `testN01UpgradeMigrationAddsColumnsToExistingTable` **chỉ assert các cột do createTestTables dựng sẵn**, không gọi migration upgrade. Cần test schema cũ → chạy migration thật → giữ dữ liệu/cột mới, nhất là PostgreSQL. Không gọi đây là kiểm chứng migration thật đã PASS.
- N02: thêm discount_amount/settled_at giúp lưu thêm dữ liệu, nhưng chưa có công thức và lifecycle nhất quán như B01/B02.
- N06: payload trả xe có vehicle_id; payload đổi xe có method/bank khi chênh lệch. Dropdown và URL vẫn phải kiểm end-to-end.

## Ảnh và bằng chứng

Thư mục: `E:/duanthuexe/audit-prototype/review-latest-ui/`.

- `contract-1440.png`: header preview bị ép/cắt; `contract-390.png`: toolbar mobile.
- `cash-390.png`: sidebar chiếm chỗ, nội dung bị ép và lỗi gọi route.
- `hr-390.png`, `warehouse-390.png`, các bản `*-1440.png`: màn hình tương ứng.
- `return-390.png`, `exchange-390.png`: modal trả/đổi.
- `contract-a4.pdf`: bản in từ fixture, không phải hợp đồng thật.
- `results.json`: preview/thu/ghi chú smoke; `route-results.json`: URL lỗi, paginator bank, options undefined và icon runtime.
- Script: `E:/duanthuexe/audit-prototype/review-latest-ui.py`, `review-ui-routes.py`.
- Reproduction backend: `E:/duanthuexe/himoto-demo-release/docs/review-latest/RemainingSettlementTest.php` (SQLite riêng).

Các số NaN ở vùng báo cáo phía sau preview do fixture không cung cấp đầy đủ dữ liệu báo cáo; không dùng chúng làm kết luận lỗi live. Thông báo “Route not found” trong bài browser do harness trả 404 cho URL ngoài API; **URL sai do ứng dụng phát ra là bằng chứng**, chưa phải HTTP ghi nhận trên Render.

## Thứ tự sửa nhanh khi được tiếp tục

1. Sửa U01/U02/U03 trước để két/HCNS/tất toán/đảo thu/dropdown gọi API đúng.
2. Sửa U04/U05 để demo hợp đồng desktop/mobile dùng được; giữ in A4 và snapshot.
3. Chặn hoặc hoàn thiện discount B01/B02 và nguồn tiền đổi xe B03; không mở luồng thu sai chỉ để có nút demo.
4. Test migration upgrade thực; chạy HTTP + UI + DB staging bằng dữ liệu riêng, refresh/re-login.
5. Hoàn tất icon/runtime và ma trận Excel trong plan gốc. GPS/provider, báo cáo/KPI, kế toán/VAT/tài sản, HCNS/chấm công chưa có bằng chứng mới để gọi xong.

Theo yêu cầu hiện tại, **chỉ review, chưa đẩy Git**. Việc kiểm nhanh cho demo không thay nghiệm thu toàn dự án.
