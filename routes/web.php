<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Dashboard;
use App\Livewire\Products\Index as ProductIndex;
use App\Livewire\Categories\Index as CategoryIndex;
use App\Livewire\Inventories\Index as InventoryIndex;
use App\Livewire\Suppliers\Index as SupplierIndex;
use App\Livewire\Customers\Index as CustomerIndex;
use App\Livewire\PurchaseOrders\Index as PurchaseOrderIndex;
use App\Livewire\PurchaseOrders\Create as PurchaseOrderCreate;
use App\Livewire\Orders\Index as OrderIndex;
use App\Livewire\Users\Index as UserIndex;
use App\Livewire\Pos\Cashier;
use App\Livewire\Ai\Assistant as AiAssistant;

/*
|--------------------------------------------------------------------------
| Web Routes (SaaS Multi-tenant Grocery Store Management)
|--------------------------------------------------------------------------
*/

// Guest Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

// Logout Route
Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

// Authenticated Store Routes
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/pos', Cashier::class)->name('pos');

    Route::get('/products', ProductIndex::class)->name('products.index');
    Route::get('/categories', CategoryIndex::class)->name('categories.index');
    Route::get('/inventories', InventoryIndex::class)->name('inventories.index');

    Route::get('/suppliers', SupplierIndex::class)->name('suppliers.index');
    Route::get('/customers', CustomerIndex::class)->name('customers.index');

    Route::get('/purchase-orders', PurchaseOrderIndex::class)->name('purchase-orders.index');
    Route::get('/purchase-orders/create', PurchaseOrderCreate::class)->name('purchase-orders.create');

    Route::get('/orders', OrderIndex::class)->name('orders.index');
    Route::get('/users', UserIndex::class)->name('users.index');

    Route::get('/ai-assistant', AiAssistant::class)->name('ai.assistant');
});
