# HIMOTO — Kế hoạch triển khai thử nghiệm và bàn giao AI

Ngày: 15/09/2026. Baseline đã đọc: `0f412e4`, nhánh `main`, repo `https://github.com/huybitvvt/duanthuexe`.

Đây là kế hoạch thực hiện, chưa phải báo cáo tính năng đã hoàn thành. Lượt này chỉ viết tài liệu; không triển khai nghiệp vụ, không sửa dữ liệu thật hoặc chạy migration. AI nhận việc phải kiểm tra lại HEAD và các thay đổi mới trước khi làm.

## 1. Mục tiêu, nguồn yêu cầu và thứ tự ưu tiên

Cho nhân viên chạy thử được luồng nhập thông tin → xem trước hợp đồng → lưu → in lại; sau đó hoàn thiện thu tiền, đơn trong ngày, thuê sở hữu/công nợ và quản lý kho/điều chuyển/định vị.

Nguồn yêu cầu:

- Tin nhắn và bảng 4 mục người dùng gửi ngày 15/09/2026; ảnh xem trước, danh sách hợp đồng và hai ảnh kho là tham chiếu UI/nghiệp vụ.
- `E:/duanthuexe/HĐTX Himoto A4.pdf`: mẫu hợp đồng thực tế, một trang A4 ngang có hợp đồng bên trái và phụ lục/xác nhận trả xe bên phải. Bản raster để đối chiếu: `E:/duanthuexe/audit-prototype/contract-template.png`.
- `docs/himoto-contract-fields.md`: bảng đối chiếu trường trước triển khai, có nhiều nội dung đề xuất và ghi chú lịch sử; không coi mọi mục trong đó là đã được duyệt hoặc đã triển khai.
- Các review vòng 1–3 là các lỗi phải tránh tái phát, không phải bằng chứng bản hiện tại đã sạch mọi lỗi.

Ưu tiên giao hàng:

1. P0: hợp đồng đầy đủ dữ liệu, xem trước/in và định dạng phù hợp màn hình; ưu tiên để bắt đầu thử nghiệm.
2. P0: phân loại tiền mặt/chuyển khoản cá nhân/chuyển khoản công ty, số liệu đối soát đúng.
3. P1: kho xe, phân quyền, lịch sử và ba luồng điều chuyển.
4. P1: thuê sở hữu, kỳ thanh toán, công nợ, ghi chú và xuất báo cáo.
5. P1 phụ thuộc hệ thống ngoài: định vị và nhắc khách tự động; không được đánh dấu hoàn thành bằng dữ liệu demo.
6. P2: hoàn thiện “Đơn trong ngày” theo phạm vi được chốt, kiểm hồi quy và nghiệm thu toàn hệ thống.

## 2. Những quyết định phải ghi nhận trước khi làm phần phụ thuộc

Không chờ các quyết định này để ngừng toàn bộ công việc. Có thể xây UI, fixture, API và các phần độc lập trước; không tự đặt điều khoản tài chính hoặc sửa số lịch sử.

| Chủ đề | Đã biết / điểm chưa rõ | Phương án dùng để lập kế hoạch | Điều kiện trước khi phát hành |
|---|---|---|---|
| Mã hợp đồng | Trước đây chốt `YYYY/MM/DD-0001`; bảng mới ghi `YYYYMMDD-0001` | Giữ mã hiện hành cho tới khi xác nhận đổi; hiển thị nhất quán mọi nơi | Chốt dạng mới, ngày ký hay ngày cấp, phạm vi bộ đếm, có đổi số cũ không. Mặc định không đổi số cũ |
| Bố cục in | PDF A4 ngang 2 phần, ảnh preview hai trang dọc | Template chuẩn đầu tiên bám PDF A4 ngang; preview cuộn/zoom theo màn hình | Nếu cần hai trang A4 dọc, xác nhận đó là biến thể trình bày, duyệt bản in cả hai trang |
| Preview trước lưu | Chưa có số HĐ được lưu | Hiện “Chưa cấp số” và nhãn bản xem trước, không tiêu thụ bộ đếm | Không hiển thị số dự đoán như số chính thức |
| Số kho | Văn bản nói 5 cơ sở vật lý; ảnh có 5 cơ sở và 1 kho Thuê sở hữu | Thẻ sinh từ dữ liệu Store, có một phân loại kho thuê sở hữu độc lập | Chốt danh sách/mã kho thực tế; không hardcode tên/kho ID từ ảnh |
| Quyền chi nhánh | Admin xem từng xe, cơ sở chỉ xem tổng số | Dashboard kho của cơ sở chỉ trả aggregate; thao tác xe qua nghiệp vụ được cấp quyền | Chốt quyền xe tại cơ sở của chính mình, kho thuê sở hữu, trả khác cơ sở và ai xác nhận điều chuyển |
| Thuê sở hữu | Bảng mới yêu cầu thay tên Đơn bán xe và theo dõi công nợ | Module nghiệp vụ mới, không tự biến giao dịch bán xe đã có thành thuê sở hữu | Chốt kỳ trả, số kỳ, số tiền, đặt cọc, tất toán, chuyển quyền sở hữu, xử lý chậm trả |
| Định vị | Ảnh có bản đồ, trạng thái mất GPS/không hoạt động | Adapter cho nhà cung cấp, trạng thái “Chưa có dữ liệu” khi chưa tích hợp | Cần tài liệu API, mapping thiết bị–xe, credentials do chủ hệ thống cấu hình, ngưỡng mất tín hiệu/ngừng hoạt động |
| Nhắc khách | Chưa chỉ định SMS/Zalo/email và lịch nhắc | Danh sách nhắc nội bộ trước; thiết kế gửi tự động có hàng đợi | Chốt kênh, mẫu nội dung, người nhận, lịch/tần suất, chế độ thử và quyền kích hoạt gửi thật |
| Đơn trong ngày | Nội dung bảng chỉ ghi “Có thể” | Đề xuất view lọc đơn tạo/nhận/trả/thu tiền trong ngày, không tạo nghiệp vụ mới | Chốt tab nào cần và ngày căn cứ của từng tab |

