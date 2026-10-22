<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DocumentCodeGenerator;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreOneDemoProductsSeeder extends Seeder
{
    private const PRODUCTS = [
        ['7Up vị chanh lon 320ml', 'Đồ uống & Nước giải khát', 'Công ty Cổ phần Suntory Pepsico Việt Nam', 'lon', 7500, 10000, 24, 50, 'images/products/7up.svg'],
        ['Sting dâu đỏ chai 330ml', 'Đồ uống & Nước giải khát', 'Công ty Cổ phần Suntory Pepsico Việt Nam', 'chai', 8000, 11000, 24, 6, 'images/products/sting_dau.svg'],
        ['Nước tinh khiết Aquafina 500ml', 'Đồ uống & Nước giải khát', 'Công ty Cổ phần Suntory Pepsico Việt Nam', 'chai', 4500, 7000, 24, 80, 'images/products/aquafina.svg'],
        ['Trà xanh C2 hương chanh 455ml', 'Đồ uống & Nước giải khát', 'Công ty TNHH URC Việt Nam', 'chai', 6500, 9000, 24, 18, 'images/products/c2_chanh.svg'],

        ['Bánh ChocoPie Orion hộp 12 cái', 'Bánh kẹo & Bánh snack', 'Công ty TNHH Orion Food Vina', 'hộp', 45000, 58000, 5, 12, 'images/products/chocopie.svg'],
        ['Snack Oishi bắp ngọt 32g', 'Bánh kẹo & Bánh snack', 'Công ty TNHH URC Việt Nam', 'gói', 4500, 6500, 20, 25, 'images/products/oishi_bap.svg'],
        ['Bánh quy Oreo socola 133g', 'Bánh kẹo & Bánh snack', 'Công ty Cổ phần Mondelez Kinh Đô Việt Nam', 'gói', 18000, 23000, 8, 4, 'images/products/oreo.svg'],
        ['Bánh bông lan cuộn Hura bơ sữa 200g', 'Bánh kẹo & Bánh snack', 'Công ty Cổ phần Bibica', 'hộp', 22000, 29000, 8, 18, 'images/products/hura.svg'],

        ['Nước mắm Chinsu cá cơm 500ml', 'Gia vị & Đồ khô', 'Công ty Cổ phần Hàng tiêu dùng Masan', 'chai', 28000, 36000, 10, 9, 'images/products/chinsu.svg'],
        ['Hạt nêm Maggi nấm hương 170g', 'Gia vị & Đồ khô', 'Công ty TNHH Nestlé Việt Nam', 'gói', 17000, 22000, 10, 30, 'images/products/maggi.svg'],
        ['Dầu đậu nành Simply 1L', 'Gia vị & Đồ khô', 'Công ty TNHH Dầu thực vật Cái Lân', 'chai', 44000, 55000, 8, 14, 'images/products/simply.svg'],
        ['Phở ăn liền Vifon bò 65g', 'Gia vị & Đồ khô', 'Công ty Cổ phần Kỹ nghệ Thực phẩm Việt Nam', 'gói', 5000, 7000, 40, 45, 'images/products/vifon_pho.svg'],

        ['Sữa chua Vinamilk có đường 100g', 'Sữa & Chế phẩm từ sữa', 'Công ty Cổ phần Sữa Việt Nam (Vinamilk)', 'hũ', 5000, 7000, 24, 30, 'images/products/vinamilk_sua_chua.svg'],
        ['Sữa tươi Dutch Lady có đường 180ml', 'Sữa & Chế phẩm từ sữa', 'Công ty TNHH FrieslandCampina Việt Nam', 'hộp', 6000, 8000, 24, 5, 'images/products/dutch_lady.svg'],
        ['Sữa đặc Ông Thọ đỏ 380g', 'Sữa & Chế phẩm từ sữa', 'Công ty Cổ phần Sữa Việt Nam (Vinamilk)', 'lon', 23000, 29000, 8, 11, 'images/products/ong_tho.svg'],
        ['Sữa tươi tiệt trùng TH true MILK 1L', 'Sữa & Chế phẩm từ sữa', 'Công ty Cổ phần Chuỗi thực phẩm TH', 'hộp', 34000, 42000, 8, 20, 'images/products/th_true_milk.svg'],

        ['Nước rửa chén Sunlight chanh 750g', 'Hóa mỹ phẩm & Nhu yếu phẩm', 'Công ty TNHH Quốc tế Unilever Việt Nam', 'chai', 28000, 36000, 8, 15, 'images/products/sunlight.svg'],
        ['Xà phòng Lifebuoy bảo vệ sức khỏe 90g', 'Hóa mỹ phẩm & Nhu yếu phẩm', 'Công ty TNHH Quốc tế Unilever Việt Nam', 'bánh', 14000, 18000, 10, 7, 'images/products/lifebuoy.svg'],
        ['Kem đánh răng Colgate Total 150g', 'Hóa mỹ phẩm & Nhu yếu phẩm', 'Công ty TNHH Colgate-Palmolive Việt Nam', 'tuýp', 37000, 47000, 8, 12, 'images/products/colgate.svg'],
        ['Khăn giấy Pulppy 180 tờ', 'Hóa mỹ phẩm & Nhu yếu phẩm', 'Công ty TNHH New Toyo Pulppy Việt Nam', 'gói', 22000, 29000, 10, 3, 'images/products/pulppy.svg'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $store = Store::whereIn('code', ['cua-hang-huy-hoang', 'tap-hoa-hanh-phuc'])->firstOrFail();
            $owner = User::where('store_id', $store->id)->where('role', 'owner')->firstOrFail();
            $staff = User::where('store_id', $store->id)->where('role', 'staff')->firstOrFail();
            $customers = Customer::where('store_id', $store->id)->orderBy('id')->get();
            $categories = Category::where('store_id', $store->id)->get()->keyBy('name');
            $inventory = app(InventoryService::class);

            $dailySales = $this->dailySalesPlan();
            $unitsSold = array_fill(0, count(self::PRODUCTS), 0);
            foreach ($dailySales as $lines) {
                foreach ($lines as $index => $quantity) {
                    $unitsSold[$index] += $quantity;
                }
            }

            $products = [];
            $suppliers = [];
            foreach (self::PRODUCTS as $index => [$name, $categoryName, $supplierName, $unit, $cost, $price, $minStock, $endingStock, $image]) {
                $supplier = $suppliers[$supplierName] ??= Supplier::firstOrCreate(
                    ['store_id' => $store->id, 'name' => $supplierName],
                    ['note' => 'Nhà cung cấp mẫu trong dữ liệu demo.'],
                );
                $product = Product::firstOrCreate(
                    ['store_id' => $store->id, 'barcode' => DocumentCodeGenerator::internalBarcode($index + 1)],
                    [
                        'category_id' => $categories->get($categoryName)?->id,
                        'supplier_id' => $supplier->id,
                        'name' => $name,
                        'unit' => $unit,
                        'image' => $image,
                        'cost_price' => $cost,
                        'sale_price' => $price,
                        'min_stock' => $minStock,
                        'status' => 1,
                    ],
                );

                if ($product->wasRecentlyCreated) {
                    $this->adjustStock(
                        $inventory,
                        $store->id,
                        $product->id,
                        $endingStock,
                        'initial',
                        $owner->id,
                        null,
                        null,
                        'Tồn đầu kỳ mẫu; mã vạch nội bộ dùng cho demo.',
                        Carbon::today()->subDays(60),
                    );
                }

                $products[$index] = $product;
            }

            $this->createPurchaseOrders($store, $owner, $products, $unitsSold, $inventory);
            $this->createSalesOrders($store, $staff, $customers, $products, $dailySales, $inventory);
        });
    }

    private function createPurchaseOrders(Store $store, User $owner, array $products, array $unitsSold, InventoryService $inventory): void
    {
        $groups = [];
        foreach (self::PRODUCTS as $index => [, , $supplierName, , $cost]) {
            if ($unitsSold[$index] > 0) {
                $groups[$supplierName][] = [$index, $unitsSold[$index], $cost];
            }
        }

        $purchaseDate = Carbon::today()->subDays(20)->setTime(8, 0);
        foreach ($groups as $supplierName => $lines) {
            $supplier = Supplier::where('store_id', $store->id)->where('name', $supplierName)->firstOrFail();
            $total = array_sum(array_map(fn (array $line): int => $line[1] * $line[2], $lines));
            $purchaseOrder = PurchaseOrder::firstOrCreate(
                ['store_id' => $store->id, 'request_token' => 'demo20-pn-'.$purchaseDate->format('Ymd').'-'.$supplier->id],
                [
                    'po_code' => app(DocumentCodeGenerator::class)->nextPurchaseCode($store->id, $purchaseDate),
                    'supplier_id' => $supplier->id,
                    'user_id' => $owner->id,
                    'purchase_date' => $purchaseDate,
                    'total_amount' => $total,
                    'discount_amount' => 0,
                    'paid_amount' => $total,
                    'payment_status' => 'paid',
                    'status' => 'completed',
                    'note' => 'Phiếu nhập mẫu bổ sung hàng cho bộ dữ liệu báo cáo 20 sản phẩm.',
                ],
            );

            if (! $purchaseOrder->wasRecentlyCreated) {
                continue;
            }

            foreach ($lines as [$index, $quantity, $cost]) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $products[$index]->id,
                    'quantity' => $quantity,
                    'price' => $cost,
                    'subtotal' => $quantity * $cost,
                ]);
                $this->adjustStock(
                    $inventory,
                    $store->id,
                    $products[$index]->id,
                    $quantity,
                    'purchase',
                    $owner->id,
                    'purchase_order',
                    $purchaseOrder->id,
                    'Nhập hàng mẫu theo phiếu '.$purchaseOrder->po_code,
                    $purchaseDate,
                );
            }
        }
    }

    private function createSalesOrders(Store $store, User $staff, $customers, array $products, array $dailySales, InventoryService $inventory): void
    {
        foreach ($dailySales as $daysAgo => $lines) {
            $orderDate = Carbon::today()->subDays($daysAgo)->setTime(9 + ($daysAgo % 9), 0);
            $items = [];
            $total = 0;
            foreach ($lines as $index => $quantity) {
                $product = $products[$index];
                $subtotal = $quantity * (float) $product->sale_price;
                $items[] = [$product, $quantity, $subtotal];
                $total += $subtotal;
            }

            $order = Order::firstOrCreate(
                ['store_id' => $store->id, 'request_token' => 'demo20-hd-'.$orderDate->format('Ymd')],
                [
                    'order_code' => app(DocumentCodeGenerator::class)->nextOrderCode($store->id, $orderDate),
                    'customer_id' => $customers->isEmpty() ? null : $customers[$daysAgo % $customers->count()]->id,
                    'user_id' => $staff->id,
                    'order_date' => $orderDate,
                    'subtotal_amount' => $total,
                    'discount_amount' => 0,
                    'points_earned' => 0,
                    'total_amount' => $total,
                    'paid_amount' => $total,
                    'change_amount' => 0,
                    'payment_method' => $daysAgo % 3 === 0 ? 'transfer' : 'cash',
                    'status' => 'completed',
                    'note' => 'Hóa đơn bán lẻ mẫu, tạo để minh họa báo cáo doanh thu và lợi nhuận.',
                ],
            );

            if (! $order->wasRecentlyCreated) {
                continue;
            }

            foreach ($items as [$product, $quantity, $subtotal]) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'cost_price' => $product->cost_price,
                    'price' => $product->sale_price,
                    'subtotal' => $subtotal,
                ]);
                $this->adjustStock(
                    $inventory,
                    $store->id,
                    $product->id,
                    -$quantity,
                    'sale',
                    $staff->id,
                    'order',
                    $order->id,
                    'Xuất kho theo hóa đơn mẫu '.$order->order_code,
                    $orderDate,
                );
            }
        }
    }

    private function dailySalesPlan(): array
    {
        $sales = [];
        for ($day = 0; $day < 14; $day++) {
            $sales[$day] = [];
            for ($line = 0; $line < 5; $line++) {
                $index = ($day * 3 + $line * 4) % count(self::PRODUCTS);
                $sales[$day][$index] = 1 + (($day * 7 + $index * 3) % 6);
            }
        }

        return $sales;
    }

    private function adjustStock(
        InventoryService $inventory,
        int $storeId,
        int $productId,
        int $quantity,
        string $type,
        int $userId,
        ?string $referenceType,
        ?int $referenceId,
        string $note,
        Carbon $occurredAt,
    ): void {
        $inventory->adjust($storeId, $productId, $quantity, $type, $userId, $referenceType, $referenceId, $note);

        InventoryMovement::where('product_id', $productId)->latest('id')->firstOrFail()
            ->forceFill(['created_at' => $occurredAt, 'updated_at' => $occurredAt])
            ->save();

        if ($type === 'purchase') {
            \App\Models\Inventory::where('product_id', $productId)->update(['last_imported_at' => $occurredAt]);
        }
    }
}
