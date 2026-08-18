<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdsTableSeeder extends Seeder
{
    public function run(): void
    {
        $vendor = User::where('role', 'vendor')->first();
        $wholesale = User::where('role', 'wholesale')->first();

        $ads = [
            // إعلانات البائع
            [
                'seller_id' => $vendor->id,
                'title' => 'عرض خاص على iPhone 15 Pro',
                'description' => 'خصم 20% على جميع هواتف iPhone 15 Pro لفترة محدودة',
                'type' => 'banner',
                'duration' => '1_week',
                'price' => 15000,
                'status' => 'active',
                'views_count' => 15000,
                'clicks_count' => 1200,
                'starts_at' => now()->subDays(2),
                'expires_at' => now()->addDays(5),
                'image_url' => 'ads/iphone-banner.jpg',
                'link' => 'https://example.com/iphone',
            ],
            [
                'seller_id' => $vendor->id,
                'title' => 'Apple Watch Series 9',
                'description' => 'أحدث إصدار من ساعات Apple الذكية بسعر مميز',
                'type' => 'promoted_product',
                'duration' => '3_days',
                'price' => 8000,
                'status' => 'active',
                'views_count' => 8500,
                'clicks_count' => 680,
                'starts_at' => now()->subDays(1),
                'expires_at' => now()->addDays(2),
                'image_url' => 'ads/apple-watch.jpg',
                'link' => 'https://example.com/watch',
            ],
            [
                'seller_id' => $vendor->id,
                'title' => 'متجر المالكي - وجهتك للإلكترونيات',
                'description' => 'أفضل الأسعار على جميع الأجهزة الإلكترونية',
                'type' => 'featured_store',
                'duration' => '1_month',
                'price' => 50000,
                'status' => 'active',
                'views_count' => 23000,
                'clicks_count' => 1800,
                'starts_at' => now()->subDays(10),
                'expires_at' => now()->addDays(20),
                'image_url' => 'ads/maliki-store.jpg',
                'link' => 'https://example.com/maliki-store',
            ],
            [
                'seller_id' => $vendor->id,
                'title' => 'إشعار عروض الجمعة البيضاء',
                'description' => 'خصومات تصل إلى 50% لفترة محدودة في متجر المالكي',
                'type' => 'paid_notification',
                'duration' => '1_day',
                'price' => 15000,
                'status' => 'expired',
                'views_count' => 35000,
                'clicks_count' => 2500,
                'starts_at' => now()->subDays(2),
                'expires_at' => now()->subDays(1),
                'image_url' => null,
                'link' => null,
            ],
            // إعلانات تاجر الجملة
            [
                'seller_id' => $wholesale->id,
                'title' => 'عروض الجملة - خصومات تصل إلى 30%',
                'description' => 'عروض خاصة لتجار التجزئة على جميع المنتجات',
                'type' => 'banner',
                'duration' => '1_week',
                'price' => 20000,
                'status' => 'active',
                'views_count' => 12000,
                'clicks_count' => 950,
                'starts_at' => now()->subDays(3),
                'expires_at' => now()->addDays(4),
                'image_url' => 'ads/wholesale-banner.jpg',
                'link' => 'https://example.com/wholesale',
            ],
            [
                'seller_id' => $wholesale->id,
                'title' => 'شاشات سامسونج بأسعار الجملة',
                'description' => 'شاشات 4K بأسعار الجملة لتجار التجزئة',
                'type' => 'promoted_product',
                'duration' => '1_week',
                'price' => 12000,
                'status' => 'pending',
                'views_count' => 0,
                'clicks_count' => 0,
                'starts_at' => now(),
                'expires_at' => now()->addWeek(),
                'image_url' => 'ads/samsung-tv-wholesale.jpg',
                'link' => 'https://example.com/wholesale-tv',
            ],
        ];

        foreach ($ads as $ad) {
            Ad::create($ad);
        }

        $this->command->info('✅ تم إنشاء الإعلانات بنجاح!');
        $this->command->info('   - ' . count($ads) . ' إعلان');
        $this->command->info('   - بعضها active، بعضها pending، وبعضها expired');
    }
}