## 3. Hiện trạng đã kiểm từ code

| Phần | Nguồn hiện hữu | Kết luận và cách nối tiếp |
|---|---|---|
| Form hợp đồng | `Order/components-order/OrderUpdate.vue`, `ItemsOrder.vue`, `ModalComplete.vue` trong `resources/js/src/view/pages/` | Có trường pháp lý, CCCD, người thân, GPLX, phụ kiện, người ký; cần kiểm đủ vòng nhập–lưu–mở lại–in |
| Danh sách/chi tiết | `Order/OrderCarRental.vue`, `Order/components-order/OrderShow.vue` | Đã có số HĐ và snapshot; chưa thấy endpoint/view in trong phạm vi route/controller đã tìm |
| Số HĐ | `app/Http/Services/ContractNumberService.php` | Bộ đếm theo ngày, format `Y/m/d` + 4 số; không tự tạo bộ đếm thứ hai |
| Snapshot/khóa | `app/Http/Services/OrderService.php` | Có `maybeGenerateContractSnapshot`, `lockContract`; khóa hiện dùng snapshot có sẵn nếu có. Cần validate nội dung và tránh khóa bản cũ sau sửa form |
| Tiền | `components/PaymentMethod.vue`, `app/Models/Transaction.php`, `app/Models/Bank.php` | Có TM/CK/kết hợp và bank_id; chưa có phân loại chủ tài khoản cá nhân/công ty trong model Bank đã đọc |
| Giá thuê | `OrderUpdate.vue::calOriginalUnitPrice`, `CarRentalHelper` | Lọc loại xe, năm, số ngày; trường hợp không có giá đang có thể trả 0. Preview phải báo thiếu bảng giá, không ngầm xác nhận 0 là giá thuê đúng |
| Bán xe | `app/Entities/SellOrder.php`, `app/Http/Services/Orders/OrderSellService.php`, `order-sell/*.vue` | Lưu bán xe và lợi nhuận; chưa đủ mô hình lịch trả/công nợ thuê sở hữu |
| Xe/kho | `app/Models/Vehicle.php`, `Store.php`, `VehicleRepositoryEloquent.php` | Có store_id, trạng thái xe; chưa thấy ledger điều chuyển xe hoặc module GPS trong phạm vi đã tìm |
| Quyền | `routes/api.php`, `OrderRepositoryEloquent.php`, `app/Http/Middleware/NonSale.php` | Có lọc cửa hàng tại danh sách đơn; không suy ra mọi route mới tự được bảo vệ. Cần policy theo đối tượng |
| Deploy | `render.yaml`, `scripts/build-static.js`, `webpack.mix.js` | Static build dùng `npm run build:static`; API Docker có RUN_MIGRATIONS=true trong khai báo. Phải kiểm cấu hình thực tế trước phát hành migration |
| Stack | `composer.json`, `package.json` | Laravel 5.8, Vue 2, BootstrapVue, Element UI; có Excel exporter. Chọn giải pháp tương thích lockfile/runtime, không nâng framework để làm các yêu cầu này |

Các đường dẫn mới dưới đây là đề xuất, chưa tồn tại nếu không ghi rõ hiện hữu.

## 4. Gói P0-A — Form, xem trước và in hợp đồng

### 4.1 Luồng nhân viên và bố cục

1. Tạo/sửa hợp đồng: chia nhóm Thông tin HĐ → Khách thuê → Xe/người lái → Giá & thanh toán → Thế chấp/người ký.
2. Footer form có `Xem trước hợp đồng` ngay cạnh `Lưu hợp đồng`; màn nhỏ hai nút vẫn nhìn đủ nhãn, không che field cuối.
3. Khi đủ dữ liệu, nút preview hoạt động. Khi thiếu, hiển thị danh sách trường thiếu/cần sửa và đưa focus về nhóm tương ứng; trạng thái nút có giải thích, không chỉ vô hiệu hóa im lặng.
4. Preview dùng dữ liệu mới nhất đang nhập, gồm input vừa blur, date picker, thay xe, thay khách; không gọi API tạo/lưu đơn để xem trước.
5. Preview có zoom, số trang, đóng/quay lại sửa và nút In. In trước lưu là bản xem trước, ghi rõ chưa cấp số; nếu cần bản chính thức thì dùng Lưu → In. Không tự giữ xe, ghi nhận tiền hay cấp số khi preview/in nháp.
6. Sau lưu thành công có `Xem/In hợp đồng`; nút In xuất hiện ở cột Hành động ngoài danh sách và màn chi tiết. In đúng ID, số HĐ và phiên bản snapshot.
7. Bản chưa chốt đã lưu vẫn ghi trạng thái bản nháp khi in. Bản chính thức đi qua chốt có validate; retry chốt/in không đổi số hoặc dữ liệu đã ký.
8. Hủy preview, lỗi mạng hoặc lỗi validation giữ nguyên dữ liệu đang nhập. Thay input trong lúc request preview đang chạy phải loại response cũ.

Bố cục responsive: 1 cột ở 360/390px, 2 cột ở tablet, tối đa 3 cột input trên desktop; nhãn dài xuống dòng trong vùng nhãn, không cắt dữ liệu; ngày/giờ đủ rộng. Print không chụp ảnh cả modal rồi co nhỏ.

