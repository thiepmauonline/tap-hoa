# Hệ thống quản lý cửa hàng tạp hóa SaaS

Ứng dụng Laravel 12 + Livewire 3 quản lý nhiều cửa hàng trên cùng nền tảng, với dữ liệu tách biệt theo `store_id`.

## Chức năng

- Đăng ký cửa hàng, đăng nhập và kiểm tra trạng thái thuê bao.
- Phân quyền `owner`, `manager`, `staff` ở middleware phía server.
- Quản lý sản phẩm, danh mục, nhà cung cấp, khách hàng và nhân viên.
- POS bán hàng, quét mã vạch, giảm giá, thanh toán, tích điểm và in hóa đơn.
- Hủy toàn bộ hóa đơn có lý do, hoàn kho và đảo điểm thưởng; lưu người hủy và thời gian hủy.
- Nhập hàng, cập nhật giá vốn, xem và in phiếu nhập.
- Tồn kho, cảnh báo sắp hết và sổ biến động kho.
- Báo cáo doanh thu, lợi nhuận gộp, top bán chạy và hàng tồn lâu.
- Xuất CSV doanh thu theo ngày, top 10 bán chạy và tồn kho hiện tại, hỗ trợ tiếng Việt trong Excel.
- Cài đặt thông tin cửa hàng in trên hóa đơn và đổi mật khẩu tài khoản.
- Trợ lý AI phân tích kinh doanh tích hợp qua API (Google Gemini / OpenAI) và Local Intelligence Engine, tự động tổng hợp số liệu bán chạy, cảnh báo kho, nhận diện hàng tồn lâu và đề xuất chiến lược.

## Phân quyền

| Chức năng | Owner | Manager | Staff |
|---|---:|---:|---:|
| POS, khách hàng, hóa đơn bán | Có | Có | Có |
| Sản phẩm, kho, nhập hàng, nhà cung cấp | Có | Có | Không |
| Báo cáo kinh doanh | Có | Có | Không |
| Trợ lý AI và lịch sử phân tích | Có | Có | Không |
| Quản lý nhân viên | Có | Không | Không |
| Hủy hóa đơn, xuất báo cáo CSV | Có | Có | Không |
| Sửa thông tin cửa hàng | Có | Không | Không |
| Đổi mật khẩu của mình | Có | Có | Có |

Quyền được kiểm tra tại route/middleware; ẩn menu không được xem là cơ chế bảo mật.

## Bán hàng và hủy hóa đơn

- POS hỗ trợ tiền mặt và chuyển khoản. Thu ngân xác nhận đã nhận tiền trước khi hoàn tất bán hàng; hệ thống không tự đối soát ngân hàng.
- Vào **Hóa đơn đã bán → Xem đơn** để hủy toàn bộ hóa đơn. Nhập lý do và xác nhận đã nhận lại hàng, hoàn đủ tiền khách thực trả (không bao gồm tiền thừa).
- Hủy hóa đơn chạy trong một transaction: hoàn kho, hoàn điểm đã dùng, thu hồi điểm đã tích và đánh dấu đã hủy. Mỗi hóa đơn chỉ được hủy một lần; lịch sử gốc được giữ lại.
- Nếu điểm đã tích được dùng ở hóa đơn khác khiến không đủ điểm thu hồi, thao tác hủy bị chặn và dữ liệu được giữ nguyên. Cần xử lý các hóa đơn đã dùng số điểm đó trước.
- Báo cáo, API và ngữ cảnh số liệu của AI chỉ tính hóa đơn hoàn tất. Lợi nhuận gộp = doanh thu sau giảm giá/đổi điểm − giá vốn; chưa trừ chi phí vận hành.

## Xuất báo cáo và cài đặt

- Trong **Báo cáo kinh doanh**, chọn khoảng ngày rồi tải CSV doanh thu hoặc top 10 bán chạy. Doanh thu sản phẩm được phân bổ giảm giá/đổi điểm theo tỷ trọng tiền hàng.
- CSV tồn kho là số lượng hiện tại tại thời điểm tải, không phải tồn kho lịch sử theo khoảng ngày. File có UTF-8 BOM cho Excel và giữ mã vạch có số 0 đầu.
- Trong **Cài đặt & Tài khoản**, chủ cửa hàng sửa tên, địa chỉ, số điện thoại hiển thị trên hóa đơn. Mọi tài khoản đang hoạt động có thể đổi mật khẩu của mình sau khi nhập đúng mật khẩu hiện tại.
- Khi cập nhật phiên bản có chức năng hủy hóa đơn, chạy `php artisan migrate`. Migration thêm thông tin hủy và chuyển nhãn QR ở hóa đơn cũ thành chuyển khoản, giữ nguyên số tiền và nội dung hóa đơn.

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
- Cập nhật kho và ghi biến động luôn nằm trong cùng transaction, kể cả khi gọi trực tiếp `InventoryService`.
- Mỗi lần mở quầy bán hàng hoặc tạo phiếu nhập có mã chứng từ riêng được khóa ở server. Gửi lại cùng yêu cầu chỉ trả về chứng từ đã tạo, không cộng/trừ kho hay điểm lần nữa.
- Phiếu đã nhập kho không sửa trực tiếp để tránh sai lịch sử; khi mở rộng nên dùng nghiệp vụ hủy/điều chỉnh đảo kho.

## Kiểm thử

```bash
php artisan test
```

Feature test dùng SQLite in-memory. Bật extension `pdo_sqlite` trên môi trường chạy test. Production vẫn dùng MySQL.

Các ca kiểm thử gồm rollback khi giao dịch lỗi, gửi lại yêu cầu bán/nhập hàng, nhập liệu trống, phân quyền trên phiên đang mở, số liệu báo cáo/AI, lỗi API giả lập và tính nhất quán của dữ liệu mẫu. Test AI không cần API key và không gọi dịch vụ bên ngoài.

Trợ lý AI chỉ dành cho chủ cửa hàng/quản lý. Lịch sử hiển thị 50 lượt mới nhất; HTML và liên kết không an toàn trong câu trả lời được lọc trước khi hiển thị. API lỗi sẽ chuyển sang phân tích cục bộ, không tự thử nhiều model liên tiếp. Giới hạn gửi câu hỏi là 10 lượt/phút/tài khoản.

## Triển khai production

- Đặt `APP_ENV=production`, `APP_DEBUG=false` và cấu hình HTTPS.
- Trỏ document root vào thư mục `public`.
- Chạy `php artisan migrate --force`, `php artisan optimize` và `npm run build` trong quy trình deploy.
- Dùng Supervisor/systemd cho `php artisan queue:work` khi bổ sung AI hoặc tác vụ nền.
- Thiết lập backup MySQL, xoay vòng log, giám sát `/up` và không cấp quyền ghi rộng ngoài `storage`/`bootstrap/cache`.
