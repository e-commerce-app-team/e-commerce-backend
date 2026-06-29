<?php
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MerchantDepartment;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::Post('login', [UserController::class, 'login']);
Route::post('register/buyer', [UserController::class, 'registerBuyer']);
Route::post('register/seller', [UserController::class, 'registerSeller']);
Route::post('logout', [UserController::class, 'logout'])->middleware('auth:sanctum');

Route::post('seller/store-settings/create', [UserController::class, 'createStoreSettings'])->middleware('auth:sanctum');
Route::post('seller/store-settings/update', [UserController::class, 'updateStoreSettings'])->middleware('auth:sanctum');
Route::get('seller/store-settings', [UserController::class, 'getStoreSettings'])->middleware('auth:sanctum');

Route::post('admin/login', [AdminAuthController::class, 'login']);
Route::post('admin/logout', [AdminAuthController::class, 'logout'])->middleware('auth:sanctum');

Route::post('admin/users/approve/{id}', [AdminController::class, 'approve'])->middleware(['auth:sanctum', 'isUsersAdmin']);
Route::post('admin/users/reject/{id}', [AdminController::class, 'reject'])->middleware(['auth:sanctum', 'isUsersAdmin']);
Route::delete('admin/users/{id}', [AdminController::class, 'block'])->middleware(['auth:sanctum', 'isUsersAdmin']);
Route::post('unblock/{id}', [AdminController::class, 'unblock'])->middleware(['auth:sanctum', 'isUsersAdmin']);
Route::post('admin/deposit', [AdminController::class, 'depositByAdmin'])->middleware(['auth:sanctum', 'isUsersAdmin']);
Route::get('all', [AdminController::class, 'allUsers'])->middleware(['auth:sanctum', 'isUsersAdmin']);
Route::get('pending', [AdminController::class, 'pendingUsers'])->middleware(['auth:sanctum', 'isUsersAdmin']);
Route::get('approved', [AdminController::class, 'approvedUsers'])->middleware(['auth:sanctum', 'isUsersAdmin']);
Route::get('rejected', [AdminController::class, 'rejectedUsers'])->middleware(['auth:sanctum', 'isUsersAdmin']);
Route::get('blocked', [AdminController::class, 'blockedUsers'])->middleware(['auth:sanctum', 'isUsersAdmin']);

Route::get('balance', [PayoutController::class, 'getBalance'])->middleware('auth:sanctum');       // رؤية الرصيد
Route::get('history', [PayoutController::class, 'payoutHistory'])->middleware('auth:sanctum');   // تاريخ السحوبات
Route::post('payouts/instant-withdraw', [PayoutController::class, 'instantWithdraw'])->middleware('auth:sanctum');

Route::get('buyerBalance', [PaymentController::class, 'getWalletBalance'])->middleware('auth:sanctum');
Route::get('buyerHistory', [PaymentController::class, 'getTransactionHistory'])->middleware('auth:sanctum');
Route::post('orders/{orderId}/pay', [PaymentController::class, 'payAndTransfer'])->middleware('auth:sanctum');

Route::post('orders/{orderId}/confirm-delivery', [PaymentController::class, 'confirmDelivery'])->middleware('auth:sanctum');


Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('storeOrders', [OrderController::class, 'store']); // إنشاء طلب
    Route::get('orders/badges', [OrderController::class, 'getVendorBadges']); // تبويبات التاجر بالعدد
    Route::get('orders/{id}', [OrderController::class, 'show']); // تفاصيل الطلب الكاملة محمية
    Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']); // تحديث الحالة والتايم لاين

    Route::post('orders/search', [OrderController::class, 'search']);
    Route::post('orders/filter-by-date', [OrderController::class, 'filterByDate']);
    Route::post('orders/filter-by-amount', [OrderController::class, 'filterByAmount']);
    Route::post('orders/sales-report', [OrderController::class, 'salesReport']);
    Route::post('orders/export-csv', [OrderController::class, 'exportCSV']);

    Route::post('orders/accept', [OrderController::class, 'acceptOrder']);
    Route::post('orders/reject', [OrderController::class, 'rejectOrder']);
    Route::post('orders/update-time', [OrderController::class, 'updatePreparationTime']);
    Route::post('orders/ready-shipping', [OrderController::class, 'readyForShipping']);
});


Route::get('categories', [CategoryController::class, 'getAllCategories'])->middleware('auth:sanctum');

// تأكد من وضع هذه المسارات داخل الـ Middleware الخاص بـ sanctum لتحديد هوية التاجر عبر auth()->id()
// المسارات محمية بـ Sanctum لتحديد هوية التاجر عبر الـ Token
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('merchant/departments', [MerchantDepartment::class, 'index']);
    Route::post('merchant/departments/store', [MerchantDepartment::class, 'storeDepartment']);
    Route::patch('merchant/departments/toggle-visibility', [MerchantDepartment::class, 'toggleVisibility']);
    Route::patch('merchant/departments/reorder', [MerchantDepartment::class, 'reorderDepartment']);
    Route::post('merchant/departments/{id}/update', [MerchantDepartment::class, 'updateDepartment']);
    Route::delete('merchant/departments/{id}', [MerchantDepartment::class, 'destroyDepartment']);

});
Route::middleware('auth:sanctum')->group(function () {

    Route::post('products/filter-by-category', [ProductController::class, 'filterByCategory'])->middleware('auth:sanctum');
    Route::post('products/filter-by-status', [ProductController::class, 'filterByStatus'])->middleware('auth:sanctum');
    Route::post('products/filter-by-stock', [ProductController::class, 'filterByStock'])->middleware('auth:sanctum');
    Route::post('products', [ProductController::class, 'store'])->middleware('auth:sanctum');
    Route::put('products/{id}', [ProductController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('products/{id}', [ProductController::class, 'destroy'])->middleware('auth:sanctum');
    Route::get('products/search', [ProductController::class, 'applySearch'])->middleware('auth:sanctum');
    Route::get('products/sort', [ProductController::class, 'applySorting'])->middleware('auth:sanctum');
    Route::post('products/bulk-action', [ProductController::class, 'bulkAction'])->middleware('auth:sanctum');


    Route::post('variants/{id}/toggle', [ProductController::class, 'toggleVariant'])->middleware('auth:sanctum');


    Route::get('wholesale/invoices', [InvoiceController::class, 'getInvoices'])->middleware('auth:sanctum');
    Route::get('wholesale/reports/vat', [InvoiceController::class, 'getVatReport'])->middleware('auth:sanctum');

});