### 4.2 Ma trận dữ liệu cần điền/lấy sẵn

| Nhóm | Field hiện có hoặc đề xuất | Quy tắc preview/chốt |
|---|---|---|
| Hợp đồng | contract_number, contract_signed_on, contract_responsible_user_id | Số do server cấp; ngày ký hợp lệ; NV hiển thị người phụ trách, đề xuất dropdown có giới hạn quyền |
| Bên A | Công ty, MST, trụ sở, đại diện/chức vụ, Store tên/địa chỉ/điện thoại | Lấy cấu hình và chi nhánh; không bắt nhập lại từng đơn; snapshot tại chốt |
| Ủy quyền | contract_authorization_date, contract_authorization_party_name; thêm tên đại diện được ủy quyền nếu thiếu | Bắt buộc khi bật ủy quyền; không dùng tên tổ chức thay tên người đại diện |
| Bên B | Họ tên, điện thoại, địa chỉ, CCCD, ngày/nơi cấp | Validate trên server và UI; CCCD/điện thoại lưu chuỗi giữ số 0 đầu; giữ hỗ trợ giấy tờ cũ theo chính sách |
| Người thân | Danh sách tên, quan hệ, điện thoại | Hiện đủ liên hệ trên bản in; bắt buộc hay tùy chọn phải chốt. Không tự dùng dữ liệu khách trước |
| Xe | vehicle_id → biển số, nhãn hiệu, loại, màu, năm SX | Ít nhất 1 xe, không trùng; nhãn tiếng Việt, không in mã `xe_dien` |
| Người lái | driver_name, driver_license_number, driver_license_issued_on | Theo từng xe, không dùng biển số thay GPLX; test nhập → chuyển ô → lưu → mở lại |
| Thuê | rent_at, return_at, đơn giá đã áp dụng, đơn vị, ngày/giờ lẻ, nhãn gói | Hẹn trả sau lúc thuê; không chia tổng để suy đoán đơn giá; giá tùy chỉnh/gói có nguồn rõ ràng |
| Tiền | Thu thuê/cọc, phân bổ TM/CK cá nhân/CK công ty | Preview trước lưu ghi dự kiến thu; sau lưu dùng giao dịch thực đã ghi; không đồng nhất cọc với doanh thu |
| Bàn giao | borrow_hats, borrow_raincoats, collateral_description | Số nguyên không âm, tài sản mô tả riêng; không tự quy tiền |
| Người ký | contract_signer_a_name, contract_signer_b_name | Tên bắt buộc để chốt theo rule nghiệp vụ; chừa vùng ký giấy, không thêm chữ ký điện tử ngoài yêu cầu |
| Trả xe | Giờ thực tế, người nhận/trả, số thực hoàn, nội dung bổ sung | Trước trả để trống vùng xác nhận; không điền now() hoặc số cọc dự tính như đã hoàn |

Liệt kê lại mọi ô trống trên PDF và đánh dấu: nhập, lấy sẵn, tính, hoặc chỉ điền lúc trả. Đặc biệt kiểm NV, đại diện ủy quyền, gói thuê, số lượng xe, tài sản cọc và xác nhận trả xe. Không tự thay nội dung điều khoản trong mẫu; chỗ đọc không rõ phải đánh dấu để đối chiếu bản gốc.

### 4.3 Cách triển khai kỹ thuật

- Tách `ContractDocumentBuilder` thuần: nhận dữ liệu đã normalize, trả DTO phục vụ preview/print. Không save(), không cấp số, không thay xe/giao dịch. Tách khỏi hàm dựng snapshot đang có side effect.
- Tạo request validator riêng cho preview/chốt. Preview validation có thể nghiêm hơn lưu bản chưa đủ, nhưng không tự nới rule tạo đơn hiện tại.
- Đề xuất `POST /api/auth/order/car-rental/preview`: payload form, optional order_id/version; trả DTO cùng errors theo field, không mutation. Thêm scope kiểm quyền nếu preview đơn đã tồn tại.
- Đề xuất `GET /api/auth/order/car-rental/{order}/document`: trả DTO từ snapshot và version; không lấy khách/xe hiện tại thay nội dung hợp đồng đã khóa.
- Dùng một `ContractPrintDocument.vue` cho modal preview và trang in; CSS `@media print`, `@page` kích thước/lề đúng mẫu; nút/sidebar/backdrop không nằm trong print DOM. Chỉ in vùng tài liệu; chờ font/ảnh sẵn sàng.
- API trả dữ liệu có cấu trúc, template Vue escape text, không ghép dữ liệu khách vào HTML thô. Có thể dựng iframe cùng nguồn cho print, truyền dữ liệu an toàn sau auth; không gắn token vào URL.
- In trình duyệt/Save as PDF là lối ra P0; nếu cần file PDF tạo từ server, thêm renderer riêng tương thích runtime sau khi đo font và phân trang. Không coi browser print là tự động lưu trữ file PDF trên server.
- Với nhiều xe, nhiều liên hệ, tên/địa chỉ dài: thêm trang chi tiết/phụ lục có cùng số HĐ, giữ toàn bộ dữ liệu; không ép mọi thứ vào một dòng của mẫu.
- Sửa chốt: khóa row trong transaction → validate dữ liệu mới nhất → cấp/lưu số nếu chưa có → dựng snapshot chuẩn → is_locked/version/template_version → commit. Nếu đã khóa, trả nguyên bản.
- Đơn đã ký sửa qua phụ lục/version mới, có tham chiếu bản gốc; dữ liệu xác nhận trả xe là phần bổ sung riêng. In lại bản ký không tự đổi theo khách/xe/cấu hình mới.
- Nếu legacy có số chỉ ở snapshot, in/tìm kiếm dùng đúng số đó; không phát hành số mới chỉ vì orders.contract_number rỗng. Có công cụ đối soát riêng trước khi backfill.
- Nếu đổi dạng số sang `YYYYMMDD-0001`, chỉ áp dụng số mới sau mốc cấu hình; cập nhật generate/validate/search/tests đồng bộ, hỗ trợ cả dạng cũ khi đọc. Preview không giữ chỗ số, không hứa số tiếp theo.

