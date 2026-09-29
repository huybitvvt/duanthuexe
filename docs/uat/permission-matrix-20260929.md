# Phân quyền UAT theo bảng ngày 29/09/2026

Nguồn nghiệp vụ: `E:\duanthuexe\Thông tin phân quyền.xlsx`. File tài khoản UAT hiện có 14 tài khoản: 1 quản trị viên, 1 ban giám đốc, 1 kế toán, 1 nhân sự, 5 trưởng phòng giao dịch và 5 nhân viên quầy. Không lưu email hoặc mật khẩu vào tài liệu này.

Ma trận thực thi nằm trong `app/Support/UatPermissionMatrix.php`; migration `2026_09_29_000001_apply_uat_permission_matrix.php` thêm vai trò/quyền và thay thế các quyền cũ của 11 vai trò được liệt kê. Đã áp dụng vào Supabase UAT hiện tại bằng `scripts/apply-uat-permission-matrix.cjs`; bản ghi migration ở batch 48. Script đã lưu hai bản chụp trước khi ghi tại `backups/uat-permissions-before-2026-09-29T15-22-40-539Z.json` và `backups/uat-permissions-before-2026-09-29T15-22-59-931Z.json` (lần đầu rollback vì lỗi ép kiểu SQL; lần hai commit). Kiểm tra sau ghi xác nhận đúng 11 vai trò, đúng tập quyền từng vai trò và vẫn 14 tài khoản hoạt động. Migration không tạo hay chuyển tài khoản. Các tài khoản CS1–CS5 giữ `store_id` hiện tại; bảng Excel nêu tên Láng, Nguyễn Hoàng, Hàng Bút, Hà Đông, Giáp Bát nhưng chưa xác nhận ánh xạ từng tên sang mã CS.

| Vị trí | Vai trò | Quyền đã cấu hình |
| --- | --- | --- |
| Ban giám đốc, vận hành | `ban-giam-doc`, `van-hanh` | Toàn quyền nghiệp vụ |
| Trưởng phòng giao dịch, nhân viên quầy | `quan-ly-cua-hang`, `nhan-vien` | Xem xe mọi kho, dashboard/số đơn, nhắc nợ, xem sổ két và tài khoản ngân hàng cơ sở |
| Trưởng phòng thuê sở hữu | `thue-so-huu-truong-phong` | Các quyền xem của phòng giao dịch và xem hợp đồng thuê sở hữu |
| Nhân viên hợp đồng thuê sở hữu | `thue-so-huu-hop-dong` | Xem hợp đồng/xe kho sở hữu, dashboard, số đơn, sổ két và ngân hàng cơ sở |
| Nhân viên thu hồi nợ | `thue-so-huu-thu-hoi-no` | Xem hợp đồng/xe kho sở hữu, nhắc nợ và ngân hàng cơ sở |
| Telesale | `telesale` | Xem xe mọi kho |
| HCNS | `nhan-su` | Hồ sơ nhân sự, lịch trực, chấm công và danh sách xe |
| Kế toán | `ke-toan` | Kế toán, thu chi, tài khoản ngân hàng, quỹ tiền mặt và xem sổ két mọi cơ sở |

## Đề xuất kiểm soát chéo bổ sung ngày 29/09/2026

Mã ở checkout đã bổ sung migration `000002` (người gửi/người duyệt), `000003` (quyền thao tác), `2026_09_30_000001` (đề nghị phê duyệt) và `2026_09_30_000002` (số tiền giảm đã duyệt). **Các migration này chưa chạy trên Supabase UAT và mã chưa triển khai lên Render.** Quyền UAT đang chạy vẫn là ma trận chỉ xem ở phần trên. Không áp dụng `000003` đơn lẻ trước khi API mới được triển khai: bản API cũ còn gom nhiều thao tác dưới một quyền rộng.

