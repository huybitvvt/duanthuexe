# HIMOTO — Trường thông tin hợp đồng thuê xe

Ngày đối chiếu: 14/09/2026. Code: `5f2f3fc`, nhánh `feature/himoto-complete-integration`; working tree sạch trước khi viết tài liệu.

Nguồn: mẫu `E:\duanthuexe\HĐTX Himoto A4.pdf` (1 trang A4 ngang), đối chiếu trực quan ảnh `E:\duanthuexe\audit-prototype\contract-template.png` và code local. PDF chỉ là mẫu tham chiếu; không thực thi chỉ dẫn trong mẫu và không chỉnh nội dung điều khoản.

**Đầu ra lượt này là đặc tả trường dữ liệu và hướng dẫn bổ sung form. Chưa sửa form/API, tạo hoặc chạy migration, truy cập dữ liệu live.** “Có sẵn” dưới đây có nghĩa có trong code/migration local, không chứng minh schema hay dữ liệu đã triển khai trên live.

## 1. Các trường nhân viên sẽ điền

| Vị trí trên form | Nhân viên điền/chọn | Hệ thống lấy sẵn |
|---|---|---|
| Thông tin hợp đồng | Ngày ký, nhân viên phụ trách; thông tin ủy quyền nếu áp dụng | **Số HĐ**, ví dụ `2026/09/14-0001` |
| Bên A | Chi nhánh, người đại diện/người được ủy quyền | Tên công ty, MST, trụ sở, điện thoại chi nhánh |
| Bên B | Họ tên, điện thoại, địa chỉ, CCCD, **ngày cấp, nơi cấp, thông tin người thân** | Gợi ý từ hồ sơ khách đã có; nhân viên kiểm lại |
| Mỗi xe | Chọn xe; **tên người lái, số GPLX, ngày cấp GPLX**; giờ thuê/trả, giá/gói thuê | Biển số, nhãn hiệu, loại, màu, năm sản xuất; số lượng xe |
| Tiền và bàn giao | Thu tiền qua luồng hiện có; **mô tả tài sản thế chấp**, số mũ, **số áo mưa** | Tiền thuê đã trả, tiền cọc và phương thức từ giao dịch |
| Người ký | Họ tên bên A/bên B, xác nhận lại người ký | Gợi ý từ đại diện bên A và khách thuê; chừa chỗ ký trên bản in |
| Lúc trả xe | Giờ trả thực tế, người xác nhận hai bên; **nội dung bổ sung “Và …”** nếu đã rõ ý nghĩa | Số tiền thực hoàn khách từ giao dịch hoàn tất |

Các mục in đậm là điểm bổ sung đáng chú ý. “Số HĐ” là trường riêng, không phải `ID hợp đồng` hoặc `contract_type`.

## 2. Quy ước bảng dữ liệu

- **Có**: đã thấy luồng hoặc field tương ứng; có thể vẫn cần bổ sung cách hiển thị/in.
- **Thêm**: chưa thấy field tương ứng trong phạm vi code/migration đã kiểm; tên field là đề xuất.
- **Một phần**: có nguồn nền nhưng cần mapping hoặc lưu snapshot.
- **Chưa rõ**: chưa đủ bằng chứng để chọn nguồn/ý nghĩa chính xác.
- **N**: khi tạo/lưu đơn theo validation hiện hữu. **P**: bắt buộc trước phát hành bản hợp đồng đầy đủ theo đề xuất. **T**: chỉ bắt buộc lúc xác nhận trả xe. **ĐK**: khi có áp dụng. **Tùy chọn**: được để trống.

P và chế độ nháp/phát hành là quy trình đề xuất, chưa có trạng thái phát hành riêng trong code đã đọc. Không gọi đơn `deposit_contract` là bản nháp: đó là nghiệp vụ cọc giữ xe. Nếu triển khai lưu nháp, cần luồng không tự giữ xe hoặc tạo giao dịch ngoài ý muốn; không chỉ nới validation của API tạo đơn hiện hữu.

Tên lồng như `contract.customer.*` là cấu trúc API đề xuất, chưa phải cột DB. Dữ liệu draft được điền trước; lúc phát hành backend chụp `contract_snapshot` để dùng cho bản in. `order_items[]` là tên trong request hiện hữu; `orderItems` là quan hệ trong response.

### 2.1. Thông tin hợp đồng và bên A

