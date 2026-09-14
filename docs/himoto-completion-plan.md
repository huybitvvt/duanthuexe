# HIMOTO — Kế hoạch chốt sản phẩm và bàn giao

## 1. Kết luận và phạm vi

Mốc đối chiếu: nhánh `feature/himoto-complete-integration`, HEAD khi lập kế hoạch là `284abfa`. Không làm lại prototype, bộ logo, Layout và các sửa contract đã đạt.

Đã có:
- Prototype HTML và nhận diện HIMOTO.
- Layout/sidebar/header/dashboard/drawer, chuyển menu SPA, skeleton và một phần cache.
- Production build đã chạy thành công trong các lượt trước.
- Log do người dùng cung cấp: 122 migration `Yes`, không có `No`.
- Health API đã được kiểm tra trực tiếp trong lượt trước, HTTP 200 và database `ok`.
- Báo cáo mới và `tests/live-staging-smoke-results.json` ghi 13/13 kiểm tra API thật đạt. Lượt lập kế hoạch này đọc bằng chứng đã lưu, không chạy lại đăng nhập.

Chưa đủ bằng chứng để kết luận hoàn tất sản phẩm:
- `tests/Unit/ExampleTest.php` chỉ `assertTrue(true)`; `tests/Feature/ExampleTest.php` chỉ kiểm GET `/` trả 200. Hai test này không kiểm nghiệp vụ.
- Smoke test kiểm HTTP và một số dạng danh sách, chưa kiểm toàn bộ field, số tiền, quyền, tạo/sửa/trả xe hoặc giao dịch.
- Script SPA ghi nhiều cờ FAIL vào JSON nhưng cuối file chỉ in kết quả; có thể thoát 0 dù các cờ này sai.
- Chưa đối chiếu commit backend/frontend thực sự đang được Render phục vụ với commit đã kiểm thử local.
- Tên service có chữ staging trong báo cáo không chứng minh database tách biệt. Xem endpoint hiện hữu là dữ liệu vận hành cho tới khi xác nhận được môi trường riêng.

Mục tiêu bản bàn giao: giao diện HIMOTO thống nhất cho những chức năng hiện hữu, chuyển trang không tải lại tài liệu, giữ trạng thái hợp lý, nghiệp vụ và quyền đúng, có bằng chứng trên bản đã triển khai. Không tự thêm tính năng mới chỉ để khớp hình minh họa prototype; KPI chưa có dữ liệu thật phải được bỏ/ghi rõ thay vì tạo số giả.

## 2. Quy tắc thực hiện

1. Đọc `AGENTS.md` áp dụng cho repo; kiểm tra `git status`, branch và commit trước khi sửa. Giữ nguyên thay đổi của người khác.
2. Không tự `git pull`, merge `main` hoặc push nhánh đang auto-deploy trước khi biết nhánh triển khai của Render.
3. Làm và kiểm thử từng nhóm thay đổi; không nâng framework, đổi database hay refactor toàn bộ dự án trong đợt chốt giao diện.
4. Không in token/mật khẩu/connection string vào log, JSON hay commit. Không truy vấn hàng loạt user để tìm hoặc đoán mật khẩu.
5. Kiểm thử trên hệ thống đang vận hành chỉ gồm health, đăng nhập bằng tài khoản được cấp và đọc danh sách có giới hạn. Kiểm thử ghi/xóa/thu tiền chỉ trên database test riêng có dữ liệu tổng hợp.
6. Không chạy `migrate:fresh`, `db:wipe`, restore hoặc seeder thử nghiệm trên Supabase vận hành. Không sửa file trong `vendor/` để làm test xanh.
7. Mỗi bằng chứng phải ghi commit, môi trường, thời điểm, lệnh, exit code, loại dữ liệu mock/live. Không suy diễn `200 OK` thành nghiệm thu toàn bộ nghiệp vụ.

## 3. Giai đoạn A — Khóa phạm vi và xác định bản đang chạy

Thực hiện trước mọi deployment:

```powershell
cd E:\duanthuexe\happyride-1.1
git status --short
git branch --show-current
git log -5 --oneline
```

