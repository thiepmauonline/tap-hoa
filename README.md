# Hệ thống quản lý cửa hàng tạp hóa SaaS

Ứng dụng Laravel 12 + Livewire 3 quản lý nhiều cửa hàng trên cùng nền tảng, với dữ liệu tách biệt theo `store_id`.

## Chức năng

- Đăng ký cửa hàng, đăng nhập và kiểm tra trạng thái thuê bao.
- Phân quyền `owner`, `manager`, `staff` ở middleware phía server.
- Quản lý sản phẩm, danh mục, nhà cung cấp, khách hàng và nhân viên.
- POS bán hàng, quét mã vạch, giảm giá, thanh toán, tích điểm và in hóa đơn.
- Nhập hàng, cập nhật giá vốn, xem và in phiếu nhập.
- Tồn kho, cảnh báo sắp hết và sổ biến động kho.
- Báo cáo doanh thu, lợi nhuận gộp, top bán chạy và hàng tồn lâu.
- REST API đọc sản phẩm, tồn kho và báo cáo, xác thực bằng bearer token.
- Trợ lý phân tích nằm trong module riêng và sẽ được tích hợp API AI sau.

## Phân quyền

| Chức năng | Owner | Manager | Staff |
|---|---:|---:|---:|
| POS, khách hàng, hóa đơn bán | Có | Có | Có |
| Sản phẩm, kho, nhập hàng, nhà cung cấp | Có | Có | Không |
| Báo cáo kinh doanh | Có | Có | Không |
| Quản lý nhân viên | Có | Không | Không |

Quyền được kiểm tra tại route/middleware; ẩn menu không được xem là cơ chế bảo mật.

## Cài đặt

Yêu cầu: PHP 8.2+, Composer, MySQL và Node.js.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Thiết lập kết nối MySQL trong `.env` trước khi migrate. Không commit `.env` hoặc API token.

## REST API

Tạo hoặc thay token cho một tài khoản:

```bash
php artisan api:issue-token owner@example.com
```

Token chỉ hiển thị một lần; database chỉ lưu SHA-256 hash. Gửi token qua header:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

Endpoints:

- `GET /api/v1/products?search=&low_stock=1&per_page=20`
- `GET /api/v1/products/{id}`
- `GET /api/v1/reports/summary?date_from=2026-08-01&date_to=2026-08-31`

Mọi endpoint tự lọc dữ liệu theo cửa hàng của token. Endpoint báo cáo chỉ dành cho owner/manager và được rate-limit.

## Quy tắc tồn kho

- Nhập hàng, bán hàng và điều chỉnh kho chạy trong transaction.
- Dòng tồn kho được khóa khi cập nhật để tránh bán âm khi nhiều thu ngân thao tác đồng thời.
- Giá và sản phẩm được nạp lại từ server lúc thanh toán; không tin dữ liệu Livewire từ trình duyệt.
- Mọi thay đổi kho mới được ghi vào `inventory_movements`, gồm số trước/sau, người thực hiện, loại và chứng từ nguồn.
- Phiếu đã nhập kho không sửa trực tiếp để tránh sai lịch sử; khi mở rộng nên dùng nghiệp vụ hủy/điều chỉnh đảo kho.

## Kiểm thử

```bash
php artisan test
```

Feature test dùng SQLite in-memory. Bật extension `pdo_sqlite` trên môi trường chạy test. Production vẫn dùng MySQL.

## Triển khai production

- Đặt `APP_ENV=production`, `APP_DEBUG=false` và cấu hình HTTPS.
- Trỏ document root vào thư mục `public`.
- Chạy `php artisan migrate --force`, `php artisan optimize` và `npm run build` trong quy trình deploy.
- Dùng Supervisor/systemd cho `php artisan queue:work` khi bổ sung AI hoặc tác vụ nền.
- Thiết lập backup MySQL, xoay vòng log, giám sát `/up` và không cấp quyền ghi rộng ngoài `storage`/`bootstrap/cache`.