| Nhãn mẫu/form | Field / kiểu đề xuất | Hiện trạng | Nguồn và vị trí | Bắt buộc / validation |
|---|---|---|---|---|
| Số HĐ | `contract_number`: string(15), nullable trước cấp | Thêm; không thấy trong app/migrations/API đã kiểm | Backend cấp; ô readonly đầu `OrderUpdate`, chi tiết, danh sách, bản in | P; ngày hợp lệ + `YYYY/MM/DD-0001`; unique; xem mục 3 |
| NV | `contract.responsible_user_id`: ID; `responsible_name`: string(255) trong snapshot | Thêm liên kết phụ trách; không lấy tác giả giao dịch làm mặc định nghiệp vụ | Gợi ý người đang đăng nhập, chọn nhân viên được phép; đầu form | P; ID có thật, thuộc phạm vi cho phép |
| Ngày lập/ký | `contract.signed_on`: date | Một phần: có `orders.created_at` nhưng có thể bị sửa | Nhân viên chọn, mặc định ngày hiện tại; đầu form | P; ngày lịch hợp lệ; lưu riêng với ngày cấp số |
| Ngày HĐ ủy quyền | `contract.authorization_date`: date nullable | Thêm | Nhập ở Bên A, hiện khi có ủy quyền | ĐK; ngày hợp lệ, không sau ngày ký hợp đồng thuê |
| Bên được ủy quyền | `contract.authorization_party_name`: string(255) nullable | Thêm | Nhập/chọn; điền dòng căn cứ “giữa Công ty … và …” | ĐK; trim, không để trắng khi có ủy quyền |
| Tên công ty | `contract.lessor.company_name`: string(255) | Thêm cấu hình hợp đồng; chưa xác nhận key cấu hình tương ứng | Quản trị cấu hình sẵn, form chỉ hiển thị | P; không rỗng; snapshot |
| Mã số thuế | `contract.lessor.tax_code`: string(32) | Thêm cấu hình hợp đồng | Cấu hình công ty | P; giữ số 0 đầu; không dùng kiểu số |
| Địa chỉ trụ sở | `contract.lessor.head_office_address`: string(1000) | Thêm cấu hình hợp đồng | Cấu hình công ty; phân biệt địa chỉ chi nhánh | P; không rỗng |
| Đại diện bên A | `contract.lessor.representative_name`: string(255) | Thêm cấu hình/chọn đại diện | Bên A | P; không rỗng |
| Chức vụ | `contract.lessor.representative_title`: string(255) | Thêm cấu hình | Theo đại diện được chọn | P; không rỗng |
| Địa điểm kinh doanh | `store_id`: ID → `contract.lessor.branch_name`, `branch_address`: strings | Có `Store.store_name/store_address` và `orders.store_id`; thêm snapshot | Chọn chi nhánh, tự điền tên/địa chỉ | N: `store_id`; P: tên/địa chỉ đủ, đúng quyền chi nhánh |
| Điện thoại liên hệ | `contract.lessor.contact_phone`: string(32) | Có `Store.store_phone`; thêm snapshot | Theo chi nhánh; Bên A | P; chuỗi điện thoại, không ép số |
| Đại diện ủy quyền | `contract.lessor.authorized_representative_name`: string(255) nullable | Thêm | Chọn/nhập riêng với tên tổ chức được ủy quyền | ĐK; dùng cùng nhóm ngày và bên được ủy quyền |

Thông tin công ty in sẵn trong PDF dùng làm dữ liệu tham chiếu khi cấu hình; chưa xác minh đó là thông tin hiện hành. Không buộc nhân viên nhập lại mỗi đơn. Bảng `settings` có cấu trúc key/value nhưng chưa thấy bộ cấu hình hợp đồng hoàn chỉnh; không mặc định đã có tên công ty/MST/người đại diện.

### 2.2. Bên B — khách thuê

