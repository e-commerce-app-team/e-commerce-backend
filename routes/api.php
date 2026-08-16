<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Buyer\BuyerProfileController;
use App\Http\Controllers\Buyer\StoreFollowController;
use App\Http\Controllers\Buyer\NotificationController;
use App\Http\Controllers\MerchantDepartment;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Controllers\Buyer\BannerController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\Buyer\FavoriteController;
use App\Http\Controllers\Buyer\StoreController;
use App\Http\Controllers\Buyer\SearchController;
use App\Http\Controllers\Buyer\ReviewController;
use App\Http\Controllers\UserController;


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

    // إدارة المنتجات (Admin Product Management)
    Route::get('products', [AdminController::class, 'allProducts'])->middleware(['auth:sanctum', 'isProductsAdmin']);
    Route::get('products/vendors', [AdminController::class, 'vendorProducts'])->middleware(['auth:sanctum', 'isProductsAdmin']);
    Route::get('products/wholesale', [AdminController::class, 'wholesaleProducts'])->middleware(['auth:sanctum', 'isProductsAdmin']);
    Route::get('products/{id}', [AdminController::class, 'showProduct'])->middleware(['auth:sanctum', 'isProductsAdmin']);
    Route::get('sellers/inventory', [AdminController::class, 'allSellersInventory'])->middleware(['auth:sanctum', 'isProductsAdmin']);
    Route::delete('products/{id}', [AdminController::class, 'deleteProduct'])->middleware(['auth:sanctum', 'isProductsAdmin']);

    // إدارة الطلبات (Admin Order Management)
    Route::middleware(['auth:sanctum', 'isOrdersAdmin'])->group(function () {
        Route::get('orders/vendors', [AdminController::class, 'vendorOrders']);
        Route::get('orders/wholesale', [AdminController::class, 'wholesaleOrders']);
        Route::get('orders', [AdminController::class, 'allOrders']);
        Route::get('orders/{id}', [AdminController::class, 'showOrder'])->where('id', '[0-9]+');
    });

    // إدارة الإعلانات (Admin Ad Management)
    Route::get('ads/all', [AdminController::class, 'allAds'])->middleware(['auth:sanctum', 'super_admin']);
    Route::get('ads/pending', [AdminController::class, 'pendingAds'])->middleware(['auth:sanctum', 'super_admin']);
    Route::get('ads/active', [AdminController::class, 'activeAds'])->middleware(['auth:sanctum', 'super_admin']);
    Route::get('ads/rejected', [AdminController::class, 'rejectedAds'])->middleware(['auth:sanctum', 'super_admin']);
    Route::get('ads/expired', [AdminController::class, 'expiredAds'])->middleware(['auth:sanctum', 'super_admin']);
    Route::get('ads/{id}/details', [AdminController::class, 'showAdDetails'])->middleware(['auth:sanctum', 'super_admin']);

    // إدارة الفئات البديلة (Alternate Category Management)
    Route::get('admin/categories', [AdminController::class, 'allCategories'])->middleware(['auth:sanctum', 'super_admin']);
    Route::post('admin/categories', [AdminController::class, 'createCategory'])->middleware(['auth:sanctum', 'super_admin']);
    Route::post('admin/categories/{id}', [AdminController::class, 'updateCategory'])->middleware(['auth:sanctum', 'super_admin']);
    Route::delete('admin/categories/{id}', [AdminController::class, 'deleteCategory'])->middleware(['auth:sanctum', 'super_admin']);
});