- Đọc router, sidebar và prototype; lập `docs/himoto-release-matrix.md`.
- Mỗi route ghi: component thật, quyền hiện hữu, API, màn hình prototype tương ứng, trạng thái giao diện, chức năng còn thiếu, test chứng minh. Không sử dụng tên route ví dụ từ kế hoạch cũ nếu router không có.
- Bao phủ đơn thuê, xe, khách, lead, bảo dưỡng; đồng thời kiểm kê cửa hàng, người dùng/quyền, bảng giá, thu chi/giao dịch, ngân hàng/tiền mặt, báo cáo và các form con thực sự có trong router.
- Kiểm tra Render service `himoto-api` và `himoto-web`: repository, deploy branch, commit gần nhất, API URL frontend và rewrite route. Không cần Render Shell để lấy thông tin deployment.
- Nếu không truy cập được Render Dashboard, ghi mục này chờ chủ dự án cung cấp commit deploy; tiếp tục các công việc local độc lập.
- Có thể sinh `version.json` chứa commit/build time lúc build frontend, không chứa environment secrets. Backend chỉ cần commit từ deploy log hoặc endpoint version tối giản nếu thực sự cần.
- Ghi rõ frontend/API có dùng cùng database vận hành hay môi trường thử nghiệm riêng.

Đầu ra: ma trận route và thông tin phiên bản. Điều kiện đạt: biết rõ cái gì cần bàn giao và bản nào đang được kiểm tra.

## 4. Giai đoạn B — Sửa độ tin cậy của kiểm thử

Files chính: `tests/verify_spa_and_modules.py`, `tests/test_staging_smoke.py`, `docs/himoto-testing.md`.

### B1. Kiểm thử phải trả mã lỗi khi thất bại

- Thêm hàm tổng hợp tất cả điều kiện bắt buộc: từng transition, URL, Back/Forward, endpoint, dữ liệu từng module, cache, retry, responsive và page errors.
- FAIL bất kỳ điều kiện nào phải exit 1; exit 0 chỉ khi tất cả điều kiện bắt buộc đạt. Không chỉ dựa vào `all_modules_functional` hoặc `all_endpoints_valid` hiện hữu.
- Giữ JSON và ảnh lỗi ngay cả khi một assertion thất bại; ghi `complete: false` nếu suite dừng giữa chừng.
- Thêm test cho hàm tổng hợp với một cờ false để chứng minh không còn báo xanh sai.
- Hỗ trợ `--base-url` và `--output-dir`; bỏ phụ thuộc đường dẫn máy cá nhân. Đưa script cần thiết vào `tests/`, cập nhật tài liệu theo vị trí thực tế.

### B2. Fixture bám backend

- Mock theo method + path; endpoint chưa khai báo phải được ghi nhận và fail, không tự trả `{data: []}` cho mọi request.
- Đối chiếu `routes/api.php`, controller/resource/service. Đặc biệt báo cáo đơn dùng các endpoint trong Vuex thực tế, không giả định `/order/car-rental/report`.
- Test phân trang phải có tối thiểu 2 trang; fixture phải thay đổi dữ liệu theo `page` và filter. Kiểm tra tên, biển số, ngày và tiền riêng biệt, tránh `A hoặc B` cho phép thiếu field vẫn PASS.
- Back/Forward kiểm cả URL, filter, page, nội dung và số document request; biến trên `window` chỉ chứng minh tài liệu không reload. Nếu cần chứng minh Layout không remount, theo dõi instance/DOM Layout riêng.
- Responsive chạy từng route chính với dữ liệu có thật trong fixture ở 360/390/768/1024/1440px; không chỉ đo Dashboard.

### B3. Smoke test live

- Bỏ mật khẩu mặc định `secret`; nhập ẩn bằng `getpass` hoặc đọc biến môi trường được cấp. Không in response login nguyên văn khi lỗi.
- Tự ghi JSON kết quả từ chương trình: commit local, URL, HTTP, latency, assertion, tổng pass/fail. Không tạo tay báo cáo thay cho output thực thi.
- Health kiểm `status`, `service`, `database`; verify-token kiểm user hợp lệ; list kiểm cấu trúc và metadata cần thiết. Không lưu hồ sơ khách hàng thật vào artifact.
- Bổ sung `/dashboard/report-chart` và list cash/banks đang dùng ở UI; `/all` không thay thế hoàn toàn list có phân trang.
- Phân biệt kiểm thử đọc API thành công với kiểm thử giao diện/nghiệp vụ. Smoke test Python không chứng minh trình duyệt không có runtime error.