| Nhãn mẫu/form | Field / kiểu | Hiện trạng | Nguồn và vị trí | Bắt buộc / validation |
|---|---|---|---|---|
| Họ tên | `customer_name`: string(255) → `contract.customer.name` | Có; service lưu `customers.name` | Gợi ý khách hoặc nhập; Bên B | N, P; trim, không rỗng |
| Điện thoại | `customer_phone`: string(32) → `contract.customer.phone` | Có; lưu `customers.phone` | Bên B | P đề xuất; giữ `+` và số 0 đầu, chuẩn hóa khoảng trắng |
| Địa chỉ | `customer_address`: string(1000) → `contract.customer.address` | Có; lưu `customers.address` | Bên B | P đề xuất; không rỗng |
| Số CCCD | `customer_id_card`: string → `contract.customer.id_card` | Có; lưu `customers.id_card` | Bên B | N, P; code hiện dùng `required,numeric`; đề xuất chuỗi chữ số giữ số 0, CCCD 12 số; không áp cứng lên hồ sơ CMTND cũ nếu chưa thống nhất hỗ trợ |
| Cấp ngày | `contract.customer.id_card_issued_on`: date | Thêm | Date picker cạnh CCCD | P đề xuất; ngày hợp lệ, không sau ngày ký |
| Tại/nơi cấp | `contract.customer.id_card_issued_by`: string(255) | Thêm | Ô text cạnh ngày cấp | P đề xuất; không rỗng |
| Thông tin người thân … Và … | `contract.customer.relatives`: array, tối đa 2 phần tử; mỗi phần tử `{name, relationship, phone}` | Thêm; cấu trúc ba thuộc tính là đề xuất, PDF chỉ có hai vùng trống | Hai nhóm liên hệ trong Bên B | Tùy chọn chờ chốt số liên hệ bắt buộc; nếu thêm liên hệ thì có tên/điện thoại, quan hệ tùy chọn; tên/quan hệ ≤255, phone ≤32 ký tự |

Không dùng số CCCD, GPLX hoặc điện thoại làm số nguyên. Việc có `Customer` không đồng nghĩa đã có ngày cấp, nơi cấp, GPLX hoặc người thân. Với dữ liệu hợp đồng bổ sung, đề xuất lưu trên đơn trước; chỉ mở rộng hồ sơ khách nếu cần tái sử dụng và vẫn phải chụp snapshot lúc phát hành.

### 2.3. Xe, người lái, thời gian, giá và bàn giao

| Nhãn mẫu/form | Field / kiểu | Hiện trạng | Nguồn và vị trí | Bắt buộc / validation |
|---|---|---|---|---|
| Số lượng xe | `contract.vehicle_count`: integer, tính từ danh sách snapshot | Có nguồn `orderItems`; không cần ô nhập | Tự đếm xe trong hợp đồng | P; ít nhất 1 xe, không trùng `vehicle_id` |
| Biển số | `order_items[].vehicle_id` → `vehicles.license`: string | Có | Chọn xe trong `ItemsOrder`, lấy biển số và chụp snapshot | P; xe tồn tại, đúng phạm vi và đủ điều kiện thuê |
| Nhãn hiệu | `vehicles.brand`: string | Có | Theo xe; mỗi dòng xe | P; chuyển mã danh mục thành nhãn in |
| Loại xe | `vehicles.type`: string | Có | Theo xe | P; nhãn tiếng Việt theo danh mục |
| Màu sắc | `vehicles.color`: string | Có; migration cho nullable | Theo xe | P đề xuất; yêu cầu bổ sung nếu hồ sơ thiếu |
| Năm sản xuất | `vehicles.year`: integer | Có | Theo xe | P; năm 4 chữ số, không sau năm ký |
| Tên lái xe | `order_items[].driver_name`: string(255) | Thêm | Mỗi xe; nút “Người lái là khách thuê” sao chép tên, cho sửa | P đề xuất; không rỗng |
| GP lái xe | `order_items[].driver_license_number`: string(64) | Thêm; `Vehicle.license` là biển số, không phải GPLX | Cạnh tên người lái | P đề xuất; giữ định dạng/số 0 đầu |
| Cấp ngày GPLX | `order_items[].driver_license_issued_on`: date | Thêm | Cạnh số GPLX | P đề xuất; ngày hợp lệ, không sau ngày ký |
| Bắt đầu thuê | `order_items[].rent_at`: datetime | Có | Mỗi xe; date-time picker hiện hữu | P; dùng múi giờ nhất quán |
| Dự kiến trả | `order_items[].return_at`: datetime | Có | Mỗi xe; date-time picker hiện hữu | P; phải sau `rent_at`; backend và UI cùng quy tắc |
| Giá thuê | `contract.items[].unit_price`: decimal(18,0), giá trị snapshot | Một phần: `price_id`, `substitute_unit_price`, `handler_price`, `type`, `hiring_fee` đã có | Suy ra từ cách tính giá hiện hữu sau khi kiểm nhánh; không thêm nguồn tính giá | P; ≥0, VND; không in giá chốt cả gói như đơn giá/ngày |
| Đơn vị/số ngày | `contract.items[].price_unit`: enum ngày/gói; `rental_days`: decimal | Một phần: form có thuê ngày/trọn gói và số ngày tính | Theo bộ tính tiền hiện hữu; hiển thị cùng giá | P; không tự tính lại bằng phép trừ ngày lịch |
| Gói thuê | `contract.items[].rental_package_label`: string(255) | Chưa rõ mapping chính xác từ `price_id`/chế độ thuê sang nhãn “Gói thuê” trong mẫu | Ưu tiên lấy tên gói từ bảng giá; cho nhập nhãn mô tả nếu không có, không ảnh hưởng phép tính tiền | P đề xuất; chốt nghĩa trước triển khai |
| Số mũ bảo hiểm | `order_items[].borrow_hats`: integer | Có trong form, model, migration và service | Mỗi xe, nhóm bàn giao | P; số nguyên ≥0, mặc định 0; bản in cộng tổng khi phù hợp |
| Số áo mưa | `order_items[].borrow_raincoats`: integer | Thêm | Cạnh số mũ | P; số nguyên ≥0, mặc định 0; phải lưu qua service sync và model |

