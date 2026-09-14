# HIMOTO — Việc còn lại trước khi mở vận hành

Mốc review: `fce6a60`, nhánh `feature/himoto-complete-integration`.
Tài liệu này thu hẹp phần còn lại của `himoto-completion-plan.md`; không yêu cầu làm lại prototype hoặc giao diện đã đạt.

## Kết quả xác minh lượt này

- Đã chạy lại `.\php.cmd artisan test`: exit 0, 12 tests, 65 assertions.
- Đã đọc code test: kiểm constants/fillable/quan hệ Eloquent, gán thuộc tính model, đăng ký route, health, 401 và validation login 422. Chưa có lưu đơn thuê, trả xe, tính tiền, giao dịch, hoặc kiểm quyền khác cửa hàng bằng dữ liệu thực.
- Cache Dashboard đã có key user:store và reset khi PURGE_AUTH.
- `test_user_switching_cache.py` chưa thực hiện User B login dù docstring mô tả có. Nếu không tìm thấy store, assertion có thể bị bỏ qua bởi `if cache_cleared`, sau đó vẫn return True. Đây là lỗi của test cần sửa.
- `version.json` đang ghi commit `284abfa`, không phải `fce6a60` như báo cáo. Chưa thấy build script/main nhập hoặc xuất file này làm bằng chứng phiên bản live.
- Đã tải công khai `js/app.js`, `3.js`, `15.js` từ himoto-web và so SHA-256 với local: cả ba khác nhau. Kết quả trong `audit-prototype/release-review-fce6a60/deployed-assets.json` ở workspace cha. Khác hash có thể do build environment/phiên bản; không đủ kết luận site đang chạy commit cũ, nhưng cũng không chứng minh site đã chạy bản mới.
- Báo cáo 13/13 live smoke là bằng chứng lần chạy được lưu; lượt review này không đăng nhập lại API thật.

Kết luận: các cải tiến test/cache có tiến triển; chưa có cơ sở ghi “hoàn thành toàn diện A–G” hoặc mở toàn bộ nghiệp vụ chính thức.

## 1. Chốt bản sẽ phát hành và sửa tài liệu sai

Thực hiện:
1. Trong Render Dashboard, xem service frontend và backend: repo, branch deploy, commit deployment gần nhất. Ghi cả hai commit vào biên bản.
2. Sinh version metadata từ commit nguồn tại lúc build, không gõ tay timestamp/commit. Metadata chỉ gồm commit và thời điểm; không chứa credential hay biến môi trường nội bộ.
3. Xuất metadata frontend vào artifact public và kiểm sau deploy; với backend có thể dùng commit deployment trong Render làm bằng chứng.
4. Sửa `himoto-release-matrix.md` theo router/controller/service. Ví dụ hiện ghi `/maintenance-logs`, `/receipts`, `/price-vehicles` trong khi routes/api.php dùng `/maintenance-log`, `/receipt`, `/priceVehicles`. Đối chiếu tiếp API update bank/cash và API report.
5. Sửa `himoto-release-acceptance.md`: mục chưa test ghi “chưa xác minh”, không gắn test constants/model làm bằng chứng đã kiểm giao dịch tiền.

Điều kiện đạt: xác định được đúng phiên bản chuẩn bị release; tài liệu không chứa route hoặc kết luận suy đoán. Chưa push vào branch auto-deploy khi chưa có ủy quyền triển khai.

## 2. Chốt cache bằng kiểm thử đổi người dùng thực sự

Files: dashboard.module.js, auth.module.js, store.module.js, Dashboard.vue và test_user_switching_cache.py.

1. Bắt buộc assert store tồn tại và cache A đã được tạo trước logout; không được bỏ qua assertion nếu giá trị null.
2. Dùng cùng browser context, đăng nhập A → chờ dữ liệu A → logout qua luồng UI → đăng nhập B trong TTL.
3. Trả fixture A và B khác rõ ràng; kiểm UI và request B, không chỉ Object.keys(cache).
4. Kiểm store list/selected store khi đổi người; reset hoặc kiểm tra lại theo quyền B.
5. Thêm trường hợp response A còn đang chờ lúc logout rồi đến sau B. Request cũ không được ghi lại cache hoặc cập nhật UI phiên mới; dùng session generation/request identity nếu cần.
6. Kiểm A→B chi nhánh với response A chậm, giữ đúng dữ liệu B; kiểm cùng user đổi scope/quyền.
7. Chạy test thiếu store, cache chưa populate, dữ liệu B sai: phải exit 1.