Điều kiện đạt: cố ý làm hỏng một assertion thì suite FAIL; fixture đúng contract; log tự sinh và không chứa credential.

## 5. Giai đoạn C — Hoàn thiện cache, điều hướng và trạng thái lỗi

Các điểm cụ thể cần xử lý hoặc tái hiện trước khi quyết định sửa:

### C1. Cache đúng người dùng và chi nhánh

- `dashboard.module.js` hiện cache chỉ theo `store_id`; `PURGE_AUTH` hiện không xóa dashboard/store cache. Thêm reset khi logout, phiên hết hạn hoặc đổi tài khoản; hủy/không nhận response thuộc phiên cũ.
- Cache key phải đủ danh tính/quyền/phạm vi cửa hàng và filter ảnh hưởng dữ liệu. Không cache lỗi 401/403.
- Test cùng browser: Admin A xem Dashboard → logout → Staff B đăng nhập trong TTL. Không được thấy dữ liệu A, kể cả trong lúc request B đang chờ.
- Test chuyển nhanh chi nhánh A→B với response A chậm hơn B. UI phải giữ B; có thể dùng request sequence hoặc cancellation.
- Sau khi tạo/sửa đơn, thu/chi, sửa xe, phải invalidation dữ liệu liên quan hoặc refresh có kiểm soát. Không chờ TTL mới thấy thay đổi vừa lưu.

### C2. Chống gọi trùng và kiểm TTL thật

- Các component dùng cả `mounted()`/`created()` và `activated()` có thể gọi request hai lần lúc vào đầu tiên vì `lastFetchedAt=0`. Dùng một hàm `ensureLoaded`/in-flight guard và đầu mối gọi nhất quán.
- Test lần đầu: một request list cho một bộ query, không chỉ so sánh hai request trước và sau chuyển tab.
- Test quay lại trong TTL không refetch; hết TTL refetch đúng một lần. Dùng clock điều khiển hoặc fixture timestamp để tránh đợi một phút mỗi case.
- `Layout.cachedViews` chưa có Cash, Bank, MaintenanceSchedule dù các trang đó có `activated()` TTL. Quyết định có cache từng trang hay không; cấu hình và báo cáo phải nhất quán. Không bắt buộc cache tất cả.
- Dashboard có TTL trong Vuex nhưng cần kiểm việc quay lại trang cache có thực sự gọi `ensureLoaded` để dữ liệu hết hạn được cập nhật.

### C3. URL và view state

- Chuẩn hóa query gồm trang, keyword/name, status, store, date range, view mode theo từng endpoint.
- Query URL thay đổi ngay trong cùng route phải cập nhật dữ liệu; chỉ `activated()` không bao phủ tất cả thay đổi query khi component đang active.
- Tránh vòng lặp watcher → router.push → watcher. Dùng replace cho chỉnh filter nếu không muốn mỗi ký tự tạo history entry.
- Giữ scroll của đúng container cuộn; keep-alive tự nó không đảm bảo scroll được phục hồi.
- Case bắt buộc: list trang 2 + filter → mở form → quay lại; Back/Forward qua hai filter; đổi grid/table rồi refresh.

### C4. API lỗi phải có hành động phục hồi

- Những `.catch(() => {})` thêm vào report/store/source cần đổi thành trạng thái có ý nghĩa nếu dữ liệu đó bắt buộc cho màn hình hoặc form.
- Không hiện KPI 0 như dữ liệu hợp lệ khi report lỗi. Giữ dữ liệu cũ chỉ khi có nhãn đang cũ/không cập nhật được; retry phải gọi đúng request.
- Form thiếu danh sách xe/cửa hàng do lỗi API phải báo lỗi và cho tải lại, không cho gửi dữ liệu sai.
- Test 401, 403, 422, 500, mạng mất và retry; 422 phải gắn đúng field. Lỗi phụ không được khóa vĩnh viễn nút tìm kiếm.

Điều kiện đạt: cache đúng scope, navigation giữ trạng thái, không có request trùng khi test, lỗi có phục hồi. Lưu kết quả regression riêng.

## 6. Giai đoạn D — Chốt giao diện các module còn thiếu

