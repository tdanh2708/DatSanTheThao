# Sports Court Booking

## Website quản lý và đặt sân thể thao trực tuyến

Đồ án website PHP thuần kết nối MySQL/MariaDB, chạy cục bộ trên XAMPP/localhost.

## Giới thiệu

Sports Court Booking hỗ trợ người dùng tìm sân thể thao tại TP. Hồ Chí Minh, xem thông tin và lịch trống, đặt một hoặc nhiều khung giờ, theo dõi đơn và trao đổi với Admin. Hệ thống có hai vai trò chính: **User** và **Admin**.

Dự án phục vụ học tập và trình diễn trên localhost. Lịch, giá và một số dữ liệu sân là dữ liệu mô phỏng hoặc tham khảo; website chưa kết nối với lịch vận hành thực tế của các địa điểm.

## Công nghệ

- PHP thuần 8.2+, PDO và prepared statements.
- MySQL/MariaDB; cấu hình mặc định dùng `utf8mb4`.
- HTML5, CSS3 và JavaScript thuần.
- Apache, XAMPP và phpMyAdmin.
- Bootstrap 5.3.3 được tải từ jsDelivr CDN cho một số giao diện; cần Internet để tải Bootstrap.
- Dự án không dùng Laravel, Node.js, Composer hoặc framework backend khác.

## Chức năng người dùng

- Đăng ký; sau khi đăng ký thành công, người dùng được đăng nhập và chuyển về trang chủ. Đăng nhập và đăng xuất.
- Xem và cập nhật hồ sơ cá nhân.
- Duyệt danh sách môn thể thao và sân; tìm kiếm, lọc sân theo các tiêu chí trên trang danh sách.
- Xem chi tiết sân, giá tham khảo, sức chứa nếu đã xác minh, ngày chơi và khung giờ còn chỗ.
- Chọn nhiều khung giờ để đặt. Server kiểm tra lịch, sức chứa và tính tiền từ dữ liệu sân; thao tác tạo đơn dùng transaction.
- Xem lịch sử, chi tiết và trạng thái booking/payment; hủy đơn khi trạng thái và thời gian cho phép.
- Chọn chuyển khoản ngân hàng hoặc thanh toán tại sân; xem QR VietQR được tạo theo đơn.
- Báo đã chuyển khoản để chờ Admin kiểm tra; payment chỉ chuyển sang `paid` sau khi Admin xác nhận.
- Gửi đánh giá sao và nội dung sau khi booking đủ điều kiện; mỗi booking được đánh giá một lần.
- Chat hỗ trợ với Admin bằng tin nhắn văn bản hoặc một ảnh JPEG/PNG/WEBP tối đa 5 MB.

## Chức năng quản trị

Khu vực Admin kiểm tra quyền ở server-side và gồm:

- Dashboard với số liệu hoạt động, doanh thu đã thanh toán, sân được đặt nhiều và booking gần đây.
- Quản lý sân và môn thể thao.
- Quản lý booking: tìm kiếm/lọc, xem chi tiết, theo dõi trạng thái và hủy đơn phù hợp.
- Quản lý thanh toán: lọc payment, xác nhận đã nhận chuyển khoản hoặc tiền thanh toán tại sân.
- Quản lý người dùng và khóa/mở tài khoản.
- Xem đánh giá, quản lý tin nhắn hỗ trợ, thông báo, nhật ký hoạt động và khung giờ.
- Chat Center cho phép Admin phản hồi, kết thúc cuộc trò chuyện và dọn tin nhắn.

Trang Admin: `http://localhost/DatSanTheThao/admin/`.

## Thanh toán

### Chuyển khoản ngân hàng

1. Khi đặt sân, hệ thống tạo payment đang chờ và lưu số tiền theo booking.
2. Trang chi tiết đơn hiển thị QR VietQR tạo theo số tiền lấy từ database và mã đơn `BKxxxxxx`.
3. Người dùng báo đã chuyển khoản. Booking được xác nhận, payment vẫn chờ Admin; hệ thống lưu thời điểm người dùng báo chuyển khoản.
4. Admin đối chiếu giao dịch thực tế rồi xác nhận. Khi đó payment mới chuyển sang `paid`.
5. Đơn chuyển khoản đã được báo nhưng chưa được Admin xác nhận có thể tự hết hạn sau 5 phút. Việc kiểm tra diễn ra khi trang liên quan được truy cập, không cần tiến trình nền.

