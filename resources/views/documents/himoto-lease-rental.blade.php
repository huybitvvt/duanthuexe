<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Hợp đồng thuê xe - {{ $code }}</title>
    <style>
        @page { size: A4; margin: 14mm 14mm; }
        body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.28; color: #111; }
        h1 { font-size: 16pt; text-align: center; margin: 8px 0; }
        p { margin: 0 0 6px; }
        .center { text-align: center; }
        .signatures { display: flex; justify-content: space-between; text-align: center; margin-top: 18px; }
        .signatures > div { width: 45%; }
        @media screen { body { max-width: 800px; margin: 24px auto; padding: 24px; box-shadow: 0 0 8px #bbb; } }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">In hợp đồng</button></div>
<p class="center"><strong>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</strong><br>Độc lập – Tự do – Hạnh phúc</p>
<h1>HỢP ĐỒNG THUÊ XE</h1>
<p class="center">Số: <strong>{{ $code }}</strong>/HĐTX</p>
<p>Căn cứ Bộ luật Dân sự và nhu cầu của các bên. Hợp đồng được lập ngày {{ $signedOn ?: '…/…/……' }}.</p>
<p><strong>BÊN A (Bên cho thuê): CÔNG TY CỔ PHẦN THƯƠNG MẠI DỊCH VỤ HIMOTO VIỆT NAM</strong></p>
<p>MST: 0110863055. Địa điểm kinh doanh: {{ $businessAddress ?: '……………………' }}. Điện thoại: {{ $businessPhone ?: '0886184116' }}.</p>
<p>Đại diện: Bà Nguyễn Thu Thủy. Chức vụ: Giám đốc.</p>
<p><strong>BÊN B (Bên thuê):</strong> {{ $customer['name'] ?: '……………………' }} &nbsp; Điện thoại: {{ $customer['phone'] ?: '……………………' }}</p>
<p>Địa chỉ: {{ $customer['address'] ?: '……………………' }}</p>
<p>Số CCCD: {{ $customer['id_card'] ?: '……………………' }} &nbsp; Cấp ngày: {{ $customer['id_card_date'] ?: '…/…/……' }} &nbsp; Tại: {{ $customer['id_card_place'] ?: '……………………' }}</p>
<p>Người thân: {{ $relativesText ?: '……………………' }}</p>
<p>Người giám hộ: {{ $guardian['name'] ?: '……………………' }} &nbsp; CCCD: {{ $guardian['id_card'] ?: '……………………' }} &nbsp; Điện thoại: {{ $guardian['phone'] ?: '……………………' }}</p>
<p><strong>ĐIỀU 1. XE THUÊ</strong></p>
<p>Nhãn hiệu: {{ $vehicleBrand ?: ($vehicle['brand'] ?: '……………………') }} &nbsp; Loại xe: {{ $vehicle['name'] ?: '……………………' }} &nbsp; Màu: {{ $vehicleColor ?: '……………………' }} &nbsp; Năm SX: {{ $vehicleYear ?: '……………………' }}</p>
<p>Biển số: {{ $vehicle['license'] ?: '……………………' }} &nbsp; Số máy: {{ $vehicle['engine'] ?: '……………………' }} &nbsp; Số khung: {{ $vehicle['chassis'] ?: '……………………' }}</p>
<p>Người lái: {{ ($driver['driver_name'] ?? '') ?: ($customer['name'] ?: '……………………') }} &nbsp; GPLX: {{ $driver['driver_license_number'] ?? '……………………' }} &nbsp; Cấp ngày: {{ !empty($driver['driver_license_issued_on']) ? $driver['driver_license_issued_on'] : '…/…/……' }}</p>
<p><strong>ĐIỀU 2. THỜI HẠN VÀ THANH TOÁN</strong></p>
<p>Thời hạn thuê sở hữu: {{ $months }} tháng, từ {{ $signedOn ?: '…/…/……' }} đến {{ $endOn ?: '…/…/……' }}.</p>
<p>Kỳ thanh toán: theo {{ $billingLabel }}. Số tiền mỗi kỳ: {{ $periodAmount }} VNĐ.</p>
<p>Tiền trả trước: {{ $prepaidAmount }} VNĐ. Tiền đặt cọc: {{ $depositAmount }} VNĐ. Hai khoản này được ghi nhận riêng và không thay thế cho nhau.</p>
<p>Giá trị tài sản: {{ $assetValue ?: '……………………' }} VNĐ ({{ $assetValueWords ?: '……………………' }}).</p>
<p><strong>ĐIỀU 3.</strong> Quyền và nghĩa vụ chi tiết nằm ở phụ lục kèm theo, là một phần không tách rời của hợp đồng này.</p>
<p><strong>ĐIỀU 4.</strong> Bên A giao xe và hồ sơ. Bên B giữ xe, thanh toán đúng kỳ và không giao xe cho người khác khi chưa có thỏa thuận.</p>
<p><strong>ĐIỀU 5.</strong> Hợp đồng có hiệu lực từ ngày ký, lập thành 02 bản có giá trị như nhau. Biên bản bàn giao xe được lập cùng bộ hồ sơ này.</p>
<div class="signatures">
    <div><strong>ĐẠI DIỆN BÊN A</strong><br><i>(Ký, đóng dấu, ghi rõ họ tên)</i></div>
    <div><strong>BÊN B / NGƯỜI GIÁM HỘ</strong><br><i>(Ký, ghi rõ họ tên)</i></div>
</div>
</body>
</html>