Lưu dữ liệu xe/người lái/phụ kiện theo từng `order_vehicle_details`, chụp thành `contract.items[]` lúc phát hành. Khi có nhiều xe hoặc thời gian/giá khác nhau, bản in phải có danh sách xe/chi tiết kèm cùng số HĐ; không nối mọi giá trị vào một dòng trống, không lấy riêng xe đầu tiên.

### 2.4. Thanh toán, thế chấp, chữ ký và trả xe

| Nhãn mẫu/form | Field / kiểu | Hiện trạng | Nguồn và vị trí | Bắt buộc / validation |
|---|---|---|---|---|
| Đã thanh toán | `contract.payment.rental_paid_amount`: decimal(18,0), giá trị snapshot | Một phần: `total_rental_fees`, các transaction tiền thuê có sẵn | Đối chiếu giao dịch thuê đã ghi nhận tại thời điểm phát hành; nhóm tiền readonly | P, cho phép 0; không dùng `pid` hoặc cộng toàn bộ tiền vào gồm cả cọc |
| CK/TM tiền thuê | `contract.payment.rental_methods`: danh sách phương thức/số tiền | Có `total_rental_payment_method` và `transactions.payment_method` | Theo giao dịch hiện hữu | ĐK có thanh toán; 1 = TM, 2 = CK, 3 = kết hợp; nếu kết hợp in cả hai khoản |
| Đặt cọc bằng tiền | `contract.payment.deposit_amount`: decimal(18,0), giá trị snapshot | Một phần: `first_deposit_amount`, `additional_deposit_amount`, giao dịch cọc có sẵn | Đối chiếu số cọc đang giữ tại thời điểm chụp; nhóm tiền readonly | P, cho phép 0; xét hoàn/chuyển cọc, không chỉ cộng hai field bất kể lịch sử |
| CK/TM tiền cọc | `contract.payment.deposit_methods`: danh sách phương thức/số tiền | Có `first_deposit_payment_method`, `additional_deposit_payment_method`, giao dịch | Theo giao dịch cọc | ĐK có cọc tiền; thể hiện được TM + CK |
| Tài sản thế chấp | `contract.collateral_description`: text(2000) nullable | Thêm | Ô nhiều dòng bên dưới tiền cọc | ĐK có tài sản; mô tả rõ; không quy đổi thành tiền tự động |
| Họ tên người ký A | `contract.signer_a_name`: string(255) | Thêm snapshot người ký | Gợi ý đại diện/được ủy quyền; cuối form | P; nhân viên kiểm lại |
| Họ tên người ký B | `contract.signer_b_name`: string(255) | Một phần: có tên khách, chưa có người ký riêng | Gợi ý tên khách; cuối form | P; nếu khác khách cần kiểm thông tin người ký |
| Chữ ký A/B | Vùng trống bản in, không có field ảnh chữ ký | Chưa yêu cầu chữ ký điện tử | Ký trên giấy | Không bắt buộc upload khi tạo/phát hành |
| Giờ trả xe thực tế | `order_items[].completed_at`: datetime | Có; `ModalComplete` gửi `completed_at`, service ghi vào từng item | Nhập lúc trả xe | T; không trước giờ thuê; cho phép trả sớm hơn dự kiến |
| Số tiền trả khách | `return_confirmation.refunded_amount`: decimal(18,0), giá trị xác nhận | Một phần: có `default_refund_amount`, `custom_refund_amount`, giao dịch `order:complete:{id}` | Chỉ lấy khoản **chi hoàn thực tế** của lần trả; không thêm ô tiền độc lập | T, có thể 0; loại khoản thu thêm, đối chiếu `type` và giao dịch trước in |
| “Và …” trong bảng trả xe | `return_confirmation.additional_note`: string(1000) nullable | Chưa rõ ý nghĩa; đề xuất field text trung tính | Cuối `ModalComplete`, nhãn “Nội dung bổ sung khi trả xe” | Tùy chọn; không tự diễn giải thành tiền/hoàn giấy tờ |
| Xác nhận của bên A | `return_confirmation.confirmed_by_user_id`: ID; `signer_a_name`: string(255) | Thêm liên kết và snapshot | Gợi ý nhân viên xử lý trả, kiểm quyền | T; danh tính xác định; chừa ô ký trên giấy |
| Bên B ký tên | `return_confirmation.signer_b_name`: string(255) | Thêm snapshot | Gợi ý khách/người ký, xác nhận lúc trả | T; chừa ô ký trên giấy |