### Thanh toán tại sân

Người dùng hoàn tất đặt đơn, booking được xác nhận và payment ở trạng thái chờ thu tiền. Admin đánh dấu đã thanh toán sau khi khách thực sự trả tại sân. Quy tắc hết hạn 5 phút không áp dụng cho phương thức này.

**Giới hạn:** VietQR được tạo theo dữ liệu đơn; dự án không tích hợp API ngân hàng, webhook, đọc biến động số dư hoặc xác nhận tiền tự động. Người dùng báo đã chuyển không đồng nghĩa với đã thanh toán. Admin phải kiểm tra và xác nhận thủ công. Thông tin nhận tiền được cấu hình tại `config/payment.php`; không lưu số tài khoản trong README.

## Quy trình đặt sân

1. Chọn môn thể thao và sân.
2. Chọn ngày chơi, sân con và một hoặc nhiều khung giờ còn trống.
3. Đăng nhập hoặc đăng ký nếu chưa có tài khoản.
4. Chọn chuyển khoản ngân hàng hoặc thanh toán tại sân.
5. Server kiểm tra lại lịch và tính tổng tiền trước khi tạo booking.
6. Theo dõi trạng thái tại **Lịch sử đặt sân**; Admin xử lý thanh toán theo phương thức đã chọn.

Các khung giờ mẫu trong database là 06:00–22:00; đây không phải cam kết giờ hoạt động của từng địa điểm.

## Chat hỗ trợ

User có thể gửi tin nhắn văn bản hoặc một ảnh JPEG/PNG/WEBP không quá 5 MB trong cuộc trò chuyện với Admin. Giao diện cập nhật tin nhắn định kỳ bằng polling. Ảnh chat được lưu trong `uploads/chat/`.

## Cơ sở dữ liệu

Cấu hình kết nối mẫu nằm trong `config/database.example.php`. Sao chép thành `config/database.php` rồi sửa theo MySQL trên máy đang chạy:

| Thông số | Giá trị mặc định |
| --- | --- |
| Host | `127.0.0.1` |
| Database | `dat_san_the_thao` |
| Username | `root` |
| Password | Để trống |
| Charset | `utf8mb4` |

`database.sql` tạo database nếu chưa có, tạo schema hiện tại và nạp dữ liệu môn thể thao cùng khung giờ mẫu. File này không tạo sẵn sân, tài khoản, Admin hoặc booking demo.

Các bảng chính:

| Bảng | Chức năng |
| --- | --- |
| `users` | Tài khoản, vai trò và hồ sơ người dùng |
| `sports` | Danh sách môn thể thao |
| `courts` | Thông tin sân, giá và sức chứa |
| `time_slots` | Khung giờ và trạng thái hoạt động |
| `bookings` | Đơn đặt sân, mã đơn và trạng thái booking |
| `booking_details` | Sân con/khung giờ thuộc booking |
| `payments` | Phương thức, số tiền và trạng thái thanh toán |
| `reviews` | Đánh giá của người dùng |
| `conversations` | Cuộc trò chuyện User–Admin |
| `messages` | Tin nhắn và tệp đính kèm |
| `notifications` | Thông báo trong hệ thống |
| `admin_logs` | Nhật ký thao tác quản trị |

## Yêu cầu hệ thống

- Windows và XAMPP.
- Apache và MySQL/MariaDB chạy trong XAMPP Control Panel.
- PHP 8.2 trở lên, phpMyAdmin và trình duyệt hiện đại.
- Kết nối Internet nếu cần tải Bootstrap từ CDN hoặc hiển thị QR VietQR.

## Cài đặt và chạy dự án

### 1. Đặt source code

Đặt thư mục dự án tại:

```text
C:\xampp\htdocs\DatSanTheThao
```

### 2. Khởi động XAMPP

Mở XAMPP Control Panel và Start **Apache** cùng **MySQL**.

### 3. Tạo database và import schema

1. Mở `http://localhost/phpmyadmin/`.
2. Nếu cần, tạo database `dat_san_the_thao` với collation `utf8mb4_unicode_ci`.
3. Chọn database vừa tạo, mở tab **Import**, chọn `C:\xampp\htdocs\DatSanTheThao\database.sql`, rồi chọn **Go**. Script SQL cũng có lệnh tạo/chọn database.

