<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\AiLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------------------------
        // 1. TẠO CỬA HÀNG 1: TẠP HÓA HẠNH PHÚC (Cửa hàng chính)
        // -------------------------------------------------------------------------
        $store1 = Store::create([
            'name' => 'Tạp Hóa Hạnh Phúc',
            'code' => 'tap-hoa-hanh-phuc',
            'phone' => '0987654321',
            'address' => '123 Đường Nguyễn Trãi, Phường Bến Thành, Quận 1, TP. Hồ Chí Minh',
            'status' => 1,
            'expired_at' => Carbon::now()->addYear(),
        ]);

        // Tài khoản Cửa hàng 1
        $owner1 = User::create([
            'store_id' => $store1->id,
            'name' => 'Nguyễn Văn Hạnh',
            'email' => 'admin@taphoa.com',
            'phone' => '0987654321',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 1,
        ]);

        $staff1 = User::create([
            'store_id' => $store1->id,
            'name' => 'Trần Thị Thu',
            'email' => 'nhanvien@taphoa.com',
            'phone' => '0912345678',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'status' => 1,
        ]);

        // Danh mục Cửa hàng 1
        $catBeverage = Category::create(['store_id' => $store1->id, 'name' => 'Đồ uống & Nước giải khát', 'description' => 'Nước ngọt, nước suối, trà đóng chai các loại']);
        $catSnack = Category::create(['store_id' => $store1->id, 'name' => 'Bánh kẹo & Bánh snack', 'description' => 'Bánh quy, bim bim, kẹo đóng gói các loại']);
        $catSpice = Category::create(['store_id' => $store1->id, 'name' => 'Gia vị & Đồ khô', 'description' => 'Mì tôm, nước mắm, dầu ăn, hạt nêm, đường, muối']);
        $catDairy = Category::create(['store_id' => $store1->id, 'name' => 'Sữa & Chế phẩm từ sữa', 'description' => 'Sữa tươi, sữa chua, sữa đặc, váng sữa']);
        $catPersonal = Category::create(['store_id' => $store1->id, 'name' => 'Hóa mỹ phẩm & Nhu yếu phẩm', 'description' => 'Bột giặt, nước rửa chén, dầu gội, giấy vệ sinh']);

        // Nhà cung cấp Cửa hàng 1
        $supCoca = Supplier::create(['store_id' => $store1->id, 'name' => 'Công ty TNHH NGK Coca-Cola Việt Nam', 'phone' => '02838291234', 'email' => 'order@cocacola.vn', 'address' => 'Xa lộ Hà Nội, P. Linh Trung, TP. Thủ Đức, TP. HCM', 'note' => 'Giao hàng thứ 2 và thứ 5']);
        $supPepsi = Supplier::create(['store_id' => $store1->id, 'name' => 'Công ty Cổ phần Suntory Pepsico Việt Nam', 'phone' => '02838219999', 'email' => 'sales@pepsico.com.vn', 'address' => 'Tòa nhà Sheraton, Q. 1, TP. HCM', 'note' => 'Chiết khấu 3% khi nhập trên 50 thùng']);
        $supAcecook = Supplier::create(['store_id' => $store1->id, 'name' => 'Công ty Cổ phần Acecook Việt Nam', 'phone' => '02838154064', 'email' => 'info@acecookvietnam.com', 'address' => 'KCN Tân Bình, Q. Tân Phú, TP. HCM', 'note' => 'Nhà cung cấp mì Hảo Hảo']);
        $supKinhDo = Supplier::create(['store_id' => $store1->id, 'name' => 'Công ty Cổ phần Mondelez Kinh Đô', 'phone' => '02743755888', 'email' => 'kinhdo@mdlz.com', 'address' => 'KCN VSIP 1, Thuận An, Bình Dương', 'note' => 'Chuyên bánh Cosy, AFC, Solite']);
        $supUnilever = Supplier::create(['store_id' => $store1->id, 'name' => 'Công ty TNHH Quốc tế Unilever Việt Nam', 'phone' => '02838236666', 'email' => 'customer.care@unilever.com', 'address' => '156 Nguyễn Lương Bằng, Q. 7, TP. HCM', 'note' => 'Nhà cung cấp OMO, Sunsilk, Nam Ngư']);
        $supVinamilk = Supplier::create(['store_id' => $store1->id, 'name' => 'Công ty Cổ phần Sữa Việt Nam (Vinamilk)', 'phone' => '02854155555', 'email' => 'vinamilk@vinamilk.com.vn', 'address' => '10 Tân Trào, Q. 7, TP. HCM', 'note' => 'Giao hàng bảo quản lạnh']);

        // Khách hàng Cửa hàng 1
        $c1 = Customer::create(['store_id' => $store1->id, 'name' => 'Nguyễn Văn An', 'phone' => '0905123456', 'address' => 'Số 12 Nguyễn Trãi, Q. 1', 'point' => 150]);
        $c2 = Customer::create(['store_id' => $store1->id, 'name' => 'Trần Thị Bình', 'phone' => '0914987654', 'address' => 'Số 45 Lê Lợi, Q. 1', 'point' => 230]);
        $c3 = Customer::create(['store_id' => $store1->id, 'name' => 'Lê Hoàng Nam', 'phone' => '0988112233', 'address' => 'Số 88 Trần Hưng Đạo, Q. 1', 'point' => 95]);
        $c4 = Customer::create(['store_id' => $store1->id, 'name' => 'Phạm Thị Mai', 'phone' => '0935445566', 'address' => 'Số 102 Điện Biên Phủ, Q. 3', 'point' => 310]);

        // Sản phẩm & Kho hàng Cửa hàng 1
        $productsData = [
            [
                'name' => 'Nước ngọt Coca-Cola Lon 320ml',
                'category_id' => $catBeverage->id,
                'supplier_id' => $supCoca->id,
                'barcode' => '8935001100018',
                'unit' => 'lon',
                'cost_price' => 8500,
                'sale_price' => 11000,
                'min_stock' => 24,
                'image' => 'images/products/coca_cola.png',
                'qty' => 180,
            ],
            [
                'name' => 'Nước ngọt Pepsi Lon 320ml',
                'category_id' => $catBeverage->id,
                'supplier_id' => $supPepsi->id,
                'barcode' => '8935001200025',
                'unit' => 'lon',
                'cost_price' => 8500,
                'sale_price' => 11000,
                'min_stock' => 24,
                'image' => null,
                'qty' => 120,
            ],
            [
                'name' => 'Mì Hảo Hảo Tôm Chua Cay 75g',
                'category_id' => $catSpice->id,
                'supplier_id' => $supAcecook->id,
                'barcode' => '8934567890123',
                'unit' => 'gói',
                'cost_price' => 3800,
                'sale_price' => 4500,
                'min_stock' => 60,
                'image' => 'images/products/hao_hao.png',
                'qty' => 350,
            ],
            [
                'name' => 'Bánh Cosy Mè Dừa 288g',
                'category_id' => $catSnack->id,
                'supplier_id' => $supKinhDo->id,
                'barcode' => '8938888777666',
                'unit' => 'hộp',
                'cost_price' => 32000,
                'sale_price' => 42000,
                'min_stock' => 10,
                'image' => 'images/products/cosy.png',
                'qty' => 15,
            ],
            [
                'name' => 'Bim Bim Oishi Vị Tôm Cay 40g',
                'category_id' => $catSnack->id,
                'supplier_id' => $supKinhDo->id,
                'barcode' => '8931111222333',
                'unit' => 'gói',
                'cost_price' => 5000,
                'sale_price' => 7000,
                'min_stock' => 20,
                'image' => null,
                'qty' => 8,
            ],
            [
                'name' => 'Nước mắm Nam Ngư Đệ Nhị 500ml',
                'category_id' => $catSpice->id,
                'supplier_id' => $supUnilever->id,
                'barcode' => '8939999888777',
                'unit' => 'chai',
                'cost_price' => 38000,
                'sale_price' => 48000,
                'min_stock' => 12,
                'image' => 'images/products/nam_ngu.png',
                'qty' => 4,
            ],
            [
                'name' => 'Sữa tươi Vinamilk 100% Tiệt trùng 1L',
                'category_id' => $catDairy->id,
                'supplier_id' => $supVinamilk->id,
                'barcode' => '8934673111222',
                'unit' => 'hộp',
                'cost_price' => 28000,
                'sale_price' => 36000,
                'min_stock' => 15,
                'image' => null,
                'qty' => 45,
            ],
            [
                'name' => 'Dầu ăn Tường An Cooking Oil 1L',
                'category_id' => $catSpice->id,
                'supplier_id' => $supUnilever->id,
                'barcode' => '8934563222111',
                'unit' => 'chai',
                'cost_price' => 42000,
                'sale_price' => 52000,
                'min_stock' => 10,
                'image' => null,
                'qty' => 30,
            ],
            [
                'name' => 'Bột giặt OMO Chữa Lành Vết Bẩn 800g',
                'category_id' => $catPersonal->id,
                'supplier_id' => $supUnilever->id,
                'barcode' => '8934888999000',
                'unit' => 'túi',
                'cost_price' => 48000,
                'sale_price' => 60000,
                'min_stock' => 10,
                'image' => null,
                'qty' => 25,
            ],
            [
                'name' => 'Dầu gội Sunsilk Mềm Mượt Diệu Kỳ 650g',
                'category_id' => $catPersonal->id,
                'supplier_id' => $supUnilever->id,
                'barcode' => '8934777888999',
                'unit' => 'chai',
                'cost_price' => 110000,
                'sale_price' => 135000,
                'min_stock' => 5,
                'image' => null,
                'qty' => 12,
            ],
        ];

        $createdProducts = [];

        foreach ($productsData as $pData) {
            $qty = $pData['qty'];
            unset($pData['qty']);

            $product = Product::create(array_merge($pData, [
                'store_id' => $store1->id,
                'status' => 1,
            ]));

            Inventory::create([
                'store_id' => $store1->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'last_imported_at' => Carbon::now()->subDays(rand(2, 15)),
            ]);

            $createdProducts[$product->barcode] = $product;
        }

        // 8. Tạo Phiếu Nhập Hàng Mẫu
        $po1 = PurchaseOrder::create([
            'store_id' => $store1->id,
            'po_code' => 'PN-' . date('Ymd') . '-0001',
            'supplier_id' => $supCoca->id,
            'user_id' => $owner1->id,
            'purchase_date' => Carbon::now()->subDays(5),
            'total_amount' => 1700000,
            'discount_amount' => 50000,
            'paid_amount' => 1650000,
            'payment_status' => 'paid',
            'status' => 'completed',
            'note' => 'Nhập 200 lon Coca-Cola bổ sung dịp cuối tuần',
        ]);

        if (isset($createdProducts['8935001100018'])) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $po1->id,
                'product_id' => $createdProducts['8935001100018']->id,
                'quantity' => 200,
                'price' => 8500,
                'subtotal' => 1700000,
            ]);
        }

        // 9. Tạo Hóa Đơn Bán Hàng 7 Ngày Gần Đây (Đường biểu đồ Doanh thu ấn tượng)
        $pCoca = $createdProducts['8935001100018'] ?? null;
        $pHaoHao = $createdProducts['8934567890123'] ?? null;
        $pCosy = $createdProducts['8938888777666'] ?? null;
        $pSunsilk = $createdProducts['8934777888999'] ?? null;

        // Dữ liệu 7 ngày gần đây
        $salesPattern = [
            6 => 1450000, // 6 ngày trước
            5 => 980000,  // 5 ngày trước
            4 => 2100000, // 4 ngày trước
            3 => 1750000, // 3 ngày trước
            2 => 3200000, // 2 ngày trước
            1 => 2400000, // 1 ngày trước
            0 => 1250000, // Hôm nay
        ];

        foreach ($salesPattern as $daysAgo => $amount) {
            $orderDate = Carbon::now()->subDays($daysAgo)->setHour(rand(9, 19));

            $ord = Order::create([
                'store_id' => $store1->id,
                'order_code' => 'HD-' . $orderDate->format('Ymd') . '-' . sprintf('%04d', rand(1, 999)),
                'customer_id' => ($daysAgo % 2 == 0) ? $c1->id : $c2->id,
                'user_id' => $staff1->id,
                'order_date' => $orderDate,
                'subtotal_amount' => $amount,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'paid_amount' => $amount,
                'change_amount' => 0,
                'payment_method' => ($daysAgo % 3 == 0) ? 'qr' : 'cash',
                'status' => 'completed',
                'note' => 'Đơn bán lẻ tại quầy',
            ]);

            if ($pCoca && $pHaoHao) {
                OrderItem::create([
                    'order_id' => $ord->id,
                    'product_id' => $pCoca->id,
                    'quantity' => rand(5, 20),
                    'cost_price' => $pCoca->cost_price,
                    'price' => $pCoca->sale_price,
                    'subtotal' => $amount * 0.4,
                ]);

                OrderItem::create([
                    'order_id' => $ord->id,
                    'product_id' => $pHaoHao->id,
                    'quantity' => rand(20, 50),
                    'cost_price' => $pHaoHao->cost_price,
                    'price' => $pHaoHao->sale_price,
                    'subtotal' => $amount * 0.6,
                ]);
            }
        }

        // 10. Tạo Lịch sử Hỏi đáp Mẫu (AI Logs)
        AiLog::create([
            'store_id' => $store1->id,
            'user_id' => $owner1->id,
            'question' => 'Mặt hàng nào bán chạy nhất tuần này?',
            'answer' => "**Top sản phẩm bán chạy trong tuần:**\n\n1. **Mì Hảo Hảo Tôm Chua Cay 75g**: Đã bán 210 gói (Doanh số: 945.000 đ)\n2. **Nước ngọt Coca-Cola Lon 320ml**: Đã bán 85 lon (Doanh số: 935.000 đ)\n3. **Bánh Cosy Mè Dừa 288g**: Đã bán 12 hộp (Doanh số: 504.000 đ)\n\n**Gợi ý vận hành:** Mì Hảo Hảo có lượng tiêu thụ nhanh và đều, nên duy trì tồn kho tối thiểu từ 60 gói trở lên.",
            'type' => 'analysis',
            'tokens_used' => 280,
            'created_at' => Carbon::now()->subHours(1),
        ]);

        AiLog::create([
            'store_id' => $store1->id,
            'user_id' => $owner1->id,
            'question' => 'Có sản phẩm nào trong kho sắp hết cần nhập thêm không?',
            'answer' => "**Cảnh báo tồn kho & Kế hoạch nhập hàng:**\n\nHệ thống ghi nhận 2 sản phẩm chạm hoặc dưới mức tồn kho tối thiểu:\n- **Bim Bim Oishi Vị Tôm Cay 40g**: Còn 8 gói (Mức tối thiểu: 20 gói)\n- **Nước mắm Nam Ngư Đệ Nhị 500ml**: Còn 4 chai (Mức tối thiểu: 12 chai)\n\nBạn có thể vào mục **Nhập hàng kho** để tạo phiếu nhập từ nhà cung cấp tương ứng.",
            'type' => 'restock',
            'tokens_used' => 310,
            'created_at' => Carbon::now()->subMinutes(15),
        ]);

        // -------------------------------------------------------------------------
        // 2. TẠO CỬA HÀNG 2: TẠP HÓA MINH ANH (Đà Nẵng) - Đảm bảo kiểm thử Multi-Tenant
        // -------------------------------------------------------------------------
        $store2 = Store::create([
            'name' => 'Tạp Hóa Minh Anh',
            'code' => 'tap-hoa-minh-anh',
            'phone' => '0905999888',
            'address' => '56 Nguyễn Văn Linh, Quận Hải Châu, TP. Đà Nẵng',
            'status' => 1,
            'expired_at' => Carbon::now()->addYear(),
        ]);

        User::create([
            'store_id' => $store2->id,
            'name' => 'Lê Minh Anh',
            'email' => 'minhanh@taphoa.com',
            'phone' => '0905999888',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 1,
        ]);
    }
}
