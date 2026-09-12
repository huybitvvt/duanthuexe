# Devop
Một số điều cần lưu ý
- Sau khi git push, phải clear cache của VPS và cloudfare, nếu không thì phần front-end của app sẽ bị lỗi
## Install local

Step 1: Change database config in .env + create database.

Step 2: Import .sql demo data

Step 3: Run project `php artisan serve` and access server via: http://127.0.0.1:8000

Admin user: `dung@gmail.com / Bfc@123123`

## Dev server

Access dev server: https://happyride.merchbridge.com, with admin account: `dung@gmail.com / Bfc@123123`


<p align="center"><img src="https://res.cloudinary.com/dtfbvvkyp/image/upload/v1566331377/laravel-logolockup-cmyk-red.svg" width="400"></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/d/total.svg" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://poser.pugx.org/laravel/framework/license.svg" alt="License"></a>
</p>



 
# Architecture
Một số điều cần lưu ý:
- Front-end
    - Source code Vue.js ở thư mục resource/
    - Built code được lưu ở thư mục public/. Build bằng `npm run dev` hoặc `npm run prod`, khi dev nếu muốn auto built thì sử dụng `npm run watch`
- Backend:
    - Sử dụng Repository nhưng tùy module, có module dùng, có module không


# Timezone

- setting app Laravel ghi là sử dụng giờ UTC, tuy nhiên thực tế thì lưu giờ Việt ở cả front-end và database
    
    ```jsx
       'timezone' => 'UTC',
    ```
    

# Một số terms

- Bảng order_vehicle_details trong database
    - total_money: tiền thuê xe ban đầu (k tính tiền thuê xe quá hạn + phí phạt quá hạn + các phí khác)
    - handler_price (Giá Khác): là phí do quản trị viên nhập vào, dùng trong vài trường hợp đặc biệt thay thế cho đơn giá gốc và cách tính quá hạn thông thường. Xem cụ thể ở mục Giá Khác
    - money_out_date: tiền phí thuê xe quá hạn. Xem cụ thể ở mục Giá Khác
- Bảng orders
    - pid: tổng tiền đã thu (cọc + gia hạn)
    - paid: ko dùng cột này
    - out_dated_at: bao lâu nữa thì hết hạn thuê (chứ k phải là quá hạn lúc nào)
    - total: là tổng phí thuê , tính ra từ phí thuê ban đầu, giá khác, phí thuê quá hạn… xem cụ thể ở mục Giá Khác
    

# “Giá khác”

## Ver cũ

- Giá Khác là giá cuối cùng, không cộng thêm gì khác. Chỉ lấy giá đó để thu của khách.
    - Là để phục vụ cho trường hợp mình muốn tạo ra 1 giá khác với đơn giá chính thức, có thể là ưu đãi cho khách, hoặc là khách đem trả xe quá hạn nhưng có lý do chính đáng nên không muốn khách phải chịu tiền phạt, thì nhập vào Giá Khác để chỉ thu khoản Giá Khác đó thôi
    - Ngày cuối tuần tăng giá hơn một chút so với ngày thường, thì cũng nhập Giá Khác vào
- Nếu khách hàng có nộp thêm tiền gia hạn, thì code sẽ cộng số tiền gia hạn đó vào Tạm tính
- Vì vậy nên phát sinh nhiều sai số. Bởi vì nhân viên thường nhầm lẫn các khái niệm sau:
    - Trường hợp cuối tuần, ngày lễ, tăng lên 10k/ngày, thì phải nhân đơn giá mới với số ngày thuê, lấy kết quả rồi mới nhập kết quả đó vào mục Giá Khác.
    - Trường hợp có gia hạn: phải làm sao để mà tính được tiền gia hạn nên bằng bao nhiêu
        - Kết quả đúng là = Giá Khác / số ngày thuê * số ngày gia hạn, để ra kết quả là Giá Khác mới. Rồi nhập vào ô Giá Khác
        - Kết quả sai là khi mà dựa vào cái tính toán giá thêm giờ dựa trên đơn giá cũ để tính ra tiền gia hạn, rồi bắt khách nộp cái khoản đó.

## Ver mới

Đã tách Giá Khác ra làm Đơn Giá Khác và Giá Tổng khác để khắc phục tình trạng trên

# Tiền lãi thuê tính như thế nào