Route::prefix('buyer')->group(function () {
    //رابط خريطة المتجر 
    Route::get('/stores/map', [StoreController::class, 'getStoresMap']);
    // مسار البحث الرئيسي (يرجع منتجات ومتاجر)
    Route::get('/search', [SearchController::class, 'search']);

    // مسار الاقتراحات الفورية أثناء الكتابة (Auto-complete)
    Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
    // 1.لمتجر واحد ..  رابط جلب شجرة الأقسام (رئيسي -> فرعي)
    Route::get('/departments/{store_id}/', [StoreController::class, 'getStoreTree']);
    // رابط جلب صفحة المتجر 
    Route::get('/stores/{id}', [StoreController::class, 'show']);
    // منتجات متجر معين
    Route::get('/stores/{store_id}/products', [StoreController::class, 'getStoreProducts']);
    // منتجات قسم معين
    Route::get('/department/{department_id}/products', [StoreController::class, 'getdepartmentProducts']);
    // رابط جلب تقييمات متجر معين
    Route::get('/stores/{id}/reviews', [StoreController::class, 'getStoreReviews']);
    // السلة
    Route::post('/cart/add', [CartController::class, 'addToCart'])->middleware('auth:sanctum');
    Route::get('/cart', [CartController::class, 'getCart'])->middleware('auth:sanctum');
    Route::put('/cart/update/{id}', [CartController::class, 'updateQty'])->middleware('auth:sanctum');
    Route::delete('/cart/remove/{id}', [CartController::class, 'removeItem'])->middleware('auth:sanctum');
    Route::delete('/cart/clear', [CartController::class, 'clearCart'])->middleware('auth:sanctum');

    // إتمام الطلب
    Route::post('/checkout', [CartController::class, 'checkout'])->middleware('auth:sanctum');
    Route::get('/orders', [OrderController::class, 'index'])->middleware('auth:sanctum');
});

//رابط ارجاع الاقسام الرئيسية للمنصة كاملة 
Route::get('/department', [MerchantDepartment::class, 'getTree']);

// 2. رابط جلب المنتجات مع الفلاتر الـ 5 الديناميكية
Route::get('/products', [ProductController::class, 'FilttetByForBuyer']);
Route::get('/products/{id}', [ProductController::class, 'showProductDetails']);
Route::post('/products/{id}/view', [ProductController::class, 'incrementViews']);

Route::middleware('auth:sanctum')->prefix('buyer')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/add', [FavoriteController::class, 'add']);
    Route::delete('/favorites/remove/{product_id}', [FavoriteController::class, 'remove']);
    Route::post('/favorites/move-to-cart', [FavoriteController::class, 'moveToCart']);
    Route::get('/profile', [BuyerProfileController::class, 'show'])->middleware('auth:sanctum');
    Route::put('/profile', [BuyerProfileController::class, 'update']);
    // متابعة وإلغاء متابعة متجر
    Route::post('/stores/{id}/follow', [StoreFollowController::class, 'follow']);
    Route::delete('/stores/{id}/unfollow', [StoreFollowController::class, 'unfollow']);

    // جلب قائمة المتاجر المتابَعة للمشتري
    Route::get('/following-stores', [StoreFollowController::class, 'followingStores']);
    // جلب قائمة الإشعارات للمستخدم الحاصل على التوكن
    Route::get('/notifications', [NotificationController::class, 'index']);
//اضافة تقييم لمنتج معين 
    Route::post('/reviews', [ReviewController::class, 'store']);
    // عرض كل التقييمات السابقة 
    Route::get('/reviews', [ReviewController::class, 'index']);
    //تعديل تقييم معين  خلال مده اقصاها 24 ساعه 
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    //تغيير حالة اشعار معين (ايقاف/تفعيل)
    Route::put('/notification-settings', [NotificationController::class, 'updatePreferences']);
    //محادثاتي (للمشتري) مع التجار 
    Route::get('/conversations', [ChatController::class, 'getBuyerConversations']);
});
//الانعكاس عند التاجر عكس الي قبلها
Route::middleware('auth:sanctum')->prefix('seller')->group(function () {
    Route::get('conversations', [ChatController::class, 'getSellerConversations']);
});

Route::prefix('home')->group(function () {
    // جلب البانرات النشطة (يعمل مع المستخدم المسجل أو الزائر)
    Route::get('banners', [BannerController::class, 'index']);
    // تسجيل نقرة على بانر محدد
    Route::post('banners/{id}/click', [BannerController::class, 'recordClick']);
});

// مسارات الأقسام للشاشة الرئيسية والزوار
Route::prefix('categories')->group(function () {
    Route::get('/main', [CategoryController::class, 'getMainCategories']);
    Route::get('/{id}/children', [CategoryController::class, 'getChildrenCategories']);
});