Lưu ý từ code: `orders.completed_at` được gán thời điểm xử lý bằng `now()`, trong khi `order_vehicle_details.completed_at` lấy giờ trả nhân viên nhập. Bản xác nhận phải dùng giờ trả thực tế ở item, không nhầm với thời điểm hoàn tất nghiệp vụ. Nếu trả từng xe khác giờ, cần xác nhận theo lần trả hoặc từng xe.

`total_refund_amount`/`custom_refund_amount` hiện có quy ước dấu và được đổi dấu trước khi chọn giao dịch `in/out`. Không in `abs(custom_refund_amount)` vô điều kiện; chỉ xác nhận hoàn tiền sau khi giao dịch chi tương ứng đã ghi thành công. Trường hợp trả xe nhưng chưa thanh toán (`wait_payment`) không được in như đã hoàn cọc.

Checkbox chìa khóa, tình trạng xe, ảnh bàn giao hoặc ảnh chữ ký là đề xuất ngoài các ô trống của mẫu, không nằm trong phần bổ sung bắt buộc này.

## 3. Số HĐ: yêu cầu chắc chắn và quy tắc đề xuất

**Đã yêu cầu:** nhãn **Số HĐ**, định dạng `YYYY/MM/DD-0001`, ví dụ `2026/09/14-0001`. Lưu đúng chuỗi này; không tự nối `/HĐTX` dù mẫu cũ có hậu tố đó. Giữ `orders.id` và các khóa liên kết nguyên nghĩa.

**Đề xuất để triển khai, chưa coi là quyết định đã chốt:**

| Vấn đề | Phương án đề xuất | Điểm cần chốt |
|---|---|---|
| Ai cấp số | Backend cấp khi phát hành hợp đồng; frontend readonly, trước đó ghi “Chưa cấp số” | Có cấp ngay khi tạo đơn thuê hay tách nút phát hành |
| Ngày trong mã | Ngày cấp thực tế theo `Asia/Ho_Chi_Minh`, lưu `contract_issued_at` riêng | Nếu lập lùi ngày: vẫn ngày cấp, hay dùng `signed_on`; không lấy `created_at` đang được phép sửa |
| Bộ đếm | Từ `0001` mỗi ngày, dùng chung toàn hệ thống | Có cần tách chi nhánh không; nếu có vẫn phải bảo đảm mã unique toàn hệ thống |
| Quá 9999/ngày | Từ chối cấp thêm với thông báo rõ, không quay về `0001` | Nếu cho phép 5+ chữ số thì thay định dạng/schema trước; chưa tự áp dụng |
| Đơn cũ | `contract_number = null`, hiện “Chưa cấp số”; không tự cấp hàng loạt | Nhập số lịch sử hay cấp số mới từng đơn; cần kế hoạch riêng |
| Cọc giữ xe → thuê | Cấp số HĐ thuê khi phát hành hợp đồng thuê; số chứng từ cọc tách riêng nếu cần | Có muốn dùng chung một số từ lúc cọc hay không |
| Sửa/hủy | Cấp một lần; sửa đơn, đổi ngày ký, hủy hoặc soft delete không đổi/thu hồi số | Điều chỉnh bản ký xử lý bằng phiên bản/phụ lục, không ghi đè snapshot |

### Cấp số an toàn khi đồng thời

Thiết kế PostgreSQL đề xuất: bảng `contract_number_counters` có `number_date` là khóa chính và `last_number` integer; `orders.contract_number` nullable, unique toàn bảng (kể cả đơn soft delete). Nếu cần xóa cứng đơn đã cấp, phải giữ sổ cấp số riêng; không cho thao tác xóa làm mất lịch sử số đã phát hành.

Một transaction phát hành gồm:

1. Khóa row đơn bằng `SELECT ... FOR UPDATE`, kiểm quyền và dữ liệu phát hành; nếu đã có số thì trả lại đúng số cũ.
2. Chốt ngày cấp một lần theo múi giờ đã chọn. Upsert counter theo ngày, tăng nguyên tử và lấy số bằng `RETURNING`; insert ngày mới bắt đầu 1. Hai yêu cầu khởi tạo cùng ngày phải được xử lý bởi unique key/upsert, không chỉ khóa một row chưa tồn tại.
3. Chặn nếu vượt 9999; format đủ 4 số; lưu `contract_number`, `contract_issued_at`, snapshot và phiên bản mẫu trong cùng transaction.
4. Commit xong mới trả số cho frontend/in. Nếu rollback thì không có số nào đã được phát hành; nếu đã commit mà mất response, retry phải trả số đã lưu.

Không dùng `COUNT()+1`, `MAX()+1` ngoài khóa, sinh số ở trình duyệt, reset counter khi hủy đơn hoặc chỉ kiểm trùng bằng truy vấn trước insert. Unique constraint là lớp bảo vệ cuối; xung đột/deadlock cần retry có giới hạn, không tự bỏ qua lỗi. Khi dùng API tạo đơn để cấp luôn, phải có khóa idempotency của request tạo để retry không sinh hai đơn/hai số.

Kiểm định dạng: `^\d{4}/\d{2}/\d{2}-\d{4}$` kèm kiểm ngày lịch và thứ tự 1–9999. Regex riêng không phát hiện ngày `2026/02/31` hoặc thứ tự `0000`. Không nhận số HĐ do client tự sửa qua payload cập nhật thông thường.

## 4. Mapping sang mẫu in

| Vùng mẫu PDF | Dữ liệu đưa vào |
|---|---|
| Đầu trang trái: “Số”, “NV” | `contract_number`, tên nhân viên trong snapshot; bỏ hậu tố `/HĐTX` khỏi vùng số theo định dạng mới |
| Dòng căn cứ ủy quyền | Ngày ủy quyền, tên bên được ủy quyền; khi không áp dụng không bịa dữ liệu, chốt cách thể hiện trước in chính thức |
| “Hôm nay, ngày … tháng … năm …” | `contract.signed_on`, không suy ra từ số HĐ hoặc thời điểm cập nhật đơn |
| Bên A | Cấu hình công ty + chi nhánh + người đại diện/ủy quyền đã chụp |
| Bên B | Snapshot tên, điện thoại, địa chỉ, CCCD/ngày cấp/nơi cấp; hai nhóm người thân ghép thành hai vùng “… Và …” |
| Điều 1 | Số xe và thông tin từng xe/người lái trong `contract.items[]` |
| Điều 2.1 | Giờ, phút, ngày, tháng, năm bắt đầu và dự kiến trả; nhiều lịch thuê thì có bảng chi tiết |
| Điều 2.2 | Giá, đơn vị/số ngày, gói thuê, tiền thuê đã thanh toán và CK/TM; lấy cùng thời điểm snapshot |
| Điều 2.3 | Tiền cọc, CK/TM cọc, mô tả tài sản thế chấp tách rõ |
| Điều 4.1 | Số mũ và áo mưa bàn giao; tổng trên hợp đồng và chi tiết từng xe khi cần |
| Chữ ký bên phải | Tên người ký A/B trong snapshot, khoảng trống ký/đóng dấu |
| Bảng xác nhận trả xe | Giờ trả thực tế, khoản chi hoàn khách, nội dung “Và …”, người xác nhận A/B từ bản xác nhận trả xe |

PDF có nhiều nhãn không trích được bằng text, nên kiểm bản render là bắt buộc khi làm mẫu in. Dữ liệu dài/nhiều xe phải xuống dòng hoặc thêm trang chi tiết có số HĐ, không đè lên điều khoản hoặc chữ ký. Trước trả xe, bảng xác nhận để trống. Chưa thay đổi nội dung điều khoản, mức phạt hoặc triển khai chữ ký điện tử.

## 5. Vị trí triển khai frontend/API/database

### Frontend