Điều kiện đạt: dữ liệu không lẫn phiên/người/cửa hàng. Không tuyên bố lộ dữ liệu chỉ từ code review; phải tái hiện và sửa hành vi cụ thể nếu có.

## 3. Nghiệm thu thuê xe, tiền và quyền trên môi trường riêng

Chuẩn bị PostgreSQL test riêng và tài khoản tổng hợp. Không dùng Supabase vận hành để chạy seed, RefreshDatabase, migrate:fresh hoặc xóa dữ liệu thử nghiệm.

SQLite hiện hữu có thể giữ cho test nhanh. Muốn kiểm nghiệp vụ PostgreSQL, tạo suite/cấu hình bootstrap riêng có guard kiểm target test; không bỏ guard hiện tại rồi trỏ vào DB vận hành.

| Case | Thao tác | Bằng chứng cần có |
|---|---|---|
| Đơn thuê hoàn chỉnh | Tạo khách test → chọn xe đủ điều kiện → ngày/giá → cọc → bắt đầu thuê → trả xe | ID đơn/xe test, trạng thái trước/sau, tổng tiền, cọc, hoàn cọc, giao dịch DB và UI khớp |
| Gia hạn và trả sớm/quá hạn | Chạy các luồng hiện hữu trong hệ thống | Kết quả tính tiền xác định trước theo nghiệp vụ thực; không chỉ so giá trị gán vào model |
| Thu chi/quỹ | Tạo phiếu test, sửa/hủy nếu được phép | Số dư trước/sau, báo cáo cùng chi nhánh và ngày khớp; thao tác gửi lặp không ghi tiền hai lần |
| Quyền | Staff A truy cập record/chi nhánh B bằng URL/API; Role 4 thử API hạn chế | Mã lỗi đúng theo policy; không đọc hoặc sửa trái quyền |
| Validation/transaction | Dữ liệu thiếu, xe không đủ điều kiện, request lỗi giữa bước | Lỗi đúng field; DB không lưu nửa chừng; retry không nhân đôi bản ghi |
| Export | Xuất đơn/thu chi theo bộ lọc | File mở được; số dòng, số tiền và phạm vi đúng |
| CRUD còn lại | Xe, khách, lead, bảo dưỡng, user/bảng giá theo ma trận | Tạo/sửa/lưu/quay lại thấy dữ liệu đúng; luồng không có trong sản phẩm không được tự bịa thêm |

Các case cốt lõi đơn/cọc/trả xe/quỹ/phân quyền phải có test hồi quy tự động. Người vận hành kiểm thêm thao tác UI thật bằng dataset tổng hợp.

Điều kiện đạt: số tiền và quyền đúng, có log/chứng cứ. GET danh sách thành công và 122 migration Yes không thay thế phần này.

## 4. Chạy browser qua frontend và API release thật

1. Build frontend bằng API URL của môi trường test riêng, dùng commit release đã chốt.
2. Browser E2E không route.fulfill/intercept API thành fixture trong suite này.
3. Kiểm đăng nhập/logout, Dashboard→Xe→Khách→Đơn→Báo cáo, Back/Forward, refresh deep-link, filter/page/scroll, form validation và hết phiên.
4. Kiểm responsive từng module ở năm viewport đã thống nhất; suite hiện hữu không đủ nếu chỉ đo Dashboard.
5. Đếm document requests khi chuyển menu; xác nhận dữ liệu thật của test DB hiện đúng trong UI.
6. Đo Dashboard/list ở cold start và warm request riêng. Điều tra query trùng/N+1, request auth mỗi route và render nếu latency cao; không thêm cache làm sai số liệu để đạt tốc độ.
7. Kiểm sau thao tác lưu: list/KPI được invalidation đúng, không cần chờ TTL mới thấy cập nhật.

Điều kiện đạt: UI release hoạt động với API release thật, không lỗi JS/CORS và đúng trạng thái. Báo số đo latency, không kết luận “mượt” chỉ vì test không ném exception.

