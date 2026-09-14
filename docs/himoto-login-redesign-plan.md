# HIMOTO — Kế hoạch và prompt làm lại giao diện đăng nhập

## 1. Mục tiêu và hiện trạng đã đọc

Thiết kế lại `/login` đồng bộ nhận diện HIMOTO, dễ dùng trên máy tính và điện thoại. Làm trực tiếp bằng Vue 2 + HTML/CSS trong dự án hiện tại; sản phẩm bàn giao là trang hoạt động được, không chỉ ảnh mockup.

Các điểm xác nhận trong repo khi lập kế hoạch:
- Component: `resources/js/src/view/pages/auth/Auth.vue` (tên component `Auth`).
- Router: `resources/js/src/router.js`. Auth còn liên quan đăng ký/quên mật khẩu/reset; phải đọc toàn bộ route trước khi thay markup.
- Auth Vuex: `resources/js/src/core/services/store/auth.module.js`, action `LOGIN` gọi `POST /api/auth/login`, `SET_AUTH` cập nhật token/header và `authSessionId`; `PURGE_AUTH` đổi session và xóa trạng thái.
- Form hiện dùng FormValidation, Bootstrap, KTUtil và SweetAlert. Luồng signin có `setTimeout(..., 2000)` giả lập; spinner được gỡ trước khi request chắc chắn kết thúc.
- Có xử lý network error thiếu guard: `catch(({response}) => ...)` truy cập `response.data.error` khi response có thể không tồn tại. Nếu sửa để hỗ trợ lỗi mạng của login, giới hạn vào action liên quan.
- CSS login hiện nhập `resources/js/src/assets/sass/pages/login/login-1.scss` không scoped.
- Bộ nhận diện: `resources/js/src/assets/himoto/_variables.scss`; logo trong `public/images/branding/`.
- Email/mật khẩu mặc định trong source là rỗng. Dữ liệu đang hiện trong ảnh người dùng không phải yêu cầu hardcode tài khoản.

## 2. Concept thiết kế được đề xuất

Tên concept: **HIMOTO — Cổng vận hành**. Tông gọn, rõ và đồng bộ Dashboard.

### Desktop từ 1024px

- Canvas tối thiểu cao bằng viewport, hai cột khoảng 46% thương hiệu / 54% form.
- Cột trái nền đỏ gradient `#C81018` → `#ED1C24`, logo HIMOTO đúng tỷ lệ ở phía trên, khoảng thở 48–64px.
- Khối thông điệp chính: “Vận hành đội xe,\ntrong một hệ thống.”; dòng phụ “Quản lý xe, đơn thuê và thu chi tại HIMOTO.”
- Phía dưới có họa tiết đường cong/đường di chuyển mảnh bằng CSS hoặc SVG trang trí, độ tương phản thấp, `aria-hidden="true"`. Không cần ảnh mạng, số liệu giả hay hiệu ứng chuyển động liên tục.
- Cột phải nền `#F5F7FA` hoặc trắng; form trắng rộng tối đa 440px, padding 32–40px, bo 18px và bóng nhẹ. Không lồng nhiều card.
- Header form: nhãn “HỆ THỐNG QUẢN LÝ HIMOTO”, tiêu đề “Đăng nhập” cỡ 28–32px, mô tả “Sử dụng tài khoản được cấp để tiếp tục.”
- Hai input Email/Mật khẩu cao 50–52px, nhãn nằm ngoài ô, bo 10px, viền rõ. Khoảng cách nhóm 20px.
- Nút chính toàn chiều ngang, cao 52px, nền đỏ đậm `#C81018`, chữ trắng. Vàng `#FFF200` dùng cho logo/điểm nhấn, không dùng làm chữ nhỏ trên nền trắng.
- Footer form: “Cần hỗ trợ truy cập? Liên hệ quản trị viên.” Dùng văn bản nếu chưa có kênh hỗ trợ xác nhận; không bịa hotline/link.
- Font kế thừa dự án (`Inter` nếu đã có, fallback Segoe UI/system); không thêm tải font bên ngoài.

### Tablet/mobile

