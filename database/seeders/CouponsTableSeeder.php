<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\Seeder;

class CouponsTableSeeder extends Seeder
{
    public function run(): void
    {
        $vendor = User::where('role', 'vendor')->first();
        $wholesale = User::where('role', 'wholesale')->first();

        $coupons = [
            // كوبونات البائع العادي
            [
                'seller_id' => $vendor->id,
                'code' => 'SUMMER20',
                'title' => 'خصم 20% على الإلكترونيات',
                'description' => 'خصم 20% على جميع منتجات الإلكترونيات',
                'type' => 'percentage',
                'value' => 20,
                'min_order_amount' => 500,
                'max_uses' => 100,
                'usage_limit_per_user' => 'once',
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'apply_to_all_products' => false,
                'product_ids' => null,
                'is_active' => true,
            ],
            [
                'seller_id' => $vendor->id,
                'code' => 'FLASH50',
                'title' => 'خصم 50 ريال على الطلبات',
                'description' => 'خصم 50 ريال على أي طلب بقيمة 300 ريال فأكثر',
                'type' => 'fixed',
                'value' => 50,
                'min_order_amount' => 300,
                'max_uses' => 50,
                'usage_limit_per_user' => 'once',
                'starts_at' => now(),
                'expires_at' => now()->addDays(15),
                'apply_to_all_products' => true,
                'product_ids' => null,
                'is_active' => true,
            ],
            // كوبونات تاجر الجملة
            [
                'seller_id' => $wholesale->id,
                'code' => 'WHOLESALE10',
                'title' => 'خصم 10% للجملة',
                'description' => 'خصم 10% على طلبات الجملة بقيمة 1000 ريال فأكثر',
                'type' => 'percentage',
                'value' => 10,
                'min_order_amount' => 1000,
                'max_uses' => 200,
                'usage_limit_per_user' => 'unlimited',
                'starts_at' => now(),
                'expires_at' => now()->addMonths(3),
                'apply_to_all_products' => true,
                'product_ids' => null,
                'is_active' => true,
            ],
            [
                'seller_id' => $wholesale->id,
                'code' => 'FREESHIP',
                'title' => 'شحن مجاني',
                'description' => 'شحن مجاني على الطلبات بقيمة 500 ريال فأكثر',
                'type' => 'free_shipping',
                'value' => 0,
                'min_order_amount' => 500,
                'max_uses' => 150,
                'usage_limit_per_user' => 'once',
                'starts_at' => now(),
                'expires_at' => now()->addDays(30),
                'apply_to_all_products' => true,
                'product_ids' => null,
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::create($coupon);
        }

        $this->command->info('✅ تم إنشاء الكوبونات بنجاح!');
    }
}