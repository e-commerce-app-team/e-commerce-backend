<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use App\Http\Middleware\IsSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::Post('login', [UserController::class, 'login']);
Route::post('register/buyer', [UserController::class, 'registerBuyer']);
Route::post('register/seller', [UserController::class, 'registerSeller']);
Route::post('logout', [UserController::class, 'logout'])->middleware('auth:sanctum');

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

Route::post('storeOrders', [OrderController::class, 'store'])->middleware('auth:sanctum');

Route::get('categories', [CategoryController::class, 'getAllCategories'])->middleware('auth:sanctum');
// شغلي
Route::middleware('auth:sanctum')->group(function () {

    // 1. إنشاء منتج جديد (مع متغيراته تلقائياً إن وجدت)
    Route::post('products', [ProductController::class, 'store']);

    // 2. تحديث جزئي لبيانات المنتج أو تحديث/إعادة بناء المتغيرات
    Route::patch('products/{id}', [ProductController::class, 'update']);

    // 3. حذف المنتج نهائياً (وسيتم حذف كافة المتغيرات والصور التابعة له من السيرفر)
    Route::delete('products/{id}', [ProductController::class, 'destroy']);
});

//هاد منشان اذا بدي اعمل ايقاف او تفعيل احدى المتغيرات الخاصة بمنتج معين
// رووت سريع لتفعيل أو إيقاف متغير محدد وتعديل كميته وسعره مباشرة
Route::patch('variants/{id}/toggle', [ProductController::class, 'toggleVariant'])->middleware('auth:sanctum');
//بالنسبة للاقسام 
Route::get('categories', [CategoryController::class, 'index']);
Route::middleware(['auth:sanctum', IsSuperAdmin::class])->group(function () {

    Route::post('categories', [CategoryController::class, 'store']);
    Route::post('categories/{id}/update', [CategoryController::class, 'update']);
    Route::delete('categories/{id}/delete', [CategoryController::class, 'destroy']);
    // روت تبديل حالة ظهور القسم او احد افرعه  فورا
    Route::post('categories/toggle-visibility', [CategoryController::class, 'toggleVisibility']);

    // روت السحب والإفلات وتحديث الترتيب في قاعدة البيانات
    Route::post('categories/reorder', [CategoryController::class, 'reorder']);
});