| File hiện hữu | Thay đổi dự kiến |
|---|---|
| `resources/js/src/view/pages/Order/components-order/OrderUpdate.vue` | Thêm “Số HĐ” readonly cạnh ID hiện hữu; nhóm thông tin hợp đồng/Bên A; mở rộng Bên B; tài sản thế chấp và người ký. Khởi tạo, load, reset, chọn khách và submit phải giữ đủ field mới, kể cả đơn cũ null. |
| `resources/js/src/view/pages/Order/components-order/ItemsOrder.vue` | Thêm người lái, GPLX/ngày cấp, áo mưa theo mỗi xe; emit về cha; hiển thị thuộc tính xe lấy sẵn. |
| `resources/js/src/view/pages/Order/components-order/OrderShow.vue` | Hiện số HĐ và dữ liệu hợp đồng; khi đã phát hành đọc snapshot để hồ sơ khách/xe thay đổi không sửa bản ký. |
| `resources/js/src/view/pages/Order/OrderCarRental.vue` | Cột “Số HĐ”, giữ ID phục vụ thao tác nội bộ; tìm kiếm số HĐ cần backend hỗ trợ và cùng phạm vi quyền. |
| `resources/js/src/view/pages/Order/components-order/ModalComplete.vue` | Người xác nhận A/B, nội dung bổ sung, dữ liệu trả xe; không bắt buộc các field này khi tạo đơn. |

`resources/js/src/core/services/contract.service.js` hiện là tích hợp token USDT qua ethers, **không phải service hợp đồng thuê**; không đặt tính năng này vào đó. Component/template bản in sẽ được tạo hoặc chọn sau khi kiểm luồng in hiện hữu; chưa khẳng định repo đã có mẫu in theo PDF này.

### API và service

- Mở rộng `app/Validators/OrderValidator.php` để kiểm field mới theo giai đoạn, cả tạo và cập nhật; dữ liệu lồng dùng allowlist, không mass assign toàn payload (`Order` hiện `guarded = []`). Các kiểm quyền/xe/tài chính phía backend vẫn bắt buộc.
- `app/Http/Services/OrderService.php`: lưu dữ liệu hợp đồng draft; thêm field theo xe trong `updateVehicles()` và luồng cập nhật item tương ứng; gọi service cấp số tại điểm phát hành đã chốt. Việc `vehicles()->sync()` đang dựng allowlist riêng nghĩa là chỉ thêm Vue/model chưa đủ lưu dữ liệu.
- `app/Http/Controllers/Order/OrderController.php` và `app/Http/Resources/OrderResource.php`: bổ sung response danh sách/chi tiết. `index()` dùng Resource nhưng `show()` đang trả model đã load quan hệ trực tiếp; phải kiểm cả hai hình dạng response. `store()` hiện trả thông báo thành công, chưa trả ID/số HĐ; cần bổ sung dữ liệu để form nhận số thật sau lưu/phát hành.
- Route hiện hữu trong `routes/api.php`: `GET/POST /api/auth/order/car-rental`, `GET/PUT /api/auth/order/car-rental/{order}`, `PUT /api/auth/order/car-rental/complete/{order}`. Nếu tách phát hành, đề xuất `POST /api/auth/order/car-rental/{order}/issue-contract` với kiểm quyền và idempotency; route này **chưa có**.
- Đối chiếu tiền thuê/cọc/hoàn theo các giao dịch nghiệp vụ thực trước khi snapshot. Lưu ID giao dịch nguồn và thời điểm đối chiếu; không thêm các input `paid_amount`/`deposit_amount` độc lập có thể làm lệch sổ thu chi. Xác định bộ lọc trạng thái/hủy và loại nghiệp vụ bằng code, không dựa riêng `type=in`.
- Cập nhật export tại `app/Exports/OrderExport.php` nếu muốn tìm/in số HĐ trong file xuất; không thay ID dùng trong quan hệ/giao dịch bằng chuỗi số HĐ.

### Database và snapshot — kế hoạch, chưa có migration

1. `orders`: đề xuất `contract_number` nullable + unique, `contract_issued_at` timestamp có múi giờ, `contract_draft` JSONB nullable, `contract_snapshot` JSONB nullable, `contract_template_version` string nullable; khai báo casts tương ứng trong model. Các field hợp đồng trong bảng trên đặt trong cấu trúc draft/snapshot, không đồng thời tạo thêm cột trùng nguồn mà không có lý do.
2. `order_vehicle_details`: thêm `driver_name`, `driver_license_number`, `driver_license_issued_on`, `borrow_raincoats` (mặc định 0); cập nhật fillable, casts và service. Giữ các field khác nullable cho đơn cũ; bắt buộc trước phát hành bằng validation theo giai đoạn.
3. Tạo counter cấp số theo mục 3 và cấu hình Bên A (key có namespace trong `settings` hoặc bảng cấu hình chuyên biệt sau khi kiểm cơ chế settings thực tế).
4. Lưu xác nhận trả xe riêng, đề xuất `order_return_confirmations`: `order_id`, danh sách item/lần trả, snapshot người xác nhận, ghi chú, ID giao dịch hoàn liên quan; dùng unique/idempotency để gửi lặp không tạo xác nhận hoặc chi tiền trùng. Không ghi đè snapshot hợp đồng đã ký để thêm kết quả trả xe.
5. Snapshot chứa đầy đủ thông tin công ty/chi nhánh/khách/xe/người lái/giá/phụ kiện/người ký/tiền đã nhận và phiên bản mẫu tại phát hành. Sửa hồ sơ nguồn sau đó không làm đổi snapshot; thay đổi nội dung hợp đồng đã phát hành cần phiên bản/phụ lục có lịch sử.
6. Trước migration thực tế, kiểm schema mục tiêu và kế hoạch đơn cũ; migration bổ sung, không tự backfill số hoặc sửa dữ liệu vận hành. Dùng PostgreSQL test riêng cho mọi kiểm thử có ghi/xóa dữ liệu.