### 4.4 Hoàn thành khi

- Có thể nhập đủ → preview ngay cạnh Lưu → in nháp → quay lại sửa → lưu → xem/in từ danh sách, không nhập lại.
- Preview 10 lần không tăng bộ đếm, không thêm orders/transactions, không đổi trạng thái xe.
- Bản chính thức in từ snapshot đã khóa; dữ liệu, mã và số tiền khớp lần chốt; không cắt chữ/dấu ở preview hoặc PDF.
- Kiểm bản in vật lý A4 và PDF trên desktop; mobile preview thao tác được. Có ảnh trước/sau, PDF mẫu dữ liệu giả và test kết quả, không chỉ báo build thành công.

## 5. Gói P0-B — Thu tiền và đối soát TM/CK cá nhân/CK công ty

1. Giữ layout hàng tiền đã căn; mở rộng PaymentMethod cho 3 loại nghiệp vụ: TM, CK cá nhân, CK công ty. Hỗ trợ thanh toán kết hợp, không ép chọn độc quyền khi thực tế thu nhiều loại.
2. Phân biệt `payment_method` (TM/CK) và chủ tài khoản. Không đổi ý nghĩa payment_method=3 hiện là kết hợp; không dùng Bank.account_type làm cá nhân/công ty vì field đó đang mô tả tài khoản thu/chi.
3. Đề xuất Bank.owner_type = personal/company/unknown; mỗi giao dịch CK snapshot loại chủ tài khoản tại thời điểm thu. Dữ liệu cũ để unknown cho tới khi đối soát, không đoán theo owner_name.
4. Nếu một lần có hai tài khoản CK, cần `payment_allocations[]` gồm kind, bank_id/cash_id, amount; chuyển thành các giao dịch hạch toán qua service chung. Có adapter giữ tương thích payload hiện tại.
5. Tổng phân bổ bằng khoản thu được xác nhận, bank/cash thuộc phạm vi cho phép, số tiền hợp lệ. Thu cọc, phí thuê, gia hạn, hoàn tiền có loại nghiệp vụ riêng.
6. Lưu đơn + các dòng phân bổ trong transaction và có idempotency key: double-click/retry không ghi hai lần. UI không báo đã thu trước khi server commit.
7. Báo cáo theo ngày/chi nhánh/người thu/phương thức/chủ tài khoản, drill down tới giao dịch; thu thực tế chỉ cộng giao dịch đã ghi, tách số phải thu và số dự kiến.
8. Test TM, CK cá nhân, CK công ty, cả ba, đổi tài khoản sau thu, hủy/hoàn, đối soát số cũ unknown; không thay phân loại lịch sử khi sửa danh mục ngân hàng.

## 6. Gói P1-A — Kho xe và phân quyền

### 6.1 Màn hình theo ảnh tham chiếu

- Menu Kho xe; đầu trang thẻ từng cơ sở và Thuê sở hữu, hiển thị tổng xe và cơ cấu loại. Chọn thẻ đổi phạm vi, giữ filter khi quay lại.
- Bên dưới: tìm biển số, trạng thái xe, loại xe, tình trạng định vị, chọn khoảng ngày; nút `Điều chuyển kho` có 3 lựa chọn trong yêu cầu.
- Bảng chi tiết cho người có quyền: biển số, loại, kho quản lý, nơi đang giữ xe, tình trạng thuê, định vị/cập nhật cuối, hợp đồng liên quan, lịch sử điều chuyển.
- Bản đồ/tab định vị chỉ xuất hiện khi có quyền và dữ liệu; có legend, last seen và trạng thái chưa kết nối. Không hiển thị marker giả như vị trí thật.
- Cơ sở không có quyền chi tiết chỉ thấy thẻ tổng hợp; endpoint cũng chỉ trả tổng hợp, không tải danh sách đầy đủ rồi che trong Vue.

### 6.2 Mô hình dữ liệu đề xuất

Giữ Store làm cơ sở nghiệp vụ; không mặc định đổi store_id nghĩa là đổi luôn nơi xe đang ở. Phải chốt định nghĩa trước migration:

| Dữ liệu | Vai trò |
|---|---|
| Store.kind | physical / lease_to_own; tên và mã lấy cấu hình dữ liệu thật |
| Vehicle.store_id | Kho/cơ sở quản lý để giữ tương thích hiện tại; quy tắc cập nhật phải duy nhất |
| Vehicle.current_store_id (nếu cần) | Nơi nhận/đang giữ xe, nullable khi đang vận chuyển; khác địa điểm GPS |
| vehicle_transfers | ID, type, from/to, status, requested/effective/received time, actors, reason, idempotency_key |
| vehicle_transfer_items | Xe, order/item liên quan, trạng thái trước/sau, odometer/biên bản; nhiều xe cho một phiếu nếu cần |
| vehicle_location_events | Lịch sử bất biến: xe, từ/đến, thời điểm thực tế/ghi nhận, loại nghiệp vụ, ref_id, người làm |
| contract_amendments / exchange links | Xe cũ/mới, hiệu lực, tiền điều chỉnh, tham chiếu snapshot ký; không ghi đè bản gốc |

