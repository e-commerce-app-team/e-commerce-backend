<?php
use App\Http\Controllers\AdController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MerchantDepartment;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BuyerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ─── Authentication ────────────────────────────────────────────────────────
Route::Post('login', [UserController::class, 'login']);
Route::post('register/buyer', [UserController::class, 'registerBuyer']);
Route::post('register/seller', [UserController::class, 'registerSeller']);
Route::post('logout', [UserController::class, 'logout'])->middleware('auth:sanctum');

// قبول دعوة الموظف (لا يحتاج Token)
Route::post('auth/staff/accept-invite', [StaffController::class, 'acceptInvite']);

// ─── OTP & Password Reset (Public) ────────────────────────────────────────

// التحقق قبل إنشاء الحساب (Pre-Registration OTP)
Route::post('auth/signup/send-otp', [OtpController::class, 'sendRegistrationOtp']);
Route::post('auth/signup/verify-otp-pre', [OtpController::class, 'verifyRegistrationOtp']);

// نسيت كلمة المرور
Route::post('auth/forgot-password', [OtpController::class, 'sendForgotPasswordOtp']);
Route::post('auth/verify-otp', [OtpController::class, 'verifyForgotPasswordOtp']);
Route::post('auth/reset-password', [OtpController::class, 'resetPassword']);

// التحقق بعد تسجيل الدخول (2FA)
Route::post('auth/login/verify-otp', [OtpController::class, 'verifyLoginOtp']);

// التحقق بعد إنشاء الحساب بالطريقة التقليدية (إن لزم)
Route::post('auth/signup/verify-otp', [OtpController::class, 'verifySignupOtp']);

// إعادة إرسال OTP (موحّد)
Route::post('auth/resend-otp', [OtpController::class, 'resendOtp']);

// ─── 2FA Toggle (Protected) ───────────────────────────────────────────────
Route::post('user/toggle-2fa', [OtpController::class, 'toggleTwoFactor'])->middleware('auth:sanctum');



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

    Route::get('my-orders', [OrderController::class, 'myOrders']);
    
    // Chat & Messaging Routes
    Route::prefix('chat')->group(function () {
        Route::get('firebase-token', [ChatController::class, 'generateFirebaseToken']);
        
        // Quick Replies
        Route::get('quick-replies', [ChatController::class, 'getQuickReplies']);
        Route::post('quick-replies', [ChatController::class, 'storeQuickReply']);
        Route::put('quick-replies/{id}', [ChatController::class, 'updateQuickReply']);
        Route::delete('quick-replies/{id}', [ChatController::class, 'deleteQuickReply']);
        
        // Auto Replies
        Route::get('auto-replies', [ChatController::class, 'getAutoReplies']);
        Route::post('auto-replies', [ChatController::class, 'storeAutoReply']);
        Route::put('auto-replies/{id}', [ChatController::class, 'updateAutoReply']);
        Route::delete('auto-replies/{id}', [ChatController::class, 'deleteAutoReply']);
        
        // Block / Report
        Route::get('blocked-users', [ChatController::class, 'getBlockedUsers']);
        Route::post('block-user', [ChatController::class, 'blockUser']);
        Route::delete('unblock-user/{id}', [ChatController::class, 'unblockUser']);
        
        Route::post('report-user', [ChatController::class, 'reportUser']);
    });
});

// 📌 Routes العامة للإعلانات (لا تحتاج تسجيل دخول)
// ============================================================
Route::get('ads/active', [AdController::class, 'getActiveAds']);
Route::get('ads/banners', [AdController::class, 'getBanners']);
Route::get('ads/promoted', [AdController::class, 'getPromotedProducts']);
Route::get('ads/featured-stores', [AdController::class, 'getFeaturedStores']);



Route::get('categories', [CategoryController::class, 'getAllCategories']);