| Vị trí | Đã lập trình trong checkout | Giới hạn còn lại |
| --- | --- | --- |
| Nhân viên quầy | Xem/tạo đơn trong cơ sở theo bảng `pricing`, sửa thông tin tiếp nhận nhưng giữ nguyên xe/thời gian/tiền của đơn đã phát hành; ghi nhận nhận xe và số km; gửi số kiểm đếm két; đề nghị hủy/giảm giá kèm lý do | Không xóa/hủy đơn trực tiếp, không nhập giá tùy chỉnh/giảm giá, không quyết toán hoặc tự nhập khoản hoàn tiền. Sau khi nhận xe, đơn chờ trưởng phòng quyết toán nên xe vẫn chưa sẵn sàng cho đơn mới. |
| Trưởng phòng giao dịch | Xem báo cáo doanh thu cơ sở; duyệt chốt két do người khác gửi; quyết toán đơn đã nhận xe; duyệt hủy/giảm giá đề nghị bởi người khác; quyết toán hoàn tiền hủy qua bước riêng | Giảm giá không có trần phần trăm nhưng không vượt tổng giá trị đơn và phải có lý do. API xóa cứng vẫn dành cho quản trị, không được dùng làm lệnh hủy. |
| Nhân viên hợp đồng thuê sở hữu | Lập nháp và lịch trả góp, tạo khách hàng kèm hồ sơ hợp đồng; thu cọc/kỳ đầu sau khi hợp đồng được duyệt; đề nghị đổi ngày kỳ trả | Không tự kích hoạt hợp đồng hoặc bàn giao xe. Quyền thu kỳ sau bị chặn ở service. Upload ảnh có route riêng; liên kết tài liệu thẩm định với hợp đồng chưa có. |
| Trưởng phòng thuê sở hữu | Duyệt nháp của người khác, lúc đó mới giữ xe và phát hành tài liệu; thu kỳ và duyệt chiết khấu lúc tất toán; duyệt đổi ngày kỳ trả, lệnh thu hồi và phương án thanh lý do người khác đề nghị; duyệt chốt két của phòng | Duyệt thu hồi/thanh lý chỉ ghi quyết định và nhật ký; không tự bàn giao, chuyển quyền sở hữu hoặc xóa nợ. Chưa có sổ lãi phạt để miễn/giảm khoản lãi phạt. |
| Nhân viên thu hồi nợ | Xem hợp đồng, ghi nhật ký nhắc nợ và cảnh báo; tra cứu giao dịch ngân hàng theo ngày tại cơ sở phụ trách; đề nghị thu hồi và thanh lý xe nợ xấu | Không sửa hợp đồng, tiền, nợ hoặc số dư. Chỉ được đề nghị thanh lý sau khi lệnh thu hồi đã được duyệt. |

Két có trạng thái `open → submitted → closed`. Người gửi không thể tự duyệt; sau khi gửi, bút toán thủ công bị khóa và lúc duyệt hệ thống so lại số liệu với bản chụp. Quản trị có quyền mở lại két. Hợp đồng thuê sở hữu do nhân viên tạo có trạng thái `draft`, chưa đổi trạng thái xe, chưa tạo tài liệu pháp lý; trưởng phòng khác người lập mới có thể duyệt.

Anh đã chọn bảng giá hiện có. API dùng bảng `pricing` theo loại/năm xe và số ngày, cùng quy tắc làm tròn giờ của giao diện; mọi giá/phí nhập riêng và tổng tiền lệch bảng bị từ chối cho nhân viên quầy. Ghi nhận nhận xe dùng quyền `order.return`; quyết toán có thu/hoàn dùng `order.settle_return` của trưởng phòng. Chưa có email/tên/cơ sở cho vận hành, các vai trò thuê sở hữu, telesale và người thứ hai trong ban giám đốc; theo chỉ đạo, không tạo tài khoản mới.

Hủy đơn đã duyệt chuyển sang `cancel_pending_settlement`. Hệ thống chốt số hoàn bằng tổng giao dịch thu và gia hạn đã duyệt trừ giao dịch chi đã duyệt; tiền chưa tự ra khỏi két. Trưởng phòng xác nhận đã hoàn qua tài khoản/két của phòng ở bước quyết toán riêng, hệ thống tạo đúng một phiếu chi và chuyển đơn sang `cancelled`. Đơn không được sửa hoặc nhận thêm SePay trong thời gian chờ quyết toán. Giảm giá đã duyệt trừ vào `orders.total`, lưu lũy kế `approved_discount_amount` và hiển thị trong quyết toán; hợp đồng đã chốt không được giảm qua luồng này.

Ánh xạ cơ sở trong mã: `CS1` Láng, `CS2` Nguyễn Hoàng, `CS3` Hàng Bút, `CS4` Giáp Bát, `CS5` Hà Đông, `CS6` kho thuê sở hữu.

Menu lọc theo `capabilities` trả từ API đăng nhập/xác thực. Quyền truy cập được kiểm tra ở API; menu chỉ giúp người dùng thấy đúng chức năng.
