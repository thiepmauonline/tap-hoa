<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class ProductController extends Controller {
    public function index(Request $request): JsonResponse {
        $data = $request->validate(['search'=>'nullable|string|max:100','low_stock'=>'nullable|boolean','per_page'=>'nullable|integer|min:1|max:100']);
        $query = Product::where('store_id', $request->user()->store_id)->where('status', 1)->with(['category:id,name','inventory:id,product_id,quantity,last_imported_at']);
        if ($search = $data['search'] ?? null) $query->where(fn ($q) => $q->where('name','like',"%{$search}%")->orWhere('barcode','like',"%{$search}%"));
        if ($data['low_stock'] ?? false) $query->whereHas('inventory', fn ($q) => $q->whereColumn('inventories.quantity','<=','products.min_stock'));
        return response()->json($query->orderBy('name')->paginate($data['per_page'] ?? 20));
    }
    public function show(Request $request, int $product): JsonResponse {
        $item = Product::where('store_id', $request->user()->store_id)->with(['category','supplier','inventory'])->findOrFail($product);
        return response()->json(['data'=>$item]);
    }
}