Aggregate phải công bố cách đếm: tổng xe quản lý, xe có mặt, đang thuê, đang chuyển, hỏng, đã bán. Xe đang chuyển không được vừa tăng kho nhận vừa giữ trong tồn sẵn kho xuất. Kho thuê sở hữu tính riêng, không cộng kép với kho vật lý. Số xe từng loại cộng đúng tổng trong cùng định nghĩa.

### 6.3 Quyền đề xuất cần xác nhận

| Vai trò | Xem kho | Thao tác |
|---|---|---|
| Admin | Tổng hợp/chi tiết tất cả kho, lịch sử, định vị | Tạo/xác nhận điều chuyển theo quy trình; điều chỉnh qua phiếu có log |
| Nhân viên cơ sở | Tổng hợp như yêu cầu; chi tiết xe chỉ khi được cấp riêng | Đề nghị chuyển từ cơ sở mình, nhận xe về cơ sở mình; không thao tác kho khác tùy ý |
| Nhân viên công nợ | Danh sách hợp đồng/công nợ được phân công | Ghi chú, nhắc nợ, ghi nhận theo quyền; không tự đổi sở hữu xe |
| Người không có quyền | Không có dữ liệu riêng tư | API trả 403/404 theo quy ước; export/print/history cùng policy |

Trả xe khác cơ sở cần endpoint lookup tối thiểu theo biển số/hợp đồng, chỉ trả dữ liệu cần nhận xe. Không dùng yêu cầu này để mở quyền xem toàn bộ khách của cơ sở khác.

### 6.4 Ba luồng điều chuyển phải làm đủ

**A. Kho 1 → Kho 2**

1. Chọn xe đủ điều kiện tại kho nguồn, kho nhận khác nguồn, lý do, thời gian, người giao/nhận.
2. Tạo phiếu draft/requested → dispatch → received, hoặc xác nhận một bước nếu nghiệp vụ được duyệt; backend có trạng thái rõ ràng.
3. Dispatch giữ xe khỏi danh sách sẵn sàng, received cập nhật vị trí/kho đúng chính sách. Hủy trước dispatch giải phóng giữ chỗ; sau dispatch xử lý hoàn/nhận qua sự kiện, không xóa lịch sử.
4. Transaction khóa xe và phiếu; kiểm lại trạng thái và hợp đồng đang hoạt động. Hai nhân viên chuyển cùng xe chỉ một người thành công.

**B. Khách trả xe ở cơ sở khác**

1. Cơ sở nhận nhập biển số, tìm hợp đồng đang thuê hợp lệ, xác nhận thực tế/giờ trả/odometer/tình trạng.
2. Gắn sự kiện nhận khác cơ sở với luồng complete hiện hữu; giữ giờ nhân viên nhập, tính tiền đúng, không hoàn cọc hai lần.
3. Kho vị trí cập nhật nơi nhận theo chính sách; cơ sở doanh thu trên đơn lịch sử giữ nguyên, dòng tiền phản ánh nơi/người thực thu/hoàn. Cơ chế đối soát nội bộ cần phân biệt.
4. Trả xe và chưa thanh toán vẫn ghi nhận vị trí xe đúng, công nợ chưa tất toán; không bắt giả giao dịch để nhận xe.

**C. Đổi xe do hỏng/không phù hợp**

1. Chọn hợp đồng, xe đang thuê, xe thay thế, lý do, thời điểm đổi, tình trạng xe nhận lại.
2. Cùng cơ sở: kết thúc đoạn sử dụng xe 1 và mở đoạn xe 2; xe lỗi chuyển đúng trạng thái bảo trì/hỏng, không mặc định ready.
3. Khác cơ sở: liên kết các sự kiện giao/nhận giữa hai cơ sở với exchange; chốt rõ nơi khách nhận/trả xe và ai xác nhận từng bước.
4. Nếu có di chuyển vật lý, dùng phiếu dispatch/receive; chưa nhận không đánh dấu đã có mặt. Khóa logic các xe cùng lúc theo thứ tự ID để hạn chế deadlock.
5. Bảo toàn hợp đồng ký và khách, không nhân đôi tiền cọc; khác giá sinh khoản điều chỉnh/phụ lục được xác nhận. Không xóa item xe cũ làm mất lịch sử.

API đề xuất: `/auth/warehouses/summary`, `/auth/warehouses/{id}/vehicles`, `/auth/vehicle-transfers` và action dispatch/receive/cancel, `/auth/vehicle-exchanges`, `/auth/vehicles/{id}/movement-history`. Kiểm auth, scope, trạng thái, idempotency ở server cho mọi action.

## 7. Gói P1-B — Thuê sở hữu và công nợ

### 7.1 Chuyển giao diện và giữ dữ liệu cũ

- Đổi nhãn menu thành Thuê sở hữu theo yêu cầu, có kho riêng; giữ alias route cũ nếu link cũ đang dùng.
- Không chỉ rename bảng bán xe rồi dùng price_profit làm nợ. Tạo hợp đồng thuê sở hữu/kỳ phải trả/thu nợ riêng hoặc thêm subtype có migration kiểm chứng; báo cáo bán xe lịch sử vẫn truy xuất được.
- Công cụ chuyển dữ liệu lịch sử chỉ chạy khi có mapping từng đơn đã duyệt: giá trị gốc, đã trả, dư nợ đầu kỳ, ngày hiệu lực; migration schema không tự diễn giải nợ lịch sử.

