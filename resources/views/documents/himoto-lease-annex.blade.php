<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Phụ lục hợp đồng {{ $variant }} - {{ $code }}</title>
    <style>
        @page { size: A4; margin: 16mm 16mm; }
        body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.22; color: #111; }
        h1 { font-size: 14pt; text-align: center; margin: 16px 0 4px; }
        p { margin: 0 0 7px; }
        .center { text-align: center; }
        .section { font-weight: bold; margin-top: 12px; }
        .indent { margin-left: 18px; }
        .signatures { display: flex; justify-content: space-between; text-align: center; margin-top: 20px; break-inside: avoid; }
        .signatures > div { width: 45%; }
        @media screen { body { max-width: 800px; margin: 30px auto; padding: 24px; box-shadow: 0 0 8px #bbb; } }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">In phụ lục</button></div>
<p class="center"><strong>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</strong><br>Độc lập – Tự do – Hạnh phúc</p>
<h1>PHỤ LỤC HỢP ĐỒNG BỔ SUNG – {{ $variant }}</h1>
<p class="center">(Kèm theo Hợp đồng số: {{ $code }} ngày {{ $signedOn ?: '…/…/……' }})</p>
<p><strong>BÊN A (Bên cho thuê): CÔNG TY CP THƯƠNG MẠI DỊCH VỤ HIMOTO VIỆT NAM</strong></p>
<p>MST: 0110863055 &nbsp; Đại diện theo pháp luật: Bà Nguyễn Thu Thủy &nbsp; Chức vụ: Giám đốc</p>
<p>Địa chỉ trụ sở chính: Số 31 dãy C1 tổ 28, Khu tập thể Đồng Bát, Bệnh Viện 198 Bộ Công An, Phường Từ Liêm, TP Hà Nội</p>
<p>Tại địa điểm kinh doanh: {{ $businessAddress ?: '………………………………' }} &nbsp; Số điện thoại liên hệ: {{ $businessPhone ?: '……………………' }}</p>
<p><strong>BÊN B (Bên thuê)</strong> Ông/Bà: {{ $customer['name'] ?: '……………………' }} &nbsp; Điện thoại: {{ $customer['phone'] ?: '……………………' }}</p>
<p>Địa chỉ: {{ $customer['address'] ?: '………………………………………………………………' }}</p>
<p>Căn cước/CCCD số: {{ $customer['id_card'] ?: '……………………' }} &nbsp; cấp ngày: {{ $customer['id_card_date'] ?: '…/…/……' }} &nbsp; tại: {{ $customer['id_card_place'] ?: '……………………' }}</p>
<p>Sau khi bàn bạc, hai bên thống nhất lập phụ lục Hợp đồng bổ sung (kèm theo hợp đồng) với các điều khoản như sau:</p>
<p class="section">Giá trị và thông tin tài sản mà Bên B đã thuê của Bên A:</p>
<p class="indent">Giá trị tài sản: {{ $assetValue ?: '……………………' }} VNĐ (Bằng chữ: {{ $assetValueWords ?: '…………………………………………' }})</p>
<p class="indent">Nhãn hiệu: {{ $vehicle['brand'] ?: '……………………' }} &nbsp; Loại xe: {{ $vehicle['name'] ?: '……………………' }} &nbsp; Biển kiểm soát: {{ $vehicle['license'] ?: '……………………' }}</p>
<p class="section">Mua lại tài sản thuê: Bên B mua lại tại thời điểm:</p>
@if ($months === 6)
<p class="indent">Sau 60 ngày đóng theo thuê sở hữu: giảm 10% giá trị tài sản.</p>
<p class="indent">Sau 90 ngày đóng theo thuê sở hữu: giảm 20% giá trị tài sản.</p>
<p class="indent">Sau 120 ngày đóng theo thuê sở hữu: giảm 30% giá trị tài sản.</p>
<p class="indent">Sau 150 ngày đóng theo thuê sở hữu: giảm 40% giá trị tài sản.</p>
<p class="indent">Bên B chịu toàn bộ chi phí sang tên xe sau 180 ngày đóng theo thuê sở hữu.</p>
<p>Để sở hữu xe, Bên B phải đạt đủ 180 ngày đóng HIỆU LỰC. Mỗi ngày Bên B đóng đúng hạn được +1 ngày hiệu lực. Mỗi ngày Bên B bỏ lỡ sẽ bị -1 ngày hiệu lực (trừ vào tổng đã có).</p>
@elseif ($months === 12)
<p class="indent">Sau 90 ngày đóng theo thuê sở hữu: giảm 10% giá trị tài sản.</p>
<p class="indent">Sau 150 ngày đóng theo thuê sở hữu: giảm 20% giá trị tài sản.</p>
<p class="indent">Sau 180 ngày đóng theo thuê sở hữu: giảm 30% giá trị tài sản.</p>
<p class="indent">Sau 270 ngày đóng theo thuê sở hữu: giảm 50% giá trị tài sản.</p>
<p class="indent">Bên B chịu toàn bộ chi phí sang tên xe sau 365 ngày đóng theo thuê sở hữu.</p>
<p>Với các ngày lẻ mà Bên B muốn tất toán hợp đồng sớm để sở hữu tài sản, Bên B mua lại với mức giảm giá ưu đãi của mốc trước đó (ví dụ: ngày thứ 130 áp dụng mốc 90 ngày).</p>
@else
<p class="indent">6 tháng hợp đồng thuê: giảm 10% giá trị tài sản.</p>
<p class="indent">9 tháng hợp đồng thuê: giảm 20% giá trị tài sản.</p>
<p class="indent">12 tháng hợp đồng thuê: giảm 30% giá trị tài sản.</p>
<p class="indent">18 tháng hợp đồng thuê: giảm 50% giá trị tài sản.</p>
<p class="indent">Bên B được sở hữu xe sau 24 tháng hợp đồng thuê.</p>
<p>Với các tháng lẻ mà Bên B muốn tất toán hợp đồng để sở hữu tài sản, Bên B mua lại với mức giảm giá ưu đãi của mốc trước đó (ví dụ: tháng thứ 8 áp dụng mốc 6 tháng).</p>
@endif
<p class="section">ĐIỀU 4: QUYỀN VÀ TRÁCH NHIỆM CỦA CÁC BÊN</p>
<p><strong>4.1. Quyền và trách nhiệm của Bên A:</strong></p>
<p class="indent">Bên B có trách nhiệm thanh toán đầy đủ và đúng hạn tiền thuê vào đầu mỗi kỳ thanh toán theo thỏa thuận tại Hợp đồng. Trường hợp Bên B chậm thanh toán, không thanh toán hoặc thanh toán không đầy đủ tiền thuê theo thời hạn đã thỏa thuận, vì bất kỳ lý do nào, thì được xác định là vi phạm nghĩa vụ thanh toán. Khi đó, Bên A có quyền thu hồi tài sản của mình về.</p>
<p class="indent">Sau khi bàn giao tài sản cho Bên B, Bên A không chấp nhận việc đổi tài sản khác thay thế tài sản trên để tiếp nối thực hiện hợp đồng này, trừ trường hợp thuê xe điện cần đổi sang xe xăng để di chuyển xa và đổi lại khi xong việc.</p>
<p class="indent">Số tiền cọc của Bên B được coi là số tiền đảm bảo việc thực hiện đúng cam kết và hợp đồng, Bên A sẽ không hoàn trả lại trong mọi trường hợp.</p>
<p><strong>4.2. Quyền và trách nhiệm của Bên B:</strong></p>
<p class="indent">Nhận tài sản thuê và có trách nhiệm bảo quản tài sản thuê trong suốt quá trình thuê. Trường hợp tài sản bị hư hỏng, Bên B có trách nhiệm sửa chữa, bồi thường (theo thực tế) cho Bên A, được miễn trừ các hao mòn tự nhiên.</p>
<p class="indent">Nếu trong thời hạn thuê xe, Bên B thay đổi địa chỉ hoặc thông tin liên hệ mà không kịp thời thông báo cho Bên A kể từ ngày Bên A biết có sự thay đổi thì khi phát sinh trường hợp mất liên lạc, chậm trễ việc thanh toán… Bên A có quyền thu hồi tài sản và thực hiện các biện pháp pháp lý để bảo vệ quyền lợi của Bên A.</p>
@if ($months === 6)<p class="indent">Trong quá trình tham gia, nếu vào bất kỳ ngày nào Bên B không thực hiện đóng tiền thuê theo quy định, tổng số ngày đã đóng hợp lệ sẽ tự động giảm đi 01 ngày. Việc trừ lùi được áp dụng ngay sau 00:00 ngày hôm đó. Bên B có thể đóng bù vào các ngày tiếp theo để khôi phục số ngày đã mất, nhưng lộ trình 180 ngày sẽ kéo dài tương ứng.</p>@endif
<p class="indent">Trường hợp Bên B trả trước thời hạn mà không mua xe thì Bên B phải chịu thêm chi phí khấu hao tài sản tương đương phần cọc ban đầu và cộng thêm phí phạt, bồi thường (nếu có).</p>
<p class="section">ĐIỀU 5. ĐIỀU KHOẢN CHUNG</p>
<p>Sau khi Bên B đáp ứng đầy đủ các điều kiện để thực hiện mua lại tài sản thuê theo thỏa thuận, hai bên có trách nhiệm phối hợp ký kết Hợp đồng mua bán xe theo quy định của pháp luật.</p>
<p>Hai bên cam kết thi hành đúng các điều khoản của phụ lục hợp đồng bổ sung, không bên nào tự ý đơn phương sửa đổi, đình chỉ hoặc hủy bỏ phụ lục bổ sung này.</p>
<p>Phụ lục Hợp đồng bổ sung này có hiệu lực kể từ ngày ký và được thanh lý sau khi hai bên thực hiện xong nghĩa vụ của mình và không có khiếu nại nào. Hợp đồng được lập thành 02 (hai) bản có giá trị pháp lý như nhau, Bên A giữ 01 bản, Bên B giữ 01 bản. Các bên đã đọc lại toàn bộ nội dung phụ lục Hợp đồng bổ sung này, đã hiểu và đồng ý với toàn bộ nội dung ghi trong Hợp đồng và ký xác nhận dưới đây.</p>
<div class="signatures"><div><strong>ĐẠI DIỆN BÊN A</strong><br><i>(Ký, đóng dấu, ghi rõ họ tên)</i></div><div><strong>BÊN B</strong><br><i>(Ký, ghi rõ họ tên)</i></div></div>
</body>
</html>