- Dùng ma trận A để xác định module chưa đạt; không coi thêm skeleton là đã redesign toàn màn hình.
- Đồng bộ title, filter bar, table/card, pagination, form, modal, drawer, trạng thái rỗng/lỗi theo token HIMOTO.
- Kiểm tra nút mới có đúng hành vi: ví dụ “Thuê” trên card xe phải mở luồng tạo đơn với xe đã chọn nếu đó là ý định, không chỉ đổi URL rồi bỏ qua `vehicle_id`.
- Chỉ dùng trạng thái nghiệp vụ backend đang có. Không thêm `renting` vào domain xe nếu backend chỉ hỗ trợ `using`; adapter hiển thị phải bám model.
- Các form tạo/sửa, bảng giá, user/quyền, thu chi, báo cáo phải được kiểm tra riêng theo route thật.
- Không tạo tính năng xuất PDF/calendar/công nợ mới chỉ vì từng được đề xuất trong concept. Ghi rõ chức năng thực sự thuộc bản bàn giao; nếu nút hiển thị thì phải hoạt động.
- Kiểm tra ảnh xe không có ảnh, ảnh lỗi, chữ dài, giá trị 0, bảng nhiều dòng, mobile keyboard, sidebar đóng/mở, focus drawer và độ tương phản.

Đầu ra: ảnh desktop/mobile cho từng module sửa, chức năng và giới hạn rõ ràng. Điều kiện đạt: không còn nút chết hoặc màn hình quan trọng ngoài ngôn ngữ thiết kế đã chọn.

## 7. Giai đoạn E — Kiểm thử nghiệp vụ và quyền trên database riêng

### E1. Tạo môi trường test tái lập

- Dùng PostgreSQL test riêng phù hợp production và schema test. Không chạy RefreshDatabase lên kết nối Supabase hiện đang vận hành.
- Cấu hình `.env.testing`/CI secrets; bootstrap phải từ chối nếu target test trùng database production đã cấu hình.
- Dùng dữ liệu tổng hợp: hai cửa hàng A/B, Admin, Staff A/B, Role 4, khách test, xe sẵn sàng/đang thuê/đang sửa, bảng giá và tài khoản quỹ.
- Cài Composer dev dependencies theo lock trong môi trường test. Dockerfile production hiện `--no-dev`; không kỳ vọng container đó luôn có PHPUnit.
- Chạy migration với test DB mới; lỗi migration phải được sửa theo PostgreSQL test và đối chiếu production, không đánh dấu migration đã chạy bằng tay để né lỗi.
- Dùng route:list/migrate:status làm bằng chứng phụ; trạng thái Yes không chứng minh từng cột/index/số liệu đúng.

### E2. Bộ test tối thiểu

| Luồng | Thực hiện | Assertion bắt buộc |
|---|---|---|
| Khách hàng | Tạo → sửa → tìm kiếm → trang 2 | Field, validation, nội dung thay đổi đúng; policy trùng định danh theo backend |
| Xe | Tạo/cập nhật xe test → grid/table → chọn thuê | Biển số/ODO/store/status đúng; xe không đủ điều kiện bị chặn theo nghiệp vụ |
| Đơn thuê | Chọn khách/xe → ngày thuê → báo giá → đặt cọc → bắt đầu → gia hạn nếu có → trả xe | Giá, tiền cọc, tổng thu, hoàn cọc, trạng thái xe/đơn và giao dịch liên quan đối soát đúng |
| Thu chi | Tạo giao dịch test → sửa/hủy theo chức năng có sẵn | Số dư trước/sau và báo cáo khớp, không ghi hai lần khi gửi lại |
| Bảo dưỡng | Tạo lịch → mở/sửa/xử lý theo API hiện có | Đúng xe, loại, ngày; lỗi 422/403 không làm mất dữ liệu |
| Lead | Tạo/cập nhật → phân công → chuyển đơn nếu được cấp quyền | Trạng thái và liên kết đơn đúng, role hạn chế không gọi được thao tác trái quyền |
| Export | Xuất theo filter hiện hữu | File mở được, cột và dòng đúng scope, không chỉ HTTP 200 |