## Version cũ

Ở ver cũ, trước khi được update bởi OneMerce

- Lãi thuê = Đặt cọc - Trả cọc
- Đặt cọc = Tổng in. Tổng in được tính bằng việc cộng các transaction có type là in
- Trả cọc = Tổng out

## Version mới

- Gom ở bảng order_vehicle_details xem cửa hàng này (store_id) có những order  nào
    - ngày nào tạo hợp đồng (rent_at)
        - Lãi Thuê = Tiền giá thuê xe (total_money ở order_vehicle_detail) hoặc handler_price
    - ngày đóng hợp đồng (completed_at)
        - Nếu trả đúng ngày hẹn, thì ko ghi nhận gì
        - Nếu trả muộn: ghi nhận 1 khoản Lãi Thuê = Phí Quá Giờ  (money_out_date)
        - Nếu trả sớm: thì Ghi nhận 1 khoản Lãi Thuê âm = số ngày trả sớm    (vẫn là money_out_date nhưng mà âm)

- Gom ở bảng transactions: ngày phát sinh thì lấy ở ngày create_at của transaction. bảng transaction có cái type addon nào thì lọc ra những cái có ngày create_at là ngày hôm nay, và sum cái value lại
    - tiền cọc  : in
    - tiền nộp gia hạn   : addon
    - tiền out: out
        
        
- Tổng tiền thu thuê xe = lãi thuê  + tiền gia hạn
- Lưu ý
    - tiền lãi thuê xe tính vào ngày bắt đầu thuê xe chứ k phải ngày tạo hợp đồng
    - tiền quá giờ tính vào ngày đóng hợp đồng

# Về việc tiền cọc

- Có một đơn hàng không cọc bằng tiền, mà cọc bằng vật phẩm ví dụ cọc bằng xe. Nên tiền cọc bằng 0

# Khi khách không trả lại xe

- Quản trị viên tick vào ô Nợ Xấu ở đơn hàng
    - Khi tick vào ô Nợ Xấu, vô hiệu hóa button Hoàn Thành, bởi vì một đơn hàng bị nợ xấu tức là chưa thanh toán, thì không thể hoàn thành được.
    - Và bởi vì khách không trả xe thì sẽ không có giá trị thời điểm trả (return_at), không có giá trị này thì không có cơ sở để tính toán ra giá thuê và các số liệu khác. Dễ gây nhầm lẫn và sai số.
    - Ngược lại, khi đơn hàng đã Hoàn Thành thì không cho tick vào ô Nợ Xấu.
- Phát sinh 1 transaction với giá trị bằng 0 để dễ theo dõi

# Bảo dưỡng

### Thông tin

- Có vô số các loại bảo dưỡng:  thay dầu, bảo dưỡng toàn bộ, bảo dưỡng má phanh, săm lốp…
- Các loại “hạn bảo dưỡng”:
    - (1) hạn bảo dưỡng cho tất cả xe
        - Chung tất cả các xe thì 1 tháng kiểm tra dầu 1 lần, 6 tháng bảo dưỡng toàn bộ xe
    - (2) hạn bảo dưỡng theo nhóm xe: nếu có thì đè lên cái (1)
        - chia nhóm: theo công tơ mét. cứ 1000km thì bảo dưỡng 1 lần
    - hạn bảo dưỡng theo con xe: nếu có thì đè lên cả (1) và (2)
        - riêng từng xe: cái này tự quy định

### Code hiện tại

- tần suất bảo dưỡng:
    - lấy ở trong bảng maintenance_vehicle (tần suất cá nhân) trước r nếu k có mới dùng đến tần suất chung ở bảng maintenance_rule
- lịch hẹn:
    - xem trong maintenance_schedules
    - ưu tiên next_time_manual
    - next_time_auto là tính bằng cron: lấy lần bảo dưỡng gần nhất ở bảng maintenance_log r cộng thêm tần suất

- nhấn riêng vào xem từng vehicle thì sẽ xem được :
    - lịch sử bảo dưỡng
    - lịch hẹn bảo dưỡng

- tạo cron để gán vào bảng maintenance_schedule
    - tính tần suất
    - gán next_time_auto nếu có log. k có log thì next time auto là thời điểm hiện tại


# Setup server cronjob

Để app chạy cronjob cần setup lệnh sau trên server: `* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1`
