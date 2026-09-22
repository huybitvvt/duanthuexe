<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Biên bản bàn giao xe - {{ $code }}</title>
    <style>
        @page { size: A4; margin: 18mm 17mm; }
        body { font-family: "Times New Roman", serif; font-size: 13pt; color: #111; line-height: 1.35; }
        h1, h2, h3, p { margin: 0 0 8px; }
        h1 { text-align: center; font-size: 16pt; margin-top: 22px; }
        .center { text-align: center; }
        .underline { border-bottom: 1px solid #111; width: 170px; margin: 0 auto 18px; }
        table { border-collapse: collapse; width: 100%; margin: 12px 0; }
        th, td { border: 1px solid #111; padding: 6px; text-align: left; }
        th { font-size: 11pt; }
        .signatures { display: flex; justify-content: space-between; margin-top: 24px; text-align: center; }
        .signatures > div { width: 46%; }
        .signatures small { font-style: italic; }
        .draft { border: 2px solid #b00; color: #b00; padding: 6px; text-align: center; }
        @media screen { body { max-width: 800px; margin: 30px auto; padding: 24px; box-shadow: 0 0 8px #bbb; } }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">In biên bản</button></div>
<p class="center"><strong>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</strong><br>Độc lập – Tự do – Hạnh phúc</p>
<div class="underline"></div>
<h1>BIÊN BẢN BÀN GIAO XE</h1>
<p class="center">Số: {{ $code }}/BBGX</p>
@if ($isDraft)<p class="draft">BẢN NHÁP – CHƯA PHÁT HÀNH HỢP ĐỒNG</p>@endif
<p>Căn cứ vào Hợp đồng số: <strong>{{ $code }}</strong> / ký ngày {{ $signedOn ?: '…/…/……' }}. Chúng tôi gồm:</p>
<p><strong>BÊN A (Bên cho thuê): CÔNG TY CỔ PHẦN THƯƠNG MẠI DỊCH VỤ HIMOTO VIỆT NAM</strong></p>
<p>Địa chỉ: Số nhà 31, dãy C1 Tổ 28 Khu tập thể Đồng Bát, Bệnh viện 198 Bộ Công An, Phường Từ Liêm, Thành phố Hà Nội, Việt Nam.</p>
<p>MST: 0110863055 &nbsp;&nbsp; Điện thoại: 0886184116</p>
<p>Đại diện: Bà Nguyễn Thu Thủy &nbsp;&nbsp; Chức vụ: Giám đốc</p>
<p><strong>BÊN B (Bên thuê):</strong> {{ $customer['name'] ?: '…………………………………………' }}</p>
<p>Địa chỉ: {{ $customer['address'] ?: '…………………………………………' }}</p>
<p>Số CCCD: {{ $customer['id_card'] ?: '……………………' }} &nbsp; Cấp ngày: {{ $customer['id_card_date'] ?: '…/…/……' }} &nbsp; Tại: {{ $customer['id_card_place'] ?: '……………………' }}</p>
<p>Sau khi bàn bạc, thỏa thuận, hai bên thống nhất ký biên bản bàn giao xe với nội dung sau:</p>
<p>Ngày giao nhận xe: <strong>{{ $handoverOn ?: '…/…/……' }}</strong></p>
<p>Hai bên cùng kiểm tra, xác nhận và bàn giao cho Bên thuê xe sau:</p>
<table>
    <thead><tr><th>HÃNG XE</th><th>LOẠI XE</th><th>BIỂN KIỂM SOÁT</th><th>SỐ MÁY</th><th>SỐ KHUNG</th></tr></thead>
    <tbody>
    @foreach ($vehicles as $vehicle)
        <tr><td>{{ $vehicle['brand'] ?: '……………………' }}</td><td>{{ $vehicle['name'] ?: '……………………' }}</td><td>{{ $vehicle['license'] ?: '……………………' }}</td><td>{{ $vehicle['engine'] ?: '……………………' }}</td><td>{{ $vehicle['chassis'] ?: '……………………' }}</td></tr>
    @endforeach
    @if (empty($vehicles))<tr><td colspan="5">Chưa có thông tin xe giao nhận.</td></tr>@endif
    </tbody>
</table>
<p><strong>Hồ sơ kèm theo:</strong> Phiếu bảo hành; Itag xe.</p>
<p>Đại diện bên cho thuê đã giải thích kỹ những nội dung trên. Đại diện bên nhận đã nghe và xác nhận những nội dung trên.</p>
<p>Biên bản này được lập thành hai (02) bản có giá trị như nhau, mỗi bên giữ một (01) bản.</p>
<p style="text-align: right; margin-top: 18px;">Hà Nội, ngày …… tháng …… năm ……</p>
<div class="signatures"><div><strong>ĐẠI DIỆN BÊN A</strong><br><small>(Ký, đóng dấu và ghi rõ họ tên)</small></div><div><strong>ĐẠI DIỆN BÊN B</strong><br><small>(Ký và ghi rõ họ tên)</small></div></div>
</body>
</html>
