<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::Post('login', [UserController::class, 'login']);
Route::post('registerBuyer', [UserController::class, 'registerBuyer']);
Route::post('registerVendor', [UserController::class, 'registerVendor']);
Route::post('registerWholesale', [UserController::class, 'registerWholesale']);
Route::post('logout', [UserController::class, 'logout'])->middleware('auth:sanctum');

Route::post('admin/login', [AdminAuthController::class, 'login']);
Route::post('admin/logout', [AdminAuthController::class, 'logout'])->middleware('auth:sanctum');

Route::post('admin/users/approve/{id}', [AdminController::class, 'approve'])->middleware('auth:sanctum');
Route::post('admin/users/reject/{id}', [AdminController::class, 'reject'])->middleware('auth:sanctum');
Route::delete('admin/users/{id}', [AdminController::class, 'block'])->middleware('auth:sanctum');
Route::post('unblock/{id}', [AdminController::class, 'unblock'])->middleware('auth:sanctum'); // فك الحظر عن مستخدم
Route::get('all', [AdminController::class, 'allUsers'])->middleware('auth:sanctum');           // جلب كل المستخدمين
Route::get('pending', [AdminController::class, 'pendingUsers'])->middleware('auth:sanctum');  // جلب الناطرين موافقة
Route::get('approved', [AdminController::class, 'approvedUsers'])->middleware('auth:sanctum');// جلب المقبولين
Route::get('rejected', [AdminController::class, 'rejectedUsers'])->middleware('auth:sanctum'); // جلب المرفوضين
Route::get('blocked', [AdminController::class, 'blockedUsers'])->middleware('auth:sanctum');  // جلب المحظورين