- Dưới 1024px chuyển một cột, thay panel lớn bằng header thương hiệu gọn có logo.
- Dưới 768px: padding ngoài 20px; form rộng 100%, tối đa 440px; input chữ tối thiểu 16px; tiêu đề 26–28px.
- Không ép chiều cao cố định. Dùng `min-height: 100vh; min-height: 100dvh` với fallback; cho phép cuộn khi mở bàn phím hoặc ở màn hình thấp.
- Không dùng `overflow: hidden` trên body để giấu lỗi; không khóa cuộn hoặc cắt nút đăng nhập. Không có scrollbar lồng nhau vô ích.
- Logo dùng bản phù hợp với nền: `logo-himoto.svg` trên đỏ; xem trực tiếp `logo-himoto-dark.svg` trước khi dùng trên trắng. Không kéo méo/đổi màu logo để vừa layout.

## 3. Hành vi form phải hoàn thiện

1. Một `<form @submit.prevent="onSubmit">`; Enter và click dùng cùng handler. Nút submit `type="submit"`.
2. Email có label/ID, `type="email"`, `autocomplete="username"`, `autocapitalize="none"`, `spellcheck="false"`. Trim email; không tự đổi hoặc trim mật khẩu.
3. Mật khẩu `autocomplete="current-password"`, mặc định ẩn. Nút hiện/ẩn `type="button"`, tên truy cập “Hiện mật khẩu”/“Ẩn mật khẩu”, thao tác không submit form.
4. Kiểm rỗng/sai email ở frontend; không tự thêm quy tắc độ dài mật khẩu cho tài khoản cũ. Lỗi tiếng Việt ngay dưới field, `aria-invalid`, `aria-describedby`; focus lỗi đầu tiên.
5. Valid thì gửi LOGIN ngay, bỏ chờ giả lập 2 giây. Một cờ `isSubmitting` khóa gửi lặp; nút hiển thị “Đang đăng nhập…” và spinner gọn trong lúc request pending, dùng `finally` trả trạng thái.
6. Sai thông tin: “Email hoặc mật khẩu không đúng.”; offline/timeout: “Không thể kết nối. Vui lòng kiểm tra mạng và thử lại.”; 5xx: “Hệ thống đang gặp sự cố. Vui lòng thử lại sau.” Xử lý theo status thực tế của controller (kể cả nếu backend dùng status khác thông lệ); lỗi 422 map đúng field.
7. Hiển thị lỗi chung cạnh form với `role="alert"`; không chỉ đổi viền đỏ, không render lỗi bằng `v-html`, không lộ stack trace/token.
8. Đăng nhập thành công đi qua Vuex và router hiện hữu. Đọc guard, giữ đích đến theo role (đặc biệt role 4), không dùng `window.location` để tải lại tài liệu. Không tự thêm redirect query bên ngoài ứng dụng.
9. Giữ session generation và reset cache đã sửa. Không thay JwtService, cookie/token strategy hoặc auth backend để làm đẹp giao diện.
10. Không thêm checkbox “Ghi nhớ đăng nhập”, Google login, đăng ký hoặc quên mật khẩu nếu chưa kiểm chức năng hoạt động thực. Với các flow đang tồn tại, giữ tương thích hoặc tách component sau khi đã đối chiếu route; không xóa mù.
11. Tab/Shift+Tab theo thứ tự tự nhiên, focus nhìn rõ, icon button vùng chạm tối thiểu 44px. Kiểm contrast chữ thường tối thiểu 4.5:1; ưu tiên nút đỏ đậm thay vì chữ trắng trên đỏ sáng nếu không đạt.
12. Animation nếu có chỉ fade nhẹ 150–200ms và tôn trọng `prefers-reduced-motion`. Giữ autofill/password manager, không chặn paste mật khẩu.

## 4. Kế hoạch thực hiện theo giai đoạn

### A — Chốt hiện trạng và phạm vi

Đọc `AGENTS.md` áp dụng, `git status`, branch/HEAD. Người khác có thể đang sửa repo; không reset hoặc ghi đè thay đổi ngoài nhiệm vụ. Đối chiếu HEAD với bản main hiện tại để không làm trên nguồn cũ.

Đọc Auth.vue toàn bộ, router.js và guard trong entrypoint, auth.module.js, jwt.service.js, api.service.js, CSS login cũ, tokens và logo. Ghi ngắn đích đến từng role và các route auth thực sự dùng.

Chụp baseline desktop/mobile với input rỗng hoặc dữ liệu tổng hợp. Tìm căn nguyên scrollbar/font quá lớn từ DOM, viewport và CSS; ảnh người dùng không đủ để kết luận browser zoom.

Đầu ra: danh sách file định sửa và baseline; tiếp tục triển khai theo concept này, không dừng chỉ để xin duyệt kế hoạch lại.

### B — Dựng layout và branding

Sửa template Auth.vue; có thể tách `HimotoAuthLayout.vue` nếu nhiều auth form thực sự cần dùng chung. Không dựng lại cả dashboard.

Đặt CSS dưới `.himoto-auth` hoặc scoped style, ví dụ file mới `resources/js/src/assets/himoto/_auth.scss` được import bởi Auth. Scope mọi selector `input`, `button`, `label`; không đổi global `.btn-primary`, `.form-control` hoặc font-size gốc.

Kiểm token có khả dụng ở `/login` không. Nếu chỉ được nạp trong dashboard, import đúng phần tokens hoặc dùng fallback trong phạm vi auth; không import layout sidebar toàn cục chỉ để lấy màu.

Giữ CSS cũ cho flow còn phụ thuộc; chỉ bỏ import cũ khi xác nhận toàn bộ trạng thái signup/forgot/reset không bị hỏng. Nếu dùng `v-if` làm form biến mất, không khởi tạo FormValidation trên node null.

Đầu ra: trang có đủ layout desktop/mobile, logo và trạng thái input, không asset lỗi.

### C — Nối tương tác và sửa chờ giả

Chọn một hệ thống submit/validation: Vue handler hoặc FormValidation được nối đúng. Không để plugin SubmitButton và Vue handler gửi request hai lần. Nếu bỏ plugin, gỡ init/listener tương ứng; nếu giữ, destroy khi component unmount.

Thực hiện mục 3; chỉnh action LOGIN tối thiểu nếu cần xử lý lỗi không có response. Kiểm báo lỗi không phát sinh unhandled rejection. Nút phải giữ loading suốt pending, sau lỗi có thể thử lại.

Đầu ra: đăng nhập qua store thật, frontend không tự cấp token, không hardcode credential.

### D — Kiểm tra có bằng chứng

Tạo test tập trung `tests/test_login_ui.py` hoặc mở rộng suite auth hiện hữu. Chỉ test hành vi liên quan thay đổi.

| Nhóm | Kịch bản | Điều kiện đạt |
|---|---|---|
| Visual | 360×800, 390×844, 768×1024, 1024×768, 1440×900 | Logo đúng; nút không bị cắt; không tràn ngang; header và form cân đối |
| Màn hình thấp | 1366×600, zoom 200%/bàn phím mobile kiểm thủ công | Cuộn tới mọi field/nút được; không overlay che thao tác |
| Form | Rỗng, email sai, Enter, hiện/ẩn mật khẩu | Validation đúng; không gửi khi invalid; toggle không submit |
| Pending | API trì hoãn, double-click/Enter liên tiếp | Một POST login; loading tới khi request hoàn tất; không có delay cố định trước request |
| Lỗi | Sai tài khoản, 422, 500, mất mạng | Thông báo Việt rõ ràng; không lỗi JS; cho thử lại |
| Thành công | Role 1/2/3/4 với fixture riêng | Store/session cập nhật; router đi đúng quyền; không document reload |
| Vòng đời | Logout rồi login lại, Back/Forward; route auth khác đang dùng | Không listener trùng, không rò cache phiên trước, không phá form khác |
| Keyboard | Tab, Shift+Tab, Enter, focus lỗi | Mọi control thao tác được và có tên/nhãn truy cập |

Test fixture phải gọi luồng UI qua input/submit thật, không thay bằng `commit('setUser')` rồi tuyên bố đã test đăng nhập. Assert token/state chỉ trong test, không ghi token vào artifact.

Phân biệt bằng chứng: mock API kiểm hành vi UI; login thật qua API test kiểm tích hợp. Nếu thiếu môi trường test/tài khoản hợp lệ, ghi phần đó chưa kiểm; không dùng lại mật khẩu trong lịch sử chat để tự đăng nhập.