### 7.2 Dữ liệu và phép tính

- lease_ownership_contracts: mã, khách, xe, kho thuê sở hữu, ngày hiệu lực, giá trị thỏa thuận, trạng thái, người phụ trách, liên kết đơn cũ nếu có.
- installments: số kỳ, due_at, amount_due, status; lịch trả cố định có version. Cấm sửa lùi kỳ đã có thanh toán bằng thay số trực tiếp.
- payment_allocations: liên kết transaction thực với kỳ và số tiền phân bổ; ghi hoàn/đảo riêng. Không cộng cả transaction và allocation thành hai khoản thu.
- debt_notes: người ghi, nội dung, ngày hẹn, phân loại, lịch sử; điểm cảnh báo khách dựa trên dữ liệu và quy tắc đã duyệt.
- Dư nợ = tổng nghĩa vụ đã ghi nhận − khoản thu được phân bổ + khoản đảo thu, theo cùng mốc báo cáo. Nợ quá hạn chỉ gồm phần đến hạn chưa thanh toán, không tính các kỳ tương lai.
- Đề xuất bucket chưa đến hạn / 1–7 / 8–30 / trên 30 ngày; ngưỡng cấu hình sau duyệt, không tự thêm lãi/phạt.
- Thu một phần, nhiều lần, thu trước hạn, phân bổ nhiều kỳ, trả dư, trả lại/thu hồi xe, tất toán, hủy đều có luồng riêng. Chốt chính sách phân bổ tự động hay nhân viên chọn kỳ.

### 7.3 UI và báo cáo

- Bảng: khách, xe, kho, tổng giá trị, đã thu, dư nợ, đến hạn kỳ này, ngày hẹn, số ngày chậm, phân loại, người phụ trách, ghi chú mới nhất.
- Bộ lọc tháng, đến hạn/quá hạn, khách, xe, nhân viên, nhóm nợ. Ghi chú và lịch sử theo từng khách/hợp đồng; chậm nhiều lần có cảnh báo rõ nguồn.
- Doanh số hợp đồng, thu thực tế và dư nợ trình bày riêng; không gọi mọi khoản phải thu là doanh thu đã nhận.
- Xuất Excel và PDF theo filter/quyền hiện tại, có ngày chốt số liệu, tổng khớp màn hình. Excel giữ số điện thoại/CCCD dạng text, xử lý giá trị có thể bị hiểu là công thức.
- Khách thuê thường cũng có danh sách đến hạn/trả xe/tiền còn thiếu; không trộn hạn trả xe với kỳ góp thuê sở hữu.

## 8. Gói P1-C — Nhắc khách và định vị

### Nhắc khách

1. Service xác định đối tượng đến hạn/quá hạn cho hai loại hợp đồng, loại khách đã tất toán/hủy, kiểm lần liên hệ gần nhất.
2. Danh sách việc cần làm cho nhân viên công nợ: khách, hợp đồng, hạn, dư nợ, lần nhắc trước, người phụ trách, kết quả liên hệ.
3. Job theo lịch chạy ở múi giờ Việt Nam, tạo notification outbox có khóa chống trùng theo hợp đồng/kỳ/kênh/mốc nhắc. Worker gửi, retry có giới hạn, lưu trạng thái provider.
4. Cần worker/scheduler thật khi deploy; khai báo hiện tại dùng queue sync không tự có lịch gửi nền. Thêm cấu hình và health check cho scheduler/worker.
5. Sandbox/dry-run dùng số thử được cấu hình; không gửi khách thật từ fixture. Chỉ bật gửi thật sau khi chủ hệ thống chốt kênh/lịch/nội dung và bật cấu hình tương ứng.
6. Test chạy job lặp, provider timeout, khách trả tiền giữa lúc tạo/gửi, thay số liên hệ, hủy hợp đồng, kiểm rate limit và không nhắc trùng.

### Định vị

1. Adapter provider → mapping device_id–vehicle_id → vị trí/sự kiện chuẩn hóa (tọa độ, GPS timestamp, server received time, speed/ignition nếu có).
2. Không coi không có dữ liệu là mất định vị; phân biệt never_connected, online, stale/offline, moving/stopped và unknown. Ngưỡng cấu hình theo chu kỳ thiết bị.
3. “Không hoạt động lâu” cần định nghĩa theo ignition/chuyển động và thời gian, không suy từ xe không có giao dịch; xe đang bảo trì có thể là ngoại lệ được cấu hình.
4. Bảng và bản đồ hiển thị cập nhật cuối, timezone, nguồn, độ cũ; báo cáo mất tín hiệu/ngừng hoạt động theo xe và hợp đồng để nhân viên xử lý.
5. Webhook xác thực, dedupe event; polling có giới hạn, retry/backoff; không để trình duyệt giữ API key nhà cung cấp. Hạn chế lưu lịch sử tọa độ theo nhu cầu nghiệp vụ.
6. Không có provider thật thì hoàn thành adapter/mock/UI, nhưng gắn trạng thái phụ thuộc bên ngoài; chưa đánh dấu nhiệm vụ định vị hoàn tất.

## 9. Gói P2 — Đơn trong ngày và thống nhất giao diện

- Đề xuất tab: Tạo hôm nay, Nhận xe hôm nay, Hẹn trả hôm nay, Giao dịch hôm nay. Tên tab phải nói đúng field thời gian; timezone Asia/Ho_Chi_Minh, khoảng ngày đầu inclusive/cuối exclusive.
- Tận dụng query/service đơn và tiền đang có, không duplicate cơ sở dữ liệu. Nếu chưa duyệt scope, chỉ thêm view/filter tối thiểu đã thống nhất.
- Dùng Inter, nội dung 400–500, tiêu đề 600–700; giữ logo, màu hệ thống và bố cục thẻ kho theo ảnh, không sao chép giao diện GPS rối/nhiều chữ nhỏ.
- Kiểm mọi modal/create/print/list ở 360,390,768,1024,1440px và zoom 125/150%; không cắt nhãn, số HĐ, ngày, GPLX, số tiền.

