<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductsTableSeeder extends Seeder
{
    public function run(): void
    {
        $vendor = User::where('role', 'vendor')->first();
        $wholesale = User::where('role', 'wholesale')->first();

        // جلب معرفات التصنيفات
        $electronics = \App\Models\Category::where('slug', 'electronics')->first();
        $fashion = \App\Models\Category::where('slug', 'fashion')->first();
        $home = \App\Models\Category::where('slug', 'home-appliances')->first();
        $furniture = \App\Models\Category::where('slug', 'furniture')->first();

        // ============================================================
        // 📌 منتجات البائع العادي (Vendor)
        // ============================================================
        $vendorProducts = [
            [
                'name' => 'هاتف ذكي iPhone 15 Pro',
                'description' => 'هاتف ذكي بشاشة 6.1 بوصة، كاميرا 48 ميجابكسل، معالج A16',
                'original_price' => 3500,
                'offer_price' => 2999,
                'sku' => 'PH-001',
                'quantity' => 50,
                'status' => 'active',
                'sales_count' => 120,
                'category_id' => $electronics->id,
                'images' => json_encode(['products/iphone-15-pro.jpg']),
            ],
            [
                'name' => 'ساعة ذكية Apple Watch Series 9',
                'description' => 'ساعة ذكية مع مراقبة النوم، النبض، وقياس الأكسجين',
                'original_price' => 1500,
                'offer_price' => 1199,
                'sku' => 'SW-001',
                'quantity' => 30,
                'status' => 'active',
                'sales_count' => 85,
                'category_id' => $electronics->id,
                'images' => json_encode(['products/apple-watch.jpg']),
            ],
            [
                'name' => 'سماعة لاسلكية AirPods Pro 2',
                'description' => 'سماعة لاسلكية مع خاصية العزل الصوتي النشط',
                'original_price' => 800,
                'offer_price' => 649,
                'sku' => 'AP-001',
                'quantity' => 100,
                'status' => 'active',
                'sales_count' => 200,
                'category_id' => $electronics->id,
                'images' => json_encode(['products/airpods-pro.jpg']),
            ],
            [
                'name' => 'جهاز لابتوب MacBook Air M2',
                'description' => 'لابتوب بشاشة 13.6 بوصة، معالج M2، 8GB RAM، 256GB SSD',
                'original_price' => 4500,
                'offer_price' => 3999,
                'sku' => 'MB-001',
                'quantity' => 20,
                'status' => 'active',
                'sales_count' => 45,
                'category_id' => $electronics->id,
                'images' => json_encode(['products/macbook-air.jpg']),
            ],
            [
                'name' => 'قميص رجالي كلاسيك',
                'description' => 'قميص رجالي قطني 100%، مقاسات متعددة، ألوان متنوعة',
                'original_price' => 200,
                'offer_price' => 150,
                'sku' => 'SH-001',
                'quantity' => 200,
                'status' => 'active',
                'sales_count' => 350,
                'category_id' => $fashion->id,
                'images' => json_encode(['products/shirt.jpg']),
            ],
        ];

        foreach ($vendorProducts as $product) {
            Product::create(array_merge($product, ['user_id' => $vendor->id]));
        }

        // ============================================================
        // 📌 منتجات تاجر الجملة (Wholesale)
        // ============================================================
        $wholesaleProducts = [
            [
                'name' => 'شاشة سامسونج 55 بوصة 4K',
                'description' => 'شاشة ذكية 4K بتقنية HDR، 120Hz، دعم التطبيقات الذكية',
                'original_price' => 3500,
                'offer_price' => 3200,
                'wholesale_price' => 2800,
                'sku' => 'TV-001',
                'quantity' => 50,
                'status' => 'active',
                'sales_count' => 30,
                'category_id' => $home->id,
                'images' => json_encode(['products/samsung-tv.jpg']),
            ],
            [
                'name' => 'غسالة أوتوماتيك 7 كجم',
                'description' => 'غسالة أوتوماتيك بتقنية العاكس، 7 كجم، 1400 دورة',
                'original_price' => 2000,
                'offer_price' => null,
                'wholesale_price' => 1500,
                'sku' => 'WM-001',
                'quantity' => 30,
                'status' => 'active',
                'sales_count' => 18,
                'category_id' => $home->id,
                'images' => json_encode(['products/washer.jpg']),
            ],
            [
                'name' => 'ثلاجة سامسونج 16 قدم',
                'description' => 'ثلاجة بابين، 16 قدم، تقنية No Frost',
                'original_price' => 2800,
                'offer_price' => 2500,
                'wholesale_price' => 2100,
                'sku' => 'FR-001',
                'quantity' => 20,
                'status' => 'active',
                'sales_count' => 12,
                'category_id' => $home->id,
                'images' => json_encode(['products/fridge.jpg']),
            ],
            [
                'name' => 'كنبة 3 مقاعد كلاسيك',
                'description' => 'كنبة فاخرة 3 مقاعد، جلد طبيعي، إطار خشبي متين',
                'original_price' => 2500,
                'offer_price' => 2200,
                'wholesale_price' => 1800,
                'sku' => 'SB-001',
                'quantity' => 10,
                'status' => 'active',
                'sales_count' => 8,
                'category_id' => $furniture->id,
                'images' => json_encode(['products/sofa.jpg']),
            ],
        ];

        foreach ($wholesaleProducts as $product) {
            Product::create(array_merge($product, ['user_id' => $wholesale->id]));
        }

        $this->command->info('✅ تم إنشاء المنتجات بنجاح!');
        $this->command->info('   - ' . count($vendorProducts) . ' منتج للبائع العادي');
        $this->command->info('   - ' . count($wholesaleProducts) . ' منتج لتاجر الجملة');
    }
}