// Buyer catalogue is public; user-specific state is included when a token is present.
Route::get('buyer/products', [BuyerController::class, 'products']);
Route::get('buyer/categories', [CategoryController::class, 'getAllCategories']);
Route::get('buyer/products/featured', [BuyerController::class, 'products'])->defaults('section', 'featured');
Route::get('buyer/products/flash-sale', [BuyerController::class, 'products'])->defaults('section', 'flash_sale');
Route::get('buyer/products/trending', [BuyerController::class, 'products'])->defaults('section', 'trending');
Route::get('buyer/products/new-arrivals', [BuyerController::class, 'products'])->defaults('section', 'new_arrivals');
Route::get('buyer/products/offers', [BuyerController::class, 'products'])->defaults('section', 'offers');
Route::get('buyer/products/recommended', [BuyerController::class, 'products'])->defaults('section', 'recommended');
Route::get('buyer/stores/featured', [BuyerController::class, 'stores'])->defaults('section', 'featured');
Route::get('buyer/products/{id}', [BuyerController::class, 'product']);
Route::get('buyer/stores', [BuyerController::class, 'stores']);
Route::get('buyer/stores/{id}', [BuyerController::class, 'store']);
Route::get('buyer/stores/{store_id}/products', [BuyerController::class, 'storeProducts']);
Route::get('buyer/stores/{store_id}/departments', [BuyerController::class, 'storeDepartments']);
Route::get('buyer/stores/{id}/reviews', [BuyerController::class, 'storeReviews']);
Route::get('buyer/products/{id}/reviews', [BuyerController::class, 'productReviews']);

Route::middleware('auth:sanctum')->prefix('buyer')->group(function () {
    Route::get('profile', [BuyerController::class, 'profile']);
    Route::put('profile', [BuyerController::class, 'updateProfile']);
    Route::get('addresses', [BuyerController::class, 'addresses']);
    Route::post('addresses', [BuyerController::class, 'addAddress']);
    Route::put('addresses/{id}', [BuyerController::class, 'updateAddress']);
    Route::delete('addresses/{id}', [BuyerController::class, 'deleteAddress']);
    Route::post('addresses/{id}/default', [BuyerController::class, 'setDefaultAddress']);
    Route::get('wallet/deposit-requests', [PaymentController::class, 'depositRequests']);
    Route::post('wallet/deposit-requests', [PaymentController::class, 'requestDeposit']);
    Route::get('cart', [BuyerController::class, 'cart']);
    Route::post('cart/add', [BuyerController::class, 'addCart']);
    Route::put('cart/update/{id}', [BuyerController::class, 'updateCart']);
    Route::delete('cart/remove/{id}', [BuyerController::class, 'removeCart']);
    Route::delete('cart/clear', [BuyerController::class, 'clearCart']);
    Route::get('favorites', [BuyerController::class, 'favorites']);
    Route::post('favorites/{id}/toggle', [BuyerController::class, 'toggleFavorite']);
    Route::post('reviews', [BuyerController::class, 'addProductReview']);
    Route::get('reviews', [BuyerController::class, 'myReviews']);
    Route::put('reviews/{id}', [BuyerController::class, 'updateReview']);
    Route::post('stores/{id}/reviews', [BuyerController::class, 'addStoreReview']);
    Route::post('stores/{id}/follow', [BuyerController::class, 'toggleFollow']);
});