## 10. Lộ trình thực hiện và điểm dừng nghiệm thu

| Chặng | Công việc theo thứ tự | Sản phẩm bàn giao / điều kiện chuyển chặng |
|---|---|---|
| 0. Baseline | Fetch, đọc AGENTS/skill áp dụng, ghi HEAD/status; audit route/schema/form/PDF; dựng fixture local | Ma trận yêu cầu–file–test, danh sách quyết định và trạng thái baseline; không dùng .env làm nội dung báo cáo |
| 1. Preview/in | Mapping trường → validator/DTO → template → preview cạnh Lưu → in nháp → document snapshot → nút In ngoài danh sách | Nhân viên hoàn thành kịch bản P0-A, PDF có đủ dữ liệu và phụ lục |
| 2. Chốt/số/tiền | Sửa khóa snapshot và cấp số thống nhất; phân loại TM/CK cá nhân/CK công ty; đối soát | Retry không nhân đôi, báo cáo/snapshot khớp giao dịch, số cũ không đổi |
| 3. Pilot hợp đồng | Migration tương thích → deploy staging → UAT → bản chạy thử P0 | Có checklist đã ký nhận; lỗi tiền/snapshot/print trọng yếu = chưa phát hành |
| 4. Kho nền | Mô hình kho/vị trí, permissions, summary/chi tiết, ledger | Quyền API và tổng tồn đúng, chưa có điều chuyển giả |
| 5. Điều chuyển | A kho–kho → B trả khác cơ sở → C đổi xe cùng/khác cơ sở | Test concurrent/idempotent/rollback và lịch sử đầu-cuối qua UI |
| 6. Thuê sở hữu | Mapping dữ liệu cũ → lịch kỳ → thu/phân bổ → nợ/ghi chú → Excel/PDF | Nợ được đối soát theo bộ dữ liệu mẫu và số dư đầu kỳ đã duyệt |
| 7. Nhắc/GPS | Cấu hình provider → adapter/outbox/jobs → cảnh báo/màn hình → thử thật có kiểm soát | Có bằng chứng tích hợp thật, không chỉ mock/test job |
| 8. Đơn hôm nay/UAT cuối | View được duyệt, test hồi quy P0, hiệu năng/phân quyền, deploy và theo dõi | Mỗi yêu cầu có bằng chứng PASS hoặc ghi rõ phụ thuộc chưa xong |

Không đưa ước lượng giờ cố định trước khi khảo sát nhà cung cấp GPS, chính sách thuê sở hữu và migration dữ liệu thật. AI phải báo tiến độ theo sản phẩm/ca đã đạt, không theo số file đã sửa.

## 11. Kiểm thử bắt buộc và bằng chứng

| ID | Ca cần chạy | Kỳ vọng |
|---|---|---|
| C01 | Thiếu/đủ dữ liệu; nhập GPLX/ngày rồi chuyển ô | Thiếu field chỉ đúng nơi; đủ field preview hoạt động, không mất dữ liệu |
| C02 | Preview 10 lần, đóng/mở, lỗi mạng, response cũ | Không mutation DB, không tiêu thụ số; dữ liệu mới không bị response cũ ghi đè |
| C03 | Lưu/in/chốt/retry/in lại; sửa khách sau ký | Một số HĐ/một lần thu; tài liệu đã chốt không đổi |
| C04 | Legacy null/có số ở snapshot; tìm/in | Dùng đúng số lịch sử, không tự cấp số khác hoặc sửa snapshot ký |
| C05 | 1/2/nhiều xe, dữ liệu tiếng Việt dài, CCCD 0 đầu, ngày 02/01 | Bản in không cắt/dồn chữ, ngày tháng không đảo, phụ lục giữ đủ thông tin |
| C06 | Nhiều bậc giá, đời xe, giờ lẻ, thiếu giá, tùy chỉnh/gói | Giá preview/lưu/print cùng nguồn, thiếu bảng giá được báo rõ |
| C07 | Tính trước → hoàn tất → retry/đổi giờ/trả sớm/tất toán sau | Phí được hạch toán đúng đúng một lần; mốc hoàn thực tế chính xác |
| P01 | TM/CK cá nhân/CK công ty/kết hợp và tài khoản unknown cũ | Phân bổ/đối soát đúng; không đổi nghĩa enum hay lịch sử |
| W01 | Admin và từng cơ sở truy cập URL/API/export/lookup | Không lộ danh sách xe/khách/vị trí ngoài quyền |
| W02 | Hai phiên cùng chuyển một xe, retry nhận/hủy | Một chuyển trạng thái hợp lệ; không âm tồn hoặc đếm kép |
| W03 | Kho–kho, trả khác cơ sở, đổi cùng/khác cơ sở, lỗi giữa transaction | Ledger và snapshot đúng; rollback toàn bộ phần chưa commit |
| D01 | Thu một phần, nhiều kỳ, trả trước/dư, đảo thu, quá hạn sang tháng | Dư nợ và bucket đúng; báo cáo Excel/PDF khớp UI |
| D02 | Đổi nhãn/module, dữ liệu bán xe cũ | Lịch sử bán xe không bị chuyển thành nợ hay đổi lợi nhuận |
| N01 | Job chạy lặp, lỗi provider, thanh toán trước giờ gửi | Không nhắc trùng/nhắc khách đã trả; log không lộ credentials |
| G01 | Chưa kết nối, GPS trễ, event trùng/đảo thứ tự, xe dừng lâu | Trạng thái đúng định nghĩa, không vẽ tọa độ giả |
| U01 | Desktop/mobile/zoom/keyboard, print PDF và giấy A4 | Nút preview/in dễ tìm, label/ngày không cắt, không in sidebar |