Chạy từ `E:\duanthuexe\happyride-1.1`:

```powershell
git diff --check
npm run build:static
# Sau khi đã viết suite có các cờ tương ứng và mở static-dist bằng server hỗ trợ SPA fallback:
python tests/test_login_ui.py --base-url http://localhost:8091 --output-dir ../audit-prototype/login-redesign
# Nếu sửa auth.module.js, chạy regression cache trên server local đó:
python tests/test_user_switching_cache.py
```

Build dùng API URL theo cấu hình hiện hữu của môi trường test. Không tự đoán cờ test đã tồn tại. Nếu server 8091 bận, chọn cổng khác và điều chỉnh test thay vì dừng process không thuộc nhiệm vụ. Không dùng server static 404 mọi deep-link rồi quy lỗi cho router.

### E — Bàn giao và phát hành

- Chụp ảnh desktop/mobile và trạng thái lỗi/loading bằng dữ liệu tổng hợp; lưu `audit-prototype/login-redesign/` tại workspace cha.
- Viết `review.md`: commit, file sửa, lệnh/exit code, ảnh, những mục chưa xác minh. Không tuyên bố toàn dự án production-ready từ test login.
- Báo diff và build thành công; không sửa version.json bằng tay. Ghi nhận source commit tại build và xác minh lại metadata ở lần build deploy.
- Chuẩn bị branch/PR riêng nếu phù hợp workflow repo. Merge/push vào main có thể tự deploy; chỉ thực hiện khi đã có ủy quyền tương ứng. Nhiệm vụ mặc định kết thúc bằng bản local có thể review và hướng dẫn release.
- Sau release được duyệt: kiểm `/login`, refresh deep-link, logo 200, version live đúng commit deploy, đăng nhập/logout và một lượt menu. Không đổi database, migration hay gói Render để thực hiện giao diện này.

## 5. Prompt giao AI khác — sao chép nguyên khối

```text
Hãy triển khai giao diện đăng nhập HIMOTO trong repo E:\duanthuexe\happyride-1.1.
Đọc và thực hiện docs/himoto-login-redesign-plan.md. Đây là yêu cầu làm ra giao diện
hoạt động và kiểm chứng, không chỉ viết lại plan hoặc tạo ảnh.

Concept: desktop 2 cột, bên trái branding đỏ gradient với logo HIMOTO có sẵn,
bên phải form gọn max-width 440px; mobile 1 cột. Dùng tiếng Việt, màu đỏ/vàng
HIMOTO, input 50–52px, nút đỏ đậm toàn chiều ngang. Logo giữ nguyên tỷ lệ.
Không thêm số liệu giả, link hỗ trợ giả hoặc chức năng chưa có backend.

File chính là resources/js/src/view/pages/auth/Auth.vue. Đọc router.js và toàn
bộ auth flow trước khi sửa vì component còn chứa signup/forgot/reset.
Scope CSS dưới .himoto-auth; không làm biến dạng dashboard. Giữ Vue 2/Laravel
và token/session hiện hữu. Bỏ chờ giả 2 giây ở signin; một submit handler,
loading theo Promise thật, chặn gửi trùng, hiện/ẩn mật khẩu, lỗi inline Việt,
keyboard/autofill hoạt động. Sửa network-error guard trong action LOGIN nếu cần.
Không xóa authSessionId hoặc logic reset cache. Không hardcode tài khoản.

Thực hiện A→E trong tài liệu. Chụp baseline rồi sửa, build static, test đủ
valid/invalid/pending/offline/success theo role và responsive. Fixture phải đi
qua form UI, không commit Vuex thay cho đăng nhập. Ghi rõ test mock và API thật.
Không coi test PASS là bằng chứng cho case chưa được tạo hoặc chưa assert.

Bạn đang dùng chung repo với người khác: đọc git status và giữ mọi thay đổi
ngoài phạm vi. Không deploy/merge vào main nếu chưa được ủy quyền. Bàn giao code,
ảnh desktop/mobile, review có exit code và các mục chưa kiểm. Nếu gặp thiếu
API test/credential, hoàn thành phần local độc lập rồi nêu đúng đầu vào còn thiếu.
```