- Với số tiền: dùng ví dụ nhỏ xác định trước kết quả theo công thức hiện hữu; không suy ra lợi nhuận chỉ từ tổng thu. Đối chiếu UI → API → giao dịch DB.
- Kiểm quyền trên API trực tiếp và ID record, không chỉ ẩn menu: Staff A không đọc/sửa record B bằng đổi ID hoặc `store_id`; Role 4 nhận đúng hành vi endpoint được/không được cấp.
- Đối chiếu policy trước khi viết assertion; không tự siết hay nới quyền khác nghiệp vụ đã thống nhất.
- Sau mutation lỗi, kiểm DB không bị lưu nửa chừng. Với giao dịch nhiều bước, kiểm rollback transaction.

Đầu ra: Feature tests trong `tests/Feature/Himoto/`, E2E theo module, fixture/seed test và báo cáo tự sinh. Không dùng hai ExampleTest làm điều kiện nghiệm thu backend.

## 8. Giai đoạn F — Hiệu năng và browser E2E qua API thật

- Báo cáo mới đo Dashboard khoảng 9.57 giây và Orders 5.23 giây. Đây là một lần đo, chưa đủ kết luận nguyên nhân.
- Đo riêng cold start và các request warm có giới hạn; ghi timestamp, count, p50/p95, scope và kích thước payload. Không load test hệ thống vận hành.
- Tách thời gian auth guard, query DB, API và render. Kiểm `VERIFY_AUTH` mỗi route, query trùng, N+1, tính tổng toàn bộ dữ liệu và ảnh trả dư.
- Dùng EXPLAIN/query profiling trên test DB; chỉ thêm index sau khi có bằng chứng, kế hoạch migration và đánh giá thời gian khóa bảng.
- Mục tiêu đề xuất trong môi trường test ổn định: click menu phản hồi trực quan trong 200ms; list warm p95 ≤2 giây, dashboard warm p95 ≤3 giây. Đây là mục tiêu nghiệm thu đề xuất, không phải số đã đo được. Nếu chưa đạt, báo số thực tế và giới hạn trước khi chủ dự án quyết định phát hành.
- Browser E2E chạy bản frontend release kết nối backend release thật: login → Dashboard → Xe → Khách → Đơn → báo cáo → logout; không intercept `/api/**` ở suite này.
- Kiểm CORS, JWT, refresh deep-link, Back/Forward, bộ lọc, mobile và lỗi phiên hết hạn. Ảnh/log dùng dữ liệu test, che thông tin cá nhân nếu chụp môi trường có dữ liệu thật.

Điều kiện đạt: luồng browser thật hoạt động với phiên bản đã xác định, kết quả số liệu đúng và latency được đo minh bạch.

## 9. Giai đoạn G — Release và bàn giao

### G1. Chuẩn bị bản release trước khi xin deploy

```powershell
git diff --check
npm ci
npm run build:static
.\php.cmd artisan route:list --path=api
.\php.cmd artisan test
python tests\verify_spa_and_modules.py
```

- Test PHP phải dùng test DB đã cô lập. Bổ sung lệnh E2E mới vào runbook, không copy lệnh placeholder như thể đã chạy.
- Script static có thao tác dọn thư mục: chỉ chạy khi xác nhận output đúng `happyride-1.1/static-dist`.
- Smoke API thật chạy với credential nhập ẩn và giới hạn request; migrate:status chỉ đọc. Không tự chạy migrate production vì tên giai đoạn là nghiệm thu.
- Lưu commit, build hash, phiên bản PHP/Node, log build/test, report thiếu sót và ảnh.
- Kiểm lại credential từng xuất hiện trong chat/script đã được chủ dự án thay, cập nhật đồng bộ service phụ thuộc; không tự rotate khi chưa có kế hoạch để tránh gián đoạn.
- Đánh giá dependency/runtime hiện tại; tách kế hoạch nâng cấp framework khỏi đợt giao diện. Nếu phát hiện lỗi nghiêm trọng trong phạm vi xử lý dữ liệu/auth, phải xử lý hoặc ghi quyết định chấp nhận rủi ro rõ ràng.

### G2. Triển khai có kiểm soát

- Chuẩn bị PR với commit, phạm vi, bằng chứng và rollback cụ thể. User duyệt bản này trước merge/push vào nhánh kích hoạt deploy nếu phiên làm việc chưa có ủy quyền deploy.
- Render đang cấu hình auto-deploy và `RUN_MIGRATIONS=true` trong blueprint; kiểm biến thực tế và migration pending trước release. Không đổi APP_KEY/JWT_SECRET chỉ vì rebuild UI.
- Có backup hợp lệ và thử restore trên DB riêng trước khi áp dụng thay đổi DB. Release chỉ UI không cần dựng lại schema.
- Triển khai staging riêng, test lại; sau khi được phép mới triển khai production đúng commit.
- Sau deploy xác minh commit frontend/backend, health, login, route thật, một luồng đọc nghiệp vụ và số liệu. Không dùng kết quả mock local thay thế.
- Rollback ứng dụng về commit trước đã biết. Rollback database cần kế hoạch riêng; không tự `migrate:rollback` trên dữ liệu vận hành.