Chạy unit/service tests và integration trên database riêng. Các ca concurrency/counter/transaction/JSON phải kiểm trên database cùng engine triển khai; SQLite pass chưa đủ. Dùng browser E2E cho input–blur, preview–print, mobile và vai trò. Fixture giả lập, không tạo đơn hoặc thu tiền thật để test.

Lệnh kiểm tra nền trong repo (kiểm tool/runtime thực tế trước chạy):

```powershell
git status --short
.\php.cmd artisan test
npm.cmd run production
npm.cmd run build:static
git diff --check
```

Không chạy cả production rồi build:static lặp nếu build:static đã bao gồm production và không có thay đổi mới. Lưu log exit code, số test/assertion, browser screenshot, PDF, kết quả DB đối soát; không chỉ dùng dòng “build thành công” để chứng nhận nghiệp vụ.

## 12. Migration, triển khai và rollback

1. Schema thêm theo hướng tương thích: nullable/default hợp lý cho dữ liệu cũ; không tự backfill số HĐ hoặc công nợ bằng suy đoán.
2. Trước backfill, xuất báo cáo đếm/tổng/checksum không chứa dữ liệu nhạy cảm không cần thiết; duyệt mapping và backup có thử restore.
3. Backfill từng batch có checkpoint/retry; có nhóm chưa phân loại, báo cáo ngoại lệ; không chặn đọc đơn lịch sử khi chưa phân loại xong.
4. Kiểm chuỗi deploy API/frontend và API contract tương thích phiên bản cũ. Kiểm cấu hình Render thực tế, commit chạy thực tế và migration tự động; push không đồng nghĩa live xong.
5. Staging bật feature theo từng nhóm; nghiệm thu P0 trước khi mở kho/thuê sở hữu. Không đặt secrets trong source/frontend/bundle/log; `.env` người dùng đang mở không phải tài liệu bàn giao.
6. Release có commit/tag, manifest, version, log migration, danh sách feature bật; worker/scheduler/notifications/GPS có kiểm tra sức khỏe riêng.
7. Rollback ưu tiên tắt feature và revert app tương thích schema mới; không drop cột/bảng đã chứa giao dịch thật. Giao dịch sai xử lý đảo/điều chỉnh có log, không xóa lịch sử.
8. Sau deploy xác minh UI đúng commit, preview/in hoạt động, permissions, đối soát tiền/tồn, queue/scheduler/provider; chưa xác minh thì ghi rõ.

## 13. Checklist giao việc cho AI thực hiện

- [ ] Đọc baseline, xác nhận đủ file PDF/ảnh; ghi rõ phần đã có và phần còn thiếu, không tin mọi kết luận cũ.
- [ ] Chốt/ghi giả định định dạng số, template in, quyền kho, thuê sở hữu, GPS và kênh nhắc.
- [ ] Hoàn thành P0 preview–lưu–in và tất cả field; chứng minh không side effect khi xem trước.
- [ ] Hoàn thành khóa/snapshot/số HĐ và phân loại tiền, kiểm lỗi hồi quy vòng 3.
- [ ] Bàn giao bản chạy thử hợp đồng riêng trước các module lớn.
- [ ] Hoàn thành kho, quyền server, ba luồng điều chuyển và ledger.
- [ ] Hoàn thành thuê sở hữu, công nợ/ghi chú/phân loại và Excel/PDF; bảo toàn bán xe cũ.
- [ ] Hoàn thành nhắc nội bộ và gửi tự động theo cấu hình đã duyệt.
- [ ] Hoàn thành GPS qua provider thật, cảnh báo có định nghĩa và báo cáo; nếu thiếu provider ghi BLOCKED riêng.
- [ ] Hoàn thành Đơn trong ngày đúng phạm vi thống nhất.
- [ ] Chạy toàn bộ ca bắt buộc, sửa mọi P0/P1, lưu bằng chứng và báo cáo đối soát.
- [ ] Commit/push đúng nhánh được phép; triển khai theo quyền trong phiên thực hiện, kiểm live sau deploy.
- [ ] Bàn giao hướng dẫn nhân viên, cấu hình, vận hành job, xử lý lỗi và rollback.

### Nội dung có thể gửi nguyên văn cho AI khác

> Đọc `docs/himoto-pilot-implementation-handoff.md`, mẫu `HĐTX Himoto A4.pdf` và các ảnh yêu cầu. Triển khai theo chặng, ưu tiên hoàn thành preview/in hợp đồng và thu tiền cho chạy thử trước. Kiểm repo và dữ liệu hiện hữu, không chỉ đổi nhãn hoặc thêm nút hình thức. Mỗi chặng phải có code, kiểm thử nghiệp vụ, ảnh/PDF minh chứng, tài liệu vận hành và trạng thái deploy trung thực. Những điểm chưa chốt phải tách rõ, tiếp tục phần độc lập; không tự chọn giá thuê, điều khoản thuê sở hữu, sửa số lịch sử hoặc gửi nhắc khách thật. Chưa có tích hợp GPS/provider thật thì không tuyên bố xong phần đó. Báo cáo cuối đối chiếu từng mục checklist với file/commit/test và việc còn thiếu.