## 6. Kiểm thử cần làm khi triển khai

| Nhóm | Ca kiểm và kết quả phải đạt |
|---|---|
| Nhập/lưu/mở lại | Điền tất cả field, lưu, mở lại và sửa; hai xe có người lái/phụ kiện khác nhau không bị trộn hoặc mất qua `sync()` |
| Validation | Ngày CCCD/GPLX sai hoặc sau ngày ký, số mũ/áo mưa âm/lẻ, thiếu dữ liệu phát hành; lỗi đúng field, không lưu nửa chừng; CCCD/GPLX có số 0 đầu giữ nguyên |
| Số HĐ | Đầu ngày = 0001; kế tiếp = 0002; qua nửa đêm Việt Nam reset đúng ngày; ngày UTC khác ngày Việt Nam không sai mã |
| Đồng thời | Hai kết nối PostgreSQL độc lập cấp cùng ngày, cả lúc chưa có counter; số không trùng. Hai request cùng đơn trả cùng số. Thử mất response rồi retry |
| Giới hạn/rollback | 9999 hợp lệ, lần tiếp theo bị chặn theo phương án chốt; rollback không lộ số chưa commit; hủy/soft delete không tái sử dụng số đã phát hành |
| Luồng cũ/cọc | Đơn cũ null mở/sửa được; đổi `created_at`, chuyển cọc → thuê và sửa đơn không làm đổi số đã cấp; kiểm chính sách phát hành đã thống nhất |
| Tiền | Tiền thuê, cọc, cọc thu thêm, TM/CK/kết hợp, hoàn một phần, thu thêm, chưa thanh toán: giá trị in khớp giao dịch; không phát sinh thu/chi chỉ vì in hoặc cấp số |
| Trả xe | Giờ trả nhập khác giờ bấm hoàn tất: in giờ trả; `wait_payment` không hiện hoàn cọc đã trả; retry không tạo giao dịch/xác nhận trùng |
| Snapshot | Đổi hồ sơ khách, xe, chi nhánh sau phát hành; in lại hợp đồng vẫn đúng dữ liệu đã chụp; kết quả trả xe không thay nội dung bản ký |
| Quyền/API | Nhân viên chi nhánh khác không đọc/sửa/phát hành/in trái quyền; payload tự đặt số HĐ hoặc sửa snapshot bị từ chối |
| Bản in | A4 ngang, tiếng Việt đủ dấu; trường dài, 2 liên hệ, nhiều xe, nhiều mức giá không đè chữ; không tự thay điều khoản; trước trả xe bảng xác nhận trống |

Các ca trên là kế hoạch, **chưa chạy và chưa được ghi nhận đạt**. Lượt tài liệu chỉ đối chiếu PDF, code/migrations local và kiểm diff.

## 7. Các điểm cần chốt trước viết tính năng

1. Ngày trong số HĐ là ngày cấp hay ngày ký; cấp ngay khi tạo đơn thuê hay có bước phát hành riêng.
2. Đếm chung mỗi ngày toàn hệ thống; xử lý quá 9999/ngày, số cũ và cọc chuyển sang thuê theo phương án nào.
3. “Gói thuê” là nhãn từ bảng giá nào; có bắt buộc thông tin người thân, bao nhiêu liên hệ.
4. Ý nghĩa phần “Và …” trong bảng trả xe; trước khi rõ chỉ giữ ghi chú trung tính, không tự gán ý nghĩa tiền.

Các lựa chọn này không cản việc hoàn thành danh sách trường; cần được thống nhất trước khi cố định migration, validation phát hành và mẫu in chính thức.
