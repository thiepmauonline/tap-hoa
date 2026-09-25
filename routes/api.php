<?php
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;
Route::prefix('v1')->middleware(['api.token','throttle:60,1'])->group(function () {
    Route::get('/products',[ProductController::class,'index']);
    Route::get('/products/{product}',[ProductController::class,'show'])->whereNumber('product');
    Route::get('/reports/summary',[ReportController::class,'summary']);
});