## 5. Chuẩn bị vận hành và mở thử có kiểm soát

Trước go-live:
- Chủ dự án xác nhận phạm vi chức năng, tài khoản/phân quyền và chi nhánh áp dụng trước.
- Sao lưu database, kiểm khả năng khôi phục vào DB riêng; ghi người chịu trách nhiệm, lịch sao lưu và thời gian lưu bản backup. Không chỉ có script chưa từng thử restore.
- Đổi các credential đã từng lộ trong chat/script và cập nhật service phụ thuộc theo lịch tránh gián đoạn; không đưa secret vào báo cáo.
- Xác minh cấu hình runtime: API URL, CORS, APP_DEBUG, schema, JWT/session, rewrite và tình trạng migrations pending.
- Kiểm `RUN_MIGRATIONS` và nhánh auto-deploy thực tế trước push. Release UI không yêu cầu reset schema hoặc tạo APP_KEY/JWT_SECRET mới.
- Lưu commit deploy trước và cách redeploy rollback. Không tự rollback database chỉ vì rollback frontend.
- Phân công người kiểm dashboard/health, lỗi HTTP, DB capacity và phản hồi nhân viên. Chốt nơi báo lỗi và cách xử lý sự cố; không tự gửi thông báo ra ngoài.
- Kiểm cấu hình hosting đang dùng có đáp ứng giờ làm việc và thời gian phản hồi đã thống nhất. Nếu cần đổi gói/dịch vụ, báo phương án/chi phí để chủ dự án quyết định, không tự mua.

Mở thử đề xuất:
1. Sau khi mục 1–4 đạt, phát hành đúng commit được duyệt.
2. Một nhóm nhân viên/chi nhánh vận hành thử trong khoảng thời gian chủ dự án thống nhất; không tự tạo giao dịch tiền thật chỉ để test.
3. Cuối mỗi ca đối chiếu đơn, trạng thái xe, cọc, thu chi và số dư; tránh nhập trùng cùng giao dịch ở hai hệ thống.
4. Dừng mở rộng nếu sai tiền, sai quyền, mất dữ liệu hoặc lỗi chặn thuê/trả xe; dùng phương án rollback đã chuẩn bị.
5. Khi số liệu khớp và không còn lỗi chặn, chủ dự án ký nghiệm thu rồi mở rộng toàn bộ nhân viên.

## Checklist cho phép mở chính thức

- [ ] Xác nhận commit đang chạy trên cả hai service.
- [ ] Cache A→logout→B và request cũ được kiểm thử đầy đủ.
- [ ] Thuê/cọc/trả xe/thu chi và phân quyền đã đạt trên PostgreSQL test riêng.
- [ ] Browser E2E qua API thật, export và responsive các trang chính đạt.
- [ ] Tài liệu route/version/nghiệm thu đúng thực tế; không còn ghi “100%” cho mục chưa kiểm.
- [ ] Backup đã thử restore, có rollback và người phụ trách vận hành.
- [ ] Vận hành thử được đối soát và chấp nhận.

## Prompt giao cho AI khác

```text
Tiếp tục HIMOTO từ HEAD hiện tại; đọc docs/himoto-go-live-remaining.md.
Không làm lại A–G từ đầu và không kết luận fce6a60 đã hoàn thành mọi giai đoạn.
Làm theo mục 1→5; ưu tiên sửa test cache false-pass, kiểm User B login/request cũ,
sửa version/route matrix và triển khai các test nghiệp vụ tiền/quyền trên PostgreSQL test riêng.
12 PHPUnit tests hiện tại đạt nhưng chủ yếu kiểm cấu trúc, chưa phải rental lifecycle.
Không chạy test ghi/xóa trên database đang vận hành, không dùng credential lấy bằng cách đoán.
Hoàn thiện local/test độc lập trước; nếu thiếu quyền deployment/test DB thì báo rõ đầu vào thiếu.
Chỉ triển khai đúng commit khi được chủ dự án ủy quyền. Cuối cùng bàn giao kết quả test,
commit live, backup/rollback và checklist chạy thử; không đổi tên smoke test thành nghiệm thu toàn diện.
```