### 4. Tạo cấu hình cục bộ

Sao chép `config/database.example.php` thành `config/database.php` và cập nhật thông tin kết nối nếu cần. Sao chép `config/payment.example.php` thành `config/payment.php`, sau đó nhập thông tin tài khoản nhận tiền của môi trường demo. Các file cấu hình thực tế được Git bỏ qua để không đưa thông tin riêng lên kho mã nguồn.

### 5. Nạp dữ liệu sân và tài khoản

Sau khi import schema mới, các script CLI dưới đây có thể được dùng để thêm dữ liệu. Xem mục [Script hỗ trợ](#-script-hỗ-trợ) trước khi chạy; không chạy tất cả một cách máy móc.

Mở Command Prompt tại thư mục dự án và dùng PHP đi kèm XAMPP, ví dụ:

```bat
C:\xampp\php\php.exe create_demo_admin.php
C:\xampp\php\php.exe seed_verified_courts.php
C:\xampp\php\php.exe setup_demo_extensions.php
C:\xampp\php\php.exe seed_demo_data.php
```

Các script có điều kiện và một số thao tác thêm/cập nhật dữ liệu. Với database đang dùng, hãy sao lưu trước khi chạy seed/setup.

### 6. Mở website

- Website: `http://localhost/DatSanTheThao/`
- Admin: `http://localhost/DatSanTheThao/admin/`
- phpMyAdmin: `http://localhost/phpmyadmin/`

## Tài khoản demo

Thông tin dưới đây đã được kiểm tra với tài khoản demo hiện có trong database project và logic tạo tài khoản demo. Trên database mới, cần chạy script tạo Admin/seed user tương ứng.

### Quản trị viên

| Thông tin | Giá trị |
| --- | --- |
| Email | `admin.demo@example.test` |
| Mật khẩu | `AdminCourt2026!` |
| Vai trò | Admin |

`create_demo_admin.php` chỉ tạo tài khoản này khi database chưa có Admin. Nếu đã tồn tại Admin khác, script không đặt lại mật khẩu. `setup_admin.php` là trang thiết lập Admin một lần; mật khẩu mới cần tối thiểu 12 ký tự. Xóa file setup sau khi sử dụng.

### Người dùng demo

Các tài khoản dưới đây dùng chung mật khẩu `DemoCourt2026!` trong dữ liệu demo hiện có:

| Email | Mật khẩu |
| --- | --- |
| `demo.minhanh@example.test` | `DemoCourt2026!` |
| `demo.hoangnam@example.test` | `DemoCourt2026!` |
| `demo.minhkhang@example.test` | `DemoCourt2026!` |
| `demo.giahuy@example.test` | `DemoCourt2026!` |
| `demo.thanhhuong@example.test` | `DemoCourt2026!` |
| `demo.ngocmai@example.test` | `DemoCourt2026!` |
| `demo.quocbao@example.test` | `DemoCourt2026!` |
| `demo.thaovy@example.test` | `DemoCourt2026!` |
| `demo.tuankiet@example.test` | `DemoCourt2026!` |
| `demo.khanhlinh@example.test` | `DemoCourt2026!` |

`seed_demo_data.php` chỉ tạo tài khoản còn thiếu và không đặt lại mật khẩu của email đã tồn tại.

## Script hỗ trợ

Chạy script PHP CLI từ thư mục gốc bằng `C:\xampp\php\php.exe <tên-file.php>`.

| Script | Mục đích và lưu ý |
| --- | --- |
| `create_demo_admin.php` | Tạo Admin demo khi chưa có Admin; không đặt lại mật khẩu nếu Admin đã tồn tại. |
| `setup_admin.php` | Trang web thiết lập Admin đầu tiên; dùng một lần rồi xóa file. Mật khẩu tối thiểu 12 ký tự. |
| `seed_verified_courts.php` | Thêm/cập nhật tập sân theo nguồn tham khảo; có thể cập nhật giá ước tính, ghi chú nguồn và sức chứa chưa có. Chạy bằng CLI. |
| `setup_demo_extensions.php` | Bổ sung sân Futsal/Bóng bàn và hỗ trợ schema cũ; có điều chỉnh dữ liệu sân demo trong một trường hợp cụ thể. Chạy bằng CLI. |
| `seed_demo_data.php` | Tạo user demo còn thiếu, booking và review mẫu; cần đủ sân hoạt động có giá. Có thể điều chỉnh trạng thái một số khung giờ mẫu ngoài giờ sử dụng. Chạy bằng CLI. |
| `database/migrate_support.php` | Hỗ trợ schema cũ cho các bảng chat/notification/payment; không cần chạy sau khi import `database.sql` hiện tại. |
| `migrate_court_capacity.php` | Migration cho schema cũ về sức chứa/khung giờ; có thay đổi cột, index và dữ liệu khung giờ. Chỉ dùng sau khi kiểm tra schema và sao lưu. |

Không chạy các migration legacy theo hướng dẫn chung nếu database đã dùng schema hiện tại.

## Cấu trúc thư mục

```text
DatSanTheThao/
├── admin/             # Trang và thao tác quản trị
├── assets/            # CSS, JavaScript và hình ảnh
├── auth/              # Đăng nhập, đăng ký, đăng xuất
├── chat/              # Endpoint và trang chat
├── config/            # Kết nối database và cấu hình thanh toán
├── database/          # Hỗ trợ/migration database cũ
├── includes/          # Layout, helper và dịch vụ dùng chung
├── uploads/           # Tệp người dùng tải lên, gồm ảnh chat
├── user/              # Hồ sơ, booking, payment và review của User
├── booking_process.php
├── booking_cancel.php
├── booking_success.php
├── court_detail.php
├── courts.php
├── sports.php
├── index.php
├── database.sql
└── README.md
```

Ảnh giao diện nằm trong `assets/images/`; CSS và JavaScript dùng chung nằm trong `assets/css/` và `assets/js/`.

## Dữ liệu demo

- `database.sql` khởi tạo môn thể thao và khung giờ mẫu.
- `seed_verified_courts.php` bổ sung các sân tham khảo.
- `setup_demo_extensions.php` bổ sung dữ liệu Futsal và Bóng bàn.
- `seed_demo_data.php` tạo user demo cùng booking/review mẫu khi đủ điều kiện.
- Dữ liệu booking mẫu phục vụ trình diễn; không đại diện lịch đặt thực tế của cơ sở sân.

## Nguồn dữ liệu

Thông tin sân và mức giá được tổng hợp từ trang của đơn vị vận hành, danh bạ/cộng đồng thể thao và nguồn công khai. Các mức giá trong dữ liệu là giá tham khảo/ước tính, không phải báo giá được xác nhận với từng địa điểm. Số sân chỉ được ghi khi có cơ sở xác minh; thông tin không xác minh được để trống thay vì tự tạo.

Một số nguồn đã tham khảo:

- [TBG Arena](https://tbgarena.com/), [Fox Football Vietnam](https://foxfootballvietnam.com/vi/), [Thể thao Phong Sơn](https://thethaophongson.vn/).
- [LOOL](https://lool.vn/), [ShopVNB](https://shopvnb.com/san-cau-long-the-b-ung-van-khiem.html), [Vmito](https://vmito.com/vi/venues/san-cau-long-42-nguyen-trung-nguyet).
- [The Lam Sport Center](https://www.thelamsportcenter.com/), [PickleballVN](https://pickleballvn.news/san-pickleball-tp-hcm/), [Sân Pick](https://sanpick.com/san-pickleball-tphcm/).
- [Liên đoàn Bóng bàn TP. Hồ Chí Minh](https://www.bongbantphcm.vn/clb-hoi-vien), [Decathlon](https://www.decathlon.vn/blog/san-bong-ban/).


## Lưu ý

- Đây là đồ án chạy trên localhost; cần bật Apache và MySQL trước khi truy cập.
- Một số sân, lịch và giá là dữ liệu demo/tham khảo; hãy liên hệ địa điểm để xác nhận trước khi sử dụng ngoài mục đích trình diễn.
- Thanh toán chuyển khoản chưa kết nối API ngân hàng. Admin cần kiểm tra giao dịch thật trước khi xác nhận.
- Bảo vệ `config/payment.php` và thông tin cấu hình cá nhân khi chia sẻ project.
- Không chạy script seed/setup trên database có dữ liệu cần giữ nếu chưa xem mã script và sao lưu.
