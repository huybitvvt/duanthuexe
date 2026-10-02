# Kế hoạch chuyển HIMOTO sang Next.js + Node.js, giữ nguyên bản 2b49116

Ngày lập: 20/09/2026. Trạng thái: kế hoạch triển khai; chưa thực hiện chuyển đổi ứng dụng.

## 1. Mục tiêu và mốc đối chiếu

Chuyển frontend Vue 2 sang Next.js/React và backend Laravel sang Node.js. Kết quả phải giữ nguyên giao diện, chức năng, thao tác, dữ liệu và kết quả nghiệp vụ của bản hiện tại. Yêu cầu này bao gồm cả các màn hình phụ, popup, phân quyền, báo cáo, file xuất và công việc chạy nền.

Mốc cố định: `2b4911645cdeb388e43c8d3210d8eb0c48bac93a` (`2b49116`). Không tự chuyển mốc sang commit mới hơn trong quá trình thực hiện.

Đã kiểm tra khi lập kế hoạch:

| Nguồn | Kết quả |
| --- | --- |
| HEAD local, nhánh `main` | `2b4911645cdeb388e43c8d3210d8eb0c48bac93a` |
| GitHub `origin/main`, qua `git ls-remote` | Cùng commit trên |
| [Metadata frontend live](https://himoto-web.onrender.com/version.json) | HTTP 200, cùng commit; thời điểm build `2026-09-20T08:29:45.679Z` |
| [Health backend live](https://himoto-api.onrender.com/api/health) | HTTP 200, `status=ok`, `database=ok`, cùng commit |
| [Trang web đối chiếu](https://himoto-web.onrender.com) | HTTP 200 |

Các kiểm tra trên xác nhận mã nguồn và metadata phiên bản đang thống nhất. Chưa đăng nhập kiểm hết màn hình, chưa chạy giao dịch thử, chưa kiểm schema live, chưa đo hiệu năng và chưa chứng minh mọi chức năng hiện tại đều hoạt động đúng. Snapshot giao diện sau đăng nhập là công việc bắt buộc ở bước P0.

Không dùng bản `himoto-demo-release` hoặc tài liệu nghiệm thu của commit cũ để thay thế baseline này. Những nhận xét PASS/BLOCKED trong tài liệu cũ phải được kiểm lại trước khi áp dụng cho `2b49116`.

## 2. Định nghĩa “giống hệt” để nghiệm thu

| Phạm vi | Điều kiện phải giữ |
| --- | --- |
| Giao diện | Logo, font, icon, màu, kích thước, khoảng cách, đường viền, sidebar, header, bố cục, thứ tự menu, bảng, thẻ và biểu đồ |
| Thao tác | Nút, popup/drawer, tab, trường nhập, giá trị mặc định, validation, thông báo, xác nhận, tìm kiếm, bộ lọc, phân trang và cuộn |
| Điều hướng | URL hiện tại, deep link, refresh, Back/Forward, redirect, trang 403/404, điều hướng theo vai trò |
| Phiên và quyền | Đăng nhập/đăng xuất, đổi mật khẩu, hết phiên, tài khoản bị khóa, quyền từng thao tác và phạm vi chi nhánh |
| Nghiệp vụ | Trạng thái xe/đơn, cọc, gia hạn, trả sớm/muộn, hoàn cọc, công nợ, số dư, kế toán và số liệu báo cáo |
| Dữ liệu | ID, mã hợp đồng, liên kết, ảnh/tệp, lịch sử, bản ghi đã xóa mềm, dấu thời gian và snapshot hợp đồng |
| File | Mẫu in, PDF, tiếng Việt, logo, khổ giấy, hướng giấy, ngắt trang; cột, thứ tự, kiểu dữ liệu và tổng tiền trong Excel |
| Tích hợp | Upload ảnh, đồng bộ lead, GPS, nhắc nợ, webhook, lịch chạy và trạng thái bật/tắt của provider |
| Thiết bị | Hành vi responsive của bản cũ trên các viewport được chốt; gồm sidebar thu gọn và menu điện thoại |

Không đổi thiết kế hoặc thay component bằng giao diện mặc định của thư viện mới. Mỗi khác biệt quan sát được phải được sửa hoặc ghi rõ thành ngoại lệ được chủ hệ thống chấp nhận; không dùng từ “tương đương” để bỏ qua chi tiết.

So ảnh trên cùng trình duyệt, phiên bản, hệ điều hành, viewport, font, dataset và thời gian cố định. Chỉ cho phép dung sai raster rất nhỏ do khử răng cưa sau khi xem ảnh diff; không dùng một tỷ lệ sai khác chung để che lỗi bố cục/chữ/nút. Không thể bảo đảm ảnh giống từng pixel giữa hai môi trường render khác nhau.

Chức năng đang tắt, thiếu provider hoặc chưa đủ schema phải giữ đúng trạng thái và thông báo thực tế. Không tự bật tính năng mới để tính là “đã chuyển”. Lỗi cũ được ghi riêng với bằng chứng; lỗi liên quan tiền/quyền phải được xử lý hoặc chốt cách xử lý trước vận hành, không âm thầm sao chép hay âm thầm đổi quy tắc.

## 3. Kiến trúc đề xuất

- Frontend: Next.js App Router, React, TypeScript; dùng lại tài sản thương hiệu và chuyển CSS/SCSS hiện hữu có kiểm soát.
- Backend: Node.js 24 LTS, NestJS + TypeScript, chia controller/service/repository theo nghiệp vụ. Chốt phiên bản vá và lockfile khi bắt đầu.
- Database: tiếp tục Supabase PostgreSQL, schema `himoto`; giữ cấu trúc và ID hiện tại trong đợt chuyển đổi.
- Truy cập DB: `pg` với connection pool, SQL có tham số và repository có kiểu dữ liệu. Mục tiêu là kiểm soát truy vấn/transaction tương ứng Laravel, tránh cơ chế tự đồng bộ schema.
- API: giữ URL, HTTP method, tên trường, kiểu dữ liệu, phân trang, mã lỗi và định dạng file cũ; tài liệu OpenAPI mô tả đúng hợp đồng này.
- Kiểm thử: test nghiệp vụ Node, kiểm thử tích hợp trên PostgreSQL riêng, Playwright đối chiếu giao diện và thao tác.

Node.js 24 hiện thuộc nhánh LTS; máy local có Node `v24.14.0`. [Lịch phát hành Node.js](https://nodejs.org/en/about/previous-releases). Next.js hỗ trợ App Router/TypeScript; NestJS cung cấp cấu trúc module/controller/service cho Node. [Next.js](https://nextjs.org/docs/app/getting-started/installation), [NestJS](https://docs.nestjs.com/). SQL truyền tham số theo API của [node-postgres](https://node-postgres.com/features/queries).

Lộ trình kỹ thuật:

```text
Baseline:      Vue hiện tại    -> Laravel hiện tại -> PostgreSQL
Đối chiếu FE:  Next.js mới     -> Laravel đối chiếu -> PostgreSQL test
Đối chiếu BE:  Next.js mới     -> Node.js mới      -> PostgreSQL test tương ứng
Hoàn tất:      Next.js mới     -> Node.js mới      -> PostgreSQL vận hành
```

Trong quá trình phát triển, API client chọn backend bằng cấu hình trên môi trường test. Nếu cần chia endpoint qua bộ định tuyến, dùng danh sách tường minh; lỗi ở Node không tự động gọi lại Laravel, đặc biệt với request ghi dữ liệu. Mỗi luồng giao dịch có đúng một backend chịu trách nhiệm ghi.

Đích cuối không còn phụ thuộc Laravel để xử lý nghiệp vụ. Giai đoạn Next.js gọi Laravel chỉ là mốc kiểm tra frontend, không được báo là đã hoàn thành chuyển backend.

Cấu trúc dự kiến trong repo hiện hữu:

```text
apps/web/                    Next.js; component, CSS và API client
apps/api/                    NestJS; module, service, repository
packages/contracts/          Kiểu request/response dùng chung, không chứa secret
tests/parity/                Đối chiếu API, nghiệp vụ, ảnh và file xuất
docs/migration/              Ma trận chức năng, quyết định và bằng chứng đã làm sạch
infra/render-next.yaml       Cấu hình triển khai bản mới, tách bản đang chạy
```

Giữ package/build legacy hoạt động trong thời gian đối chiếu. Dùng lockfile tách biệt giai đoạn đầu; chưa tổ chức lại toàn repo. `.gitignore` hiện có `/himoto-next/`, vì vậy không đặt source mới vào đường dẫn đó rồi quên đưa vào Git.

## 4. P0 — Đóng băng baseline và lập ma trận đầy đủ

Thực hiện:

1. Tạo nhánh `migration/nextjs-node-parity` từ SHA cố định; lưu SHA và metadata build, tách môi trường đối chiếu bản cũ/bản mới.
2. Ghi cấu hình có ảnh hưởng hành vi: API URL, timezone, feature flag, provider mode, role/capability, chi nhánh, cron và trạng thái schema; không lưu giá trị secret.
3. Dựng PostgreSQL test riêng với dữ liệu tổng hợp hoặc bản sao đã làm sạch, đủ các trạng thái nghiệp vụ. Không dùng database vận hành cho test tạo/sửa/xóa.
4. Lấy tài khoản test theo các quyền thực tế, tối thiểu có hai chi nhánh và tài khoản bị giới hạn. Không tự giả định chỉ có bốn vai trò từ tài liệu cũ.
5. Đọc router, component lồng nhau, API routes, middleware, service, helper, model event/observer và cron để lập danh sách. Số liệu quét sơ bộ: 196 khai báo route API, 39 controller PHP, 53 model, 152 file migration và 252 file Vue; không đồng nghĩa 196 endpoint runtime hay 252 màn hình.
6. Mỗi route phải có danh sách tab, popup, nút, request, quyền, trạng thái, file xuất và tác động DB. Bổ sung cả API không có menu, scheduler, observer và webhook.
7. Chụp ảnh và ghi thao tác bản cũ trên desktop 1440×900, 1920×1080, laptop 1366×768, tablet 768×1024, mobile 390×844. Chốt thêm trình duyệt thực tế cần hỗ trợ.
8. Ghi các trạng thái: có dữ liệu, trống, đang tải, lỗi, thiếu quyền, form sai/hợp lệ, popup mở, thao tác lưu, trang kế tiếp và bộ lọc đang dùng.
9. Lưu request/response mẫu đã bỏ token/PII và expected result của từng giao dịch. Kiểm API bằng route runtime trên local/staging; không dựa hoàn toàn vào regex hay tài liệu cũ.
10. Đo baseline hiệu năng theo mục 10; ghi giới hạn đã biết và phần chưa đủ điều kiện kiểm.

Đầu ra dự kiến: `baseline.json`, `feature-matrix.csv`, `api-contracts.json`, bộ dữ liệu test, ảnh baseline, danh sách lỗi cũ và báo cáo thời gian tải.

Một dòng ma trận gồm: `ID, module, route/action/job, role, branch_scope, precondition, input, expected_UI, expected_API, expected_DB, screenshot, legacy_status, next_status, node_status, evidence`.

Điều kiện xong: mọi route/action/job đã được gán dòng đối chiếu; những gì chưa kiểm có trạng thái riêng, không đánh PASS. Sau bước này cập nhật ước lượng theo số popup, luồng tiền và tích hợp thực tế.

## 5. Phạm vi module phải chuyển

Bảng dưới là khung kiểm kê từ router/API hiện tại. P0 phải tách tiếp từng hành động, không coi một dòng là một test.

| Nhóm | Route/phạm vi hiện có | Nội dung đối chiếu chính |
| --- | --- | --- |
| Khung và tài khoản | `/`, `/login`, `/register`, `/ref/:id`, `/403`, `/404` | Giao diện đăng nhập, phiên, quên/đổi mật khẩu, điều hướng theo quyền, sidebar/header/drawer, trang lỗi |
| Dashboard | `/dashboard` | Bộ lọc chi nhánh/thời gian, KPI, biểu đồ, bảng, cache và cập nhật sau giao dịch |
| Xe | `/vehicles` | Dạng bảng/thẻ, tìm kiếm, trạng thái, ODO, giá, ảnh, xem/thêm/sửa/xóa theo quyền |
| Kho và GPS | `/warehouses`, API GPS | Danh sách kho, điều chuyển, nhận xe khác cơ sở, đổi xe, lịch sử, vị trí/cảnh báo GPS |
| Khách | `/customers`, `/customers-create`, `/customers-update/:id` | Hồ sơ, giấy tờ, tìm kiếm CCCD/SĐT, ảnh, cảnh báo và lịch sử |
| Lead | `/leads` | Nguồn, phân công, cập nhật, chuyển đổi, liên kết đơn, đồng bộ WordPress |
| Cửa hàng | `/stores`, `/store-create`, `/store-update/:id` | Thông tin và phạm vi chi nhánh |
| Bảng giá, người dùng | `/pricing`, `/user` | Giá thuê, tài khoản, mật khẩu, trạng thái, vai trò/capability |
| Đơn thuê | `/car-rental` | Tạo/sửa, preview, khóa hợp đồng, cọc, bắt đầu thuê, gia hạn, trả xe, nợ xấu, thống kê, in/PDF và Excel |
| Đơn bán | `/car-sell` | Đơn bán xe, giá vốn/giá bán, thu tiền, trạng thái xe và báo cáo |
| Ngân hàng | `/banks`, `/banks-create`, `/banks-update/:id` | Tài khoản, chủ sở hữu, số dư, quyền và giao dịch |
| Quỹ | `/cash`, `/cash-create`, `/cash-update/:id` | Quỹ tiền mặt, số dư, trạng thái và quyền |
| Thu chi | `/transactions`, `/receipt`, `/receipt/create`, `/receipt/update` | Lọc, chi tiết, tạo/sửa/hủy, phương thức thanh toán, xuất file |
| Chốt két | `/finances/daily-cash-register` | Số dư đầu/cuối, thu chi, ngân hàng, chênh lệch và khóa/chốt |
| Bảo dưỡng | `/maintenance-rule`, `/maintenance-type`, `/maintenance-log`, `/maintenance-schedule` | Quy tắc chung/nhóm/từng xe, lịch thủ công/tự động, nhật ký và chi phí |
| Thuê sở hữu | `/lease-to-own`, API ownership | Hợp đồng, kỳ trả, phân bổ, đảo thu, tất toán, ghi chú, snapshot/PDF, công nợ, chuyển quyền |
| Nhắc nợ | `/customer-reminders`, webhook | Action list, quét, outbox, gửi/thử lại, chống trùng, trạng thái provider |
| Nhân sự | `/hr/duty-schedule`, API HR | Hồ sơ, phòng ban, phân ca/chấm công và phạm vi được phép |
| Báo cáo | `/report/detail-report`, `/report/vehicle-revenue`, `/report/kpi` | Các bộ lọc, công thức, thời điểm ghi nhận, tổng và file xuất |
| Kế toán | `/accounting` và các tab/API | VAT, tài sản, tài khoản, bút toán, đảo bút toán, sổ cái, đối soát, đóng/mở kỳ |
| Điều hướng tương thích | `/orders` → `/car-rental`, `/roles` → `/user`, URL không tồn tại → `/404` | Deep link, refresh, redirect, quyền và lịch sử trình duyệt |
| Phần không có menu | Health, upload, export, thông báo, audit, cron, webhook | Định dạng API, lịch chạy, side effect, idempotency, log và retry |

## 6. P1–P2 — Dựng nền và chuyển toàn bộ frontend

P1: tạo hai ứng dụng chạy độc lập, cấu hình TypeScript, lint, build, log, health, env mẫu và test runner. Frontend có API adapter cho Laravel/Node; backend có validation, error handler, request ID và kiểm tra cấu hình bắt buộc.

P2 thực hiện theo trình tự:

1. Chuyển `Layout.vue`, `HimotoSidebar.vue`, `HimotoHeader.vue`, `HimotoDrawer.vue`; tái sử dụng logo, font, icon và các quy tắc trong `himoto-app.scss`/`assets/himoto/himoto.scss`.
2. Chuyển CSS scoped của Vue thành phạm vi tương ứng ở React; kiểm specificity, thứ tự stylesheet và portal của popup. Không chép nguyên selector `data-v-*` sinh khi build rồi coi là đã chuyển source.
3. Chuyển các component chung: bảng, phân trang, input tiền, ngày giờ, autocomplete, upload, modal, thông báo, skeleton và trạng thái lỗi/trống. Bộ chọn ngày/dropdown phải khớp cả popup và hành vi bàn phím.
4. Chuyển đăng nhập/phiên/quyền; giai đoạn đối chiếu frontend vẫn dùng Laravel làm nguồn xác thực để cô lập lỗi.
5. Chuyển từng nhóm màn hình trong ma trận: dashboard/danh mục → thuê/bán → thu chi/công nợ → kho/bảo dưỡng → báo cáo/HR/kế toán/tích hợp.
6. Giữ query parameter, trạng thái bộ lọc, số trang, sắp xếp, định dạng tiền/ngày và thông báo. Những component phụ thuộc `window`, canvas, bản đồ, PDF được tải phía client phù hợp.
7. Chuyển `ContractPrintDocument.vue`, preview và mọi template in; đối chiếu bản in/PDF bên cạnh giao diện màn hình.
8. Chạy kiểm ảnh và thao tác sau mỗi module với cùng Laravel test và dataset baseline. Sửa sai khác trước khi đưa module vào danh sách hoàn thành.

Điều kiện xong P2: toàn bộ phạm vi frontend đã chuyển, có bằng chứng ảnh/thao tác, hoạt động với API Laravel test thực. Màn hình chỉ có mock hoặc nút chưa nối chức năng chưa đạt.

## 7. P3–P5 — Chuyển backend và công việc nền

### P3: nền dữ liệu, quyền, danh mục

1. Lập mapping model/relation/scope/soft delete/cast/accessor của Laravel sang SQL/repository Node, gồm cả bảng không có model riêng.
2. Giữ schema `himoto`, khóa ngoại, sequence, unique index và tên cột. Kiểm index doanh thu bằng `scripts/check_vehicle_revenue_index.php` trên môi trường phù hợp; không suy ra index đã tồn tại chỉ vì có file migration.
3. Pool kết nối có giới hạn tổng cho web/API/job; chọn direct/session pooler theo môi trường, kiểm TLS và timeout theo hướng dẫn [Supabase](https://supabase.com/docs/guides/database/connecting-to-postgres).
4. Chuyển xác thực: kiểm hash mật khẩu Laravel với thư viện Node bằng fixture, giữ tài khoản cũ. Đối chiếu JWT claims, thời hạn, gia hạn, thu hồi khi logout, tài khoản khóa và response capability.
5. Trong giai đoạn hỗn hợp dùng một nguồn xác thực/thu hồi phiên; không chỉ kiểm chữ ký JWT rồi bỏ qua token đã logout. Hoàn thành chuyển quyền sở hữu cơ chế phiên sang Node trước khi tắt Laravel. Kiểm khả năng giữ phiên hiện hữu; nếu buộc đăng nhập lại thì phải ghi thành ngoại lệ trước cutover.
6. Chuyển middleware quyền và store scope sang guard/service phía server; kiểm trực tiếp URL/API khác chi nhánh, không chỉ ẩn nút.
7. Chuyển user/store/customer/vehicle/pricing/lead/upload và các API đọc cơ bản, giữ contract và validation cũ.

### P4: thuê xe và giao dịch tiền

1. Tách hàm tính tiền thuần từ helper/service PHP, ghi input/output kỳ vọng bằng dữ liệu cố định rồi triển khai tương ứng trong TypeScript.
2. Kiểm đầy đủ: giá thường/giá thay thế, ngày/giờ, cọc tiền/cọc tài sản, cọc bổ sung, gia hạn, trả sớm/muộn, hoàn cọc, nợ xấu, hủy/sửa, đơn cũ và đơn mới.
3. Chốt cách đọc/ghi `timestamp` và `timestamptz`. Cấu hình app hiện tại là `Asia/Ho_Chi_Minh`; dữ liệu lịch sử cần kiểm riêng, không cộng/trừ 7 giờ hàng loạt. Test qua nửa đêm, cuối tháng và khoảng lọc ngày.
4. Dùng decimal hoặc đơn vị tiền nguyên theo trường thực tế; giữ quy tắc làm tròn/serialize hiện có. Không dùng số thực JavaScript để cộng tiền rồi kỳ vọng luôn khớp.
5. Chuyển transaction, khóa dòng, thứ tự lock, cấp số hợp đồng và chống ghi trùng. Các câu SQL trong một transaction phải dùng cùng connection.
6. Đối chiếu toàn bộ tác động: đơn, chi tiết xe, trạng thái xe, tiền đã thu/hoàn, số dư, audit, snapshot và báo cáo; không chỉ response HTTP.
7. Chuyển đơn bán, phiếu thu/chi, quỹ/ngân hàng, chốt két; test lưu lặp, lỗi giữa bước và hai người thao tác cùng lúc.

### P5: module còn lại, báo cáo, tích hợp

1. Chuyển thuê sở hữu/công nợ/ownership, kế toán, HR/KPI, kho/điều chuyển/GPS, bảo dưỡng và nhắc nợ.
2. Giữ invariant: phân bổ/đảo thu không nhân đôi tiền, số dư khớp, bút toán Nợ=Có, kỳ đóng không bị ghi thêm, snapshot không tự thay đổi.
3. Chuyển báo cáo/Excel/PDF theo cùng bộ lọc và dataset. So nội dung, kiểu cell, tổng, bố cục in; không dùng hash toàn file nếu metadata thời gian có thể thay đổi.
4. Kiểm các lịch trong `app/Console/Kernel.php`: crontab, tính quá hạn, bảo dưỡng mỗi phút; lấy lead mỗi 15 phút. Kiểm scheduler thực tế và các command khác trước khi quyết định lịch Node.
5. Đảm bảo từng job có đúng một scheduler chạy, khóa chống chạy chồng và xử lý retry phù hợp. Chuyển cả upload Cloudinary, lead WordPress, outbox, webhook và adapter provider.
6. Kiểm chữ ký/xác thực webhook và sự kiện gửi lặp/sai thứ tự; giữ chế độ sandbox/live của baseline. Test tích hợp ngoài bằng sandbox; trạng thái chưa xác minh phải được ghi riêng.
7. Audit đầy đủ nhánh route và dependency; loại bỏ lời gọi Laravel khỏi luồng đã chuyển. Bản hoàn tất phải chạy được khi Laravel đối chiếu đã dừng.

Điều kiện xong P3–P5: từng API/job đạt contract, phân quyền và kết quả DB. Không còn module dùng mock thay nghiệp vụ thật trong bộ nghiệm thu.

## 8. P6 — Kiểm thử đối chiếu và nghiệm thu

Tách hai bộ: kiểm giao diện có fixture cố định để so ảnh; kiểm E2E qua API thật trên PostgreSQL test, không intercept thành mock. Cả hai đều phải đạt.

| Lớp kiểm | Cách làm | Điều kiện đạt |
| --- | --- | --- |
| Ảnh và tương tác | Cùng fixture/clock/font/browser; screenshot từng trang, popup, trạng thái và viewport | Không còn khác biệt nhìn thấy hoặc thao tác bị thiếu ngoài ngoại lệ đã duyệt |
| API | Cùng input; so status, header cần thiết, JSON, phân trang, lỗi và file | Khớp contract; trường biến động chỉ được chuẩn hóa theo danh sách rõ ràng |
| Tính tiền | Dùng fixture độc lập và expected result từ nghiệp vụ đã xác minh | Khớp chính xác theo đơn vị/làm tròn đã chốt, không dung sai phần trăm |
| Ghi DB | Hai database test xuất phát cùng snapshot; chạy luồng cũ/mới riêng rồi so | Trạng thái, liên kết, tiền và audit đúng; ID phát sinh được map tường minh khi cần |
| Đồng thời | Hai request thuê cùng xe/thu cùng khoản/cấp số/đóng kỳ; retry và lỗi giữa bước | Không trùng giao dịch/số hợp đồng, không mất cập nhật, không lưu nửa chừng |
| Quyền và phiên | Từng role/chi nhánh, record ID trực tiếp, token cũ, logout A/login B, request A đến chậm | Không đọc/ghi sai quyền, không hiện cache hoặc response của người/chi nhánh trước |
| File/tích hợp/job | Đối chiếu nội dung, sandbox callback, retry và lịch chạy | Không thiếu file, mất dữ liệu hoặc xử lý trùng; chế độ provider đúng baseline |
| Hiệu năng | Cùng dữ liệu, tài nguyên, mạng và trạng thái cache | Không suy giảm đáng kể; đạt ngân sách hiệu năng chốt sau baseline |
| Vận hành | Restart, timeout DB, log, health, backup/restore, rollback | Phục hồi được; có bằng chứng và hướng dẫn thực hiện |

Tận dụng test PHP/Python hiện có làm nguồn kịch bản, nhưng phải đọc assertion trước khi coi là bằng chứng. Kết quả test trên commit cũ không tự động là PASS cho commit mới. Không chạy các suite có ghi dữ liệu lên live.

Thực hiện UAT trên các quyền thực tế: tạo khách → chọn xe → tạo hợp đồng → thu cọc → bắt đầu thuê → gia hạn → trả xe → hoàn/thu thêm → xem báo cáo và đối chiếu quỹ. Bổ sung đầy đủ luồng thuê sở hữu/kế toán/kho theo ma trận.

## 9. P7 — Triển khai, chuyển traffic và bàn giao

1. Dựng web/API Node mới trên staging, phát hành đúng SHA đã kiểm; kiểm deep link, CORS, biến môi trường, upload và metadata commit. Không chỉnh service production trong bước dựng vỏ.
2. Chọn cách phục vụ Next.js ngay ở P1. Mặc định kế hoạch dùng Node Web Service để hỗ trợ App Router/server runtime; nếu dùng static export phải kiểm mọi route động và tính năng server trước. Không bê nguyên `staticPublishPath` của Vue sang cấu hình mới. [Hướng dẫn Render](https://render.com/docs/deploy-nextjs-app).
3. Đặc biệt xác minh cách giữ URL `himoto-web.onrender.com`: URL này gắn với service Render hiện tại. Không giả định tạo service Node mới sẽ nhận lại URL cũ. P1 phải kiểm khả năng chuyển runtime/định tuyến trên Render; nếu cần đổi URL hoặc dùng custom domain, ghi phương án cụ thể để chủ dự án quyết định trước cutover.
4. Xác minh gói hosting, region và lịch job thực tế. `render.yaml` đang khai báo API Free; nếu runtime cũng Free thì có ngủ sau 15 phút không có traffic. Đây là yếu tố cần tách khỏi hiệu năng code. [Giới hạn Render Free](https://render.com/docs/free#spinning-down-on-idle).
5. Backup database và thử restore vào DB riêng; lưu deployment/version trước đó và cấu hình routing/scheduler để phục hồi.
6. Giai đoạn chuyển đổi ưu tiên không đổi schema. Nếu cần bổ sung cột/index, áp dụng tương thích ngược, review trên staging; không reset/import đè dữ liệu vận hành.
7. Chốt cửa sổ chuyển đổi ngắn: dừng nhận thao tác ghi mới, đợi giao dịch đang chạy xong, tạm dừng scheduler cũ; đối soát số dư/đơn rồi chuyển web/API/job sang bản mới.
8. Kiểm phiên, quyền, danh sách, file xuất và số liệu sau chuyển. Không tạo giao dịch tiền thật chỉ để thử. Mở lại thao tác ghi khi các kiểm tra bắt buộc đạt.
9. Nếu sai tiền/quyền/dữ liệu hoặc chặn vận hành: dừng ghi, giữ bằng chứng, chuyển app/routing/job về bản cũ tương thích schema. Không phục hồi backup đè lên giao dịch mới. Nếu bản mới đã tạo dữ liệu mà bản cũ không hiểu, xử lý đối soát trước khi mở lại.
10. Theo dõi tối thiểu hai ca vận hành và một chu kỳ job/báo cáo có liên quan; chốt thời lượng thực tế với người vận hành. Giữ bản cũ để rollback đến khi nghiệm thu kết thúc.
11. Bàn giao source/lockfile, cấu hình mẫu, tài liệu chạy/build/deploy, ma trận PASS, ảnh diff, kết quả tiền/quyền, benchmark, backup/restore, rollback và danh sách hạn chế.

Trước phát hành phải cập nhật các credential đã lộ trong chat trên nhà cung cấp và môi trường phụ thuộc; không đưa credential vào source hoặc tài liệu. Đây là công việc vận hành thực tế, không được đánh hoàn thành chỉ vì đã thêm `.gitignore`.

## 10. Cách kiểm tốc độ sau chuyển đổi

Đo trước và sau cùng phần cứng/gói hosting/region/dataset/tài khoản/bộ lọc/trình duyệt. Đo production build, không so dev server với bản production.

- Tách lần mở đầu, lần mở khi dịch vụ đã thức và lần chuyển menu; tách cache trống/cache có dữ liệu.
- Màn hình trọng điểm: đăng nhập, Dashboard, xe, đơn thuê, doanh thu xe, báo cáo chi tiết và kế toán.
- Ghi thời gian đến nội dung dùng được, thời gian phản hồi thao tác, API p50/p95, lỗi, số request, dung lượng JS/ảnh và truy vấn DB.
- Với API thường dùng: tối thiểu 100 mẫu warm cùng tải cố định; với UI tối thiểu 20 lượt mỗi tình huống, ghi median/p95 và biến động. Đo cold start riêng với số mẫu rõ ràng.
- Ngân sách tạm đề xuất: không tăng quá 10% median/p95 của các luồng chính trong điều kiện kiểm soát; phải xem độ dao động và chốt lại ở P0. Không dùng con số này làm cam kết tốc độ trước khi đo.
- Tối ưu theo bằng chứng: giảm bundle, tải chart/PDF khi cần, giảm request lặp, phân trang server, tránh N+1, kiểm index/EXPLAIN, giới hạn connection pool, đặt app gần DB.
- Cache phải phân biệt user/chi nhánh/quyền/bộ lọc, xóa/refresh sau mutation, hủy response cũ khi đổi phiên. Không cache chia sẻ dữ liệu người dùng ở Next/CDN.

Mục tiêu bắt buộc là đúng và không chậm đi đáng kể; mức cải thiện chỉ được công bố từ số đo. Next.js/Node.js tự nó không bảo đảm nhanh hơn.

## 11. Thời gian, thứ tự và mốc bàn giao

Ước lượng sơ bộ cho một kỹ sư hiểu repo, có hỗ trợ công cụ, làm liên tục; chưa gồm thời gian chờ tài khoản, provider, hạ tầng hay phản hồi UAT. Một ngày công khoảng 8 giờ. Đây là ước lượng lập kế hoạch, phải cập nhật sau P0.

| Bước | Ngày công dự kiến | Đầu ra dùng để nghiệm thu |
| --- | --- | --- |
| P0: baseline và ma trận | 1–2 | Snapshot, dataset, danh sách đầy đủ và benchmark cũ |
| P1: nền Next.js/Node | 0,5–1 | Hai app build/chạy được, cấu hình test/deploy rõ ràng |
| P2: toàn bộ frontend | 5–8 | UI và thao tác khớp với Laravel test |
| P3: nền backend/danh mục/quyền | 3–5 | API/DB/auth cơ bản tương thích |
| P4: thuê xe và tài chính | 5–8 | Luồng tiền và concurrency đạt |
| P5: module còn lại/job/tích hợp | 5–8 | Toàn bộ ma trận backend được chuyển |
| P6: kiểm thử/UAT/hiệu năng | 3–5 | Bằng chứng đối chiếu, lỗi chặn được xử lý |
| P7: cutover/bàn giao | 1–2 | Bản vận hành mới, rollback và hồ sơ bàn giao |

Tổng khoảng 24–39 ngày công; cộng dự phòng 20–30% thì nên dự trù khoảng 6–10 tuần làm việc. Việc thêm nhân sự hoặc thay đổi phạm vi cần cập nhật lịch riêng, không tự giả định có nhiều người thực hiện.

Mốc tối nay, nếu còn 4–6 giờ: chốt SHA/phạm vi, lấy mẫu baseline đăng nhập/layout, dựng Next.js + NestJS, chuyển thử login/sidebar/header và một trang đại diện, health API, build và smoke test local. Chưa đủ thời gian nghiệm thu toàn bộ UI/API/nghiệp vụ 1:1. Phần mẫu này là kết quả ban đầu của P0/P1/P2, không phải hoàn tất chuyển đổi.

Các mốc báo cáo: baseline xong → vỏ chạy → frontend đối chiếu đạt → từng module Node đạt → toàn bộ test/UAT đạt → vận hành mới được nghiệm thu. Mỗi báo cáo nêu đã làm, bằng chứng, lỗi còn lại và thời gian cập nhật.

## 12. Điều kiện xác nhận hoàn thành

- [ ] Baseline cố định và cấu hình ảnh hưởng hành vi đã ghi nhận.
- [ ] Ma trận đầy đủ màn hình/action/API/job; mọi dòng có kết quả và bằng chứng.
- [ ] Giao diện, nội dung, popup, thao tác, responsive và URL đạt đối chiếu.
- [ ] API và nghiệp vụ Node thay thế đủ các luồng; không còn phụ thuộc Laravel khi vận hành.
- [ ] Dữ liệu, lịch sử, ID, ảnh, snapshot và file xuất được bảo toàn.
- [ ] Tiền, quyền, phiên, concurrency, retry và timezone qua test thực trên PostgreSQL riêng.
- [ ] Scheduler/provider/webhook khớp trạng thái baseline và không chạy trùng.
- [ ] Không còn lỗi chặn hoặc khác biệt chưa được xử lý/chấp nhận.
- [ ] Benchmark production build đạt ngưỡng đã chốt; kết luận có số đo.
- [ ] Backup đã restore thử, rollback đã diễn tập, phương án giữ URL đã xác minh.
- [ ] Người vận hành đã nghiệm thu và đối soát sau chuyển đổi.
- [ ] Source, hướng dẫn triển khai/vận hành và hồ sơ test đã bàn giao.

Tiến độ chỉ được tính từ bằng chứng thực hiện. Có giao diện đẹp, API health xanh, build thành công hoặc nhiều test PASS riêng lẻ chưa đủ để đánh dấu toàn bộ kế hoạch hoàn thành.