### G3. Bàn giao

- `docs/himoto-release-matrix.md`: route/chức năng/quyền đã nghiệm thu.
- `docs/himoto-testing.md`: lệnh tái lập, môi trường test riêng, đường dẫn trong repo, không hướng dẫn Render Free Shell như phương án bắt buộc.
- `docs/himoto-user-guide.md`: login, tạo đơn, quản lý xe/khách, xử lý bảo dưỡng, thu chi và xuất báo cáo.
- `docs/himoto-release-acceptance.md`: commit deploy, kết quả từng suite, giới hạn còn lại và hướng rollback.
- Hướng dẫn backup, xem lỗi và người chịu trách nhiệm vận hành. Theo dõi sau deploy có thời hạn được thống nhất, không tự tạo job nền dài hạn.

## 10. Định nghĩa hoàn thành

- [ ] Ma trận route đã đối chiếu prototype và nghiệp vụ hiện hữu; không có nút chính chưa hoạt động.
- [ ] Cache không lẫn user/store, không request trùng ban đầu, TTL và invalidation được test.
- [ ] Filter/page/view/scroll và Back/Forward hoạt động đúng.
- [ ] Suite tự fail bằng exit code, output tự sinh, không chứa secret.
- [ ] Test backend có nghiệp vụ thực, phân quyền và transaction trên DB test riêng.
- [ ] Các luồng tạo/sửa/trả xe/thu chi/export trong phạm vi đã đạt đối soát.
- [ ] Browser thật kết nối API thật, đúng commit, không dùng mock để kết luận live.
- [ ] Responsive từng module và error recovery đã kiểm tra.
- [ ] Build/test pass, thời gian đáp ứng có số đo và giới hạn được chấp nhận.
- [ ] Release đúng commit, backup/rollback, smoke sau deploy và tài liệu bàn giao hoàn tất.

Không cần mở thêm vòng audit nếu tất cả điều kiện đã đạt và không có bằng chứng lỗi mới. Nếu chỉ bị thiếu quyền Render hoặc DB test, hoàn thiện phần local độc lập và ghi đúng đầu vào cần người dùng cung cấp.

## 11. Prompt giao cho AI thực hiện

```text
Tiếp tục hoàn thiện HIMOTO tại E:\duanthuexe\happyride-1.1.
Đọc docs/himoto-completion-plan.md và thực hiện tuần tự A→G.
Mốc kế hoạch là 284abfa; luôn kiểm tra HEAD hiện tại, không reset về mốc cũ.

Không làm lại logo/prototype/Layout hoặc phá các sửa contract đã đạt.
Ưu tiên: xác định commit đang deploy; sửa test có FAIL vẫn exit 0;
cache đúng user/store và invalidation; query navigation; UI module còn thiếu;
backend/E2E nghiệp vụ trên database test riêng; đo hiệu năng; release/bàn giao.

13/13 live smoke là bằng chứng đọc API, không phải hoàn thành mọi nghiệp vụ.
Hai ExampleTest hiện tại không đủ; viết test cho hành vi thực tế cần bảo toàn.
Không dùng database vận hành cho test ghi/xóa hoặc RefreshDatabase.
Không in/commit mật khẩu, token, hồ sơ khách thật. Không đoán credential.
Không chỉnh vendor. Không tự push/merge vào nhánh auto-deploy nếu chưa được phép.

Làm và kiểm thử từng nhóm thay đổi, cập nhật bằng chứng tự sinh theo commit.
Khi thiếu quyền ngoài repo, vẫn hoàn thiện phần độc lập rồi báo chính xác đầu vào thiếu.
Kết quả cuối phải có bản chạy được, ma trận nghiệm thu, test/log, commit release,
hướng dẫn sử dụng và rollback; không kết thúc chỉ bằng lời đề nghị làm tiếp.
```
