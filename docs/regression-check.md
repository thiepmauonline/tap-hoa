# Kiểm tra hồi quy ngày 16/10/2026

## Kết quả

- `php artisan test`: **58 bài đạt, 519 assertions**. Các bài có database dùng SQLite riêng trong bộ nhớ.
- Trình duyệt Chrome headless: **11 nhóm thao tác đạt**, dùng SQLite và tài khoản thử riêng; không ghi nhận lỗi JavaScript hoặc HTTP 5xx trong các luồng đã chạy. Có thao tác xóa số lượng/giảm giá rồi nhập lại và hỏi AI ở chế độ cục bộ.
- Kiểm tra chỉ đọc trên MySQL của dự án: schema hủy đơn đã có; truy vấn tổng hợp, doanh thu theo ngày và top sản phẩm chạy được cho cả 2 cửa hàng. Doanh thu ngày khớp tổng kỳ, lợi nhuận khớp doanh thu trừ giá vốn.
- Blade biên dịch thành công.

## Phạm vi

| Nhóm | Đã kiểm tra |
|---|---|
| Đăng nhập và tài khoản | Owner/staff đăng nhập, đổi mật khẩu rồi đăng nhập lại; quyền truy cập các trang của owner/manager/staff |
| POS | Quét mã vạch, tăng/xóa số lượng, tiền mặt/chuyển khoản, tiền thừa, đổi điểm, thông tin hóa đơn và cửa sổ in |
| Kiểm tra dữ liệu POS | Nạp lại giá từ server, chặn giỏ hàng/sản phẩm/khách hàng ngoài cửa hàng, giảm giá quá mức, thiếu tiền/điểm/tồn kho |
| Transaction | Thiếu hàng ở dòng sau phải hoàn tác các dòng trước; hủy lỗi giữa chừng không làm lệch kho/điểm |
| Hủy hóa đơn | Phân quyền, lý do, xác nhận/hủy xác nhận trên trình duyệt, hoàn kho/điểm, chặn hủy lặp, không tính đơn hủy trong báo cáo |
| Nhập hàng và kho | Tạo phiếu nhập, cập nhật tồn/giá vốn, xem phiếu; điều chỉnh kho yêu cầu lý do và ghi biến động |
| Báo cáo | Trừ giảm giá/điểm đúng một lần, phân bổ giảm giá theo giá trị sản phẩm, khoảng ngày, CSV, chống trộn dữ liệu cửa hàng |
| Cài đặt | Lưu thông tin cửa hàng, cập nhật thông tin hóa đơn, quyền sửa, mật khẩu cũ và xác nhận mật khẩu mới |
| Phiên đang mở | Khóa tài khoản hoặc hạ vai trò phải chặn các thao tác từ trang đã mở trước đó |
| Gửi lại yêu cầu | Gửi lại cùng snapshot thanh toán/nhập hàng không tạo thêm chứng từ, không trừ/cộng kho hoặc đổi điểm lần hai; đơn đã hủy không hiển thị thanh toán thành công khi gửi lại |
| Tạo mới bị lỗi | Lỗi khi tạo chủ cửa hàng, tồn ban đầu hoặc sổ biến động phải rollback toàn bộ phần dữ liệu liên quan |
| AI và API | Phân quyền AI, HTML không an toàn, 50 lượt mới nhất, đúng khoảng 7/30 ngày, phân loại lời khuyên; giả lập phản hồi API và lỗi kết nối; API sản phẩm lọc cửa hàng và kiểm tra token |
| Dữ liệu mẫu | Thành tiền dòng hàng = số lượng × đơn giá, tổng hóa đơn khớp dòng hàng; số dư kho khớp sổ biến động và điểm thưởng có chứng từ |

## Lỗi đã tái hiện và sửa trong đợt này

1. Lưu thông tin sản phẩm có thể ghi đè tồn kho đã thay đổi do bán hàng. Form sửa sản phẩm hiện chỉ đọc số lượng; điều chỉnh tồn qua màn hình kho.
2. Middleware riêng về vai trò và trạng thái tài khoản chưa được kiểm tra lại trên thao tác Livewire. Đã đăng ký persistent middleware và kiểm thử bằng HTTP update có snapshot thật.
3. Validation trong `render()` của báo cáo có thể làm lỗi khi bộ lọc đang không hợp lệ. Đã xử lý lỗi tại chỗ, ẩn kết quả/xuất báo cáo theo kỳ cho đến khi bộ lọc hợp lệ, và kiểm thử sửa lại giá trị.
4. Gửi lại yêu cầu có thể tạo hóa đơn hoặc phiếu nhập trùng. Đã dùng mã chứng từ khóa ở server và ràng buộc unique sẵn có để nhận diện yêu cầu đã xử lý.
5. Tạo cửa hàng/sản phẩm và gọi cập nhật kho trực tiếp có thể để lại dữ liệu dở dang nếu bước sau thất bại. Đã bao transaction và kiểm thử lỗi giả lập.
6. Ô tiền, điểm, số lượng bị xóa hoặc có số thập phân không hợp lệ có thể gây lỗi kiểu dữ liệu. Đã chuyển sang validation và hiển thị lỗi trên form; giá/tiền được làm tròn nhất quán, chặn giá trị vượt giới hạn database.
7. Staff có thể đọc số liệu quản lý qua AI; HTML trả lời chưa lọc, lịch sử lấy 50 lượt cũ nhất. Đã sửa phân quyền, lọc Markdown và thứ tự lịch sử.
8. Số liệu AI bị lệch khoảng 7/30 ngày, doanh số sản phẩm chưa trừ giảm giá; hàng mới chưa bán bị tính là tồn lâu. Đã dùng chung cách tính với báo cáo và xét ngày nhập/tạo sản phẩm.
9. Khi API lỗi, nhiều lượt thử model có thể kéo dài quá thời gian request; token bị sinh ngẫu nhiên. Đã giới hạn timeout, dùng model cấu hình, chuyển dự phòng và ghi nhận usage thực tế/0 khi phân tích cục bộ.
10. Số điện thoại/email/địa chỉ thiếu validation; dữ liệu mẫu không khớp dòng hàng, kho và điểm; tìm kiếm API với chuỗi `0` bị bỏ qua. Đã sửa và bổ sung kiểm thử.

## Giới hạn xác minh

Chưa kiểm thử tải nhiều thu ngân đồng thời hoặc in giấy trên máy in vật lý. Cửa sổ in đã được mở và đối chiếu nội dung; các kiểm thử stock thay đổi dùng mô phỏng giao dịch xen kẽ. Mã tích hợp AI được kiểm thử bằng phản hồi/lỗi giả lập; tính sẵn sàng của dịch vụ bên ngoài phụ thuộc kết nối và tài khoản API.