// تأكد من وضع هذه المسارات داخل الـ Middleware الخاص بـ sanctum لتحديد هوية التاجر عبر auth()->id()
// المسارات محمية بـ Sanctum لتحديد هوية التاجر عبر الـ Token
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('merchant/departments', [MerchantDepartment::class, 'index']);
    Route::post('merchant/departments/store', [MerchantDepartment::class, 'storeDepartment']);
    Route::patch('merchant/departments/toggle-visibility', [MerchantDepartment::class, 'toggleVisibility']);
    Route::patch('merchant/departments/reorder', [MerchantDepartment::class, 'reorderDepartment']);
    Route::post('merchant/departments/{id}/update', [MerchantDepartment::class, 'updateDepartment']);
    Route::delete('merchant/departments/{id}', [MerchantDepartment::class, 'destroyDepartment']);

    // 📌 مسارات الفروع / المستودعات للتاجر
    Route::get('merchant/branches', [App\Http\Controllers\BranchController::class, 'index']);
    Route::post('merchant/branches', [App\Http\Controllers\BranchController::class, 'store']);
    Route::get('merchant/branches/{id}', [App\Http\Controllers\BranchController::class, 'show']);
    Route::put('merchant/branches/{id}', [App\Http\Controllers\BranchController::class, 'update']);
    Route::patch('merchant/branches/{id}/toggle', [App\Http\Controllers\BranchController::class, 'toggleActive']);
    Route::delete('merchant/branches/{id}', [App\Http\Controllers\BranchController::class, 'destroy']);

    // 📌 مسارات إدارة الموظفين للتاجر (Staff Management)
    Route::get('seller/staff', [StaffController::class, 'index']);
    Route::post('seller/staff/invite', [StaffController::class, 'invite']);
    Route::get('seller/staff/invitations', [StaffController::class, 'pendingInvitations']);
    Route::delete('seller/staff/invitations/{id}', [StaffController::class, 'cancelInvitation']);
    Route::put('seller/staff/{id}/permissions', [StaffController::class, 'updatePermissions']);
    Route::patch('seller/staff/{id}/toggle-status', [StaffController::class, 'toggleStatus']);
    Route::delete('seller/staff/{id}', [StaffController::class, 'removeStaff']);
});
Route::middleware('auth:sanctum')->group(function () {

    Route::post('products/filter-by-category', [ProductController::class, 'filterByCategory'])->middleware('auth:sanctum');
    Route::post('products/filter-by-status', [ProductController::class, 'filterByStatus'])->middleware('auth:sanctum');
    Route::post('products/filter-by-stock', [ProductController::class, 'filterByStock'])->middleware('auth:sanctum');
    Route::post('products/filter/department', [ProductController::class, 'filterByDepartment']);
    Route::post('products', [ProductController::class, 'store'])->middleware('auth:sanctum');
    Route::put('products/{id}', [ProductController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('products/{id}', [ProductController::class, 'destroy'])->middleware('auth:sanctum');
    Route::get('products/search', [ProductController::class, 'applySearch'])->middleware('auth:sanctum');
    Route::get('products/sort', [ProductController::class, 'applySorting'])->middleware('auth:sanctum');
    Route::post('products/bulk-action', [ProductController::class, 'bulkAction'])->middleware('auth:sanctum');


    Route::post('variants/{id}/toggle', [ProductController::class, 'toggleVariant'])->middleware('auth:sanctum');


    // Invoices (Expanded)
    Route::get('invoices', [InvoiceController::class, 'getInvoices'])->middleware('auth:sanctum');
    Route::get('invoices/order/{orderId}', [InvoiceController::class, 'getOrderInvoice'])->middleware('auth:sanctum');
    Route::get('invoices/commission', [InvoiceController::class, 'getCommissionInvoices'])->middleware('auth:sanctum');
    Route::get('invoices/tax-report', [InvoiceController::class, 'getTaxReport'])->middleware('auth:sanctum');







    // 🔥 Routes خاصة بالتاجر (بدون middleware)
    Route::post('vendor/coupons/store', [UserController::class, 'store'])->middleware('auth:sanctum');
    Route::get('vendor/coupons/index', [UserController::class, 'index'])->middleware('auth:sanctum');
    Route::get('vendor/coupons/{id}/show', [UserController::class, 'show'])->middleware('auth:sanctum');
    Route::put('vendor/coupons/{id}/update', [UserController::class, 'update'])->middleware('auth:sanctum');
    Route::patch('vendor/coupons/{id}/toggle', [UserController::class, 'toggle'])->middleware('auth:sanctum');
    Route::delete('vendor/coupons/{id}/destroy', [UserController::class, 'destroy'])->middleware('auth:sanctum');
    Route::get('vendor/coupons/{id}/stats', [UserController::class, 'stats'])->middleware('auth:sanctum');

    // 🔥 Routes للمشتري (بدون middleware)
    Route::get('coupons/available', [UserController::class, 'getAvailableForBuyer'])->middleware('auth:sanctum');
    Route::post('coupons/validate', [UserController::class, 'validateCoupon'])->middleware('auth:sanctum');






    // 📌 Routes العامة (لا تحتاج تسجيل دخول) تم نقلها لخارج الميدلوير

    // 📌 Routes للمستخدمين المسجلين (تتبع المشاهدات والنقرات)
// ============================================================
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('ads/{adId}/view', [AdController::class, 'trackView']);
        Route::get('ads/{adId}/click', [AdController::class, 'trackClick']);
    });

    // 📌 Routes للتاجر (Vendor / Wholesale)
// ============================================================
    Route::middleware('auth:sanctum')->group(function () {

        // 📌 أنواع الإعلانات والأسعار
        Route::get('ads/types', [UserController::class, 'getAdTypes']);
        // 📌 عرض إعلانات التاجر مع فلترة
        Route::get('ads/indexAd', [UserController::class, 'indexAd']);
        // 📌 إنشاء طلب إعلان جديد
        Route::post('ads/storeAd', [UserController::class, 'storeAd']);
        // 📌 عرض إعلان محدد
        Route::get('ads/{id}/showAd', [UserController::class, 'showAd']);
        // 📌 تحديث إعلان (قبل الموافقة)
        Route::put('ads/{id}/updateAd', [UserController::class, 'updateAd']);
        // 📌 حذف إعلان (pending/rejected فقط)
        Route::delete('ads/{id}/destroyAd', [UserController::class, 'destroyAd']);
        // 📌 Dashboard إحصائيات الإعلانات
        Route::get('ads/dashboard/stats', [UserController::class, 'dashboard']);
    });


    // 📌 Routes للأدمن (Admin)
// ============================================================

    // إدارة الإعلانات
    Route::get('ads/index', [AdminController::class, 'index'])->middleware(['auth:sanctum', 'super_admin']);

    Route::get('ads/{id}/show', [AdminController::class, 'show'])->middleware(['auth:sanctum', 'super_admin']);
    Route::post('ads/{id}/approve', [AdminController::class, 'approveAd'])->middleware(['auth:sanctum', 'super_admin']);
    Route::post('ads/{id}/reject', [AdminController::class, 'rejectAd'])->middleware(['auth:sanctum', 'super_admin']);
    Route::post('ads/{id}/deactivate', [AdminController::class, 'deactivateAd'])->middleware(['auth:sanctum', 'super_admin']);

    // إحصائيات الإعلانات
    Route::get('ads/stats/summary', [AdminController::class, 'statsAd'])->middleware(['auth:sanctum', 'isSuperAdmin']);

    // إدارة الأقسام والتصنيفات (Admin Category Management)
    Route::post('categories/store', [CategoryController::class, 'storeCategory'])->middleware(['auth:sanctum', 'super_admin']);
    Route::post('categories/{id}/update', [CategoryController::class, 'updateCategory'])->middleware(['auth:sanctum', 'super_admin']);
    Route::delete('categories/{id}/destroy', [CategoryController::class, 'destroyCategory'])->middleware(['auth:sanctum', 'super_admin']);
    Route::patch('categories/toggle-visibility', [CategoryController::class, 'toggleVisibility'])->middleware(['auth:sanctum', 'super_admin']);
    Route::patch('categories/reorder', [CategoryController::class, 'reorderCategories'])->middleware(['auth:sanctum', 'super_admin']);

    // إعدادات المنصة (Admin Settings - Platform commission control)
    Route::get('admin/settings', [AdminSettingsController::class, 'index'])->middleware(['auth:sanctum', 'super_admin']);
    Route::put('admin/settings/{key}', [AdminSettingsController::class, 'update'])->middleware(['auth:sanctum', 'super_admin']);
});
