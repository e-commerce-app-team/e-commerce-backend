<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // ============================================================
            // 0% ضريبة
            // ============================================================
            [
                'name' => 'منتجات غذائية',
                'slug' => 'food',
                'tax_rate' => 0,
                'tax_label' => 'معفى من الضريبة - 0%',
                'order_position' => 0,
            ],
            [
                'name' => 'الأدوات الزراعية',
                'slug' => 'agricultural',
                'tax_rate' => 0,
                'tax_label' => 'معفى من الضريبة - 0%',
                'order_position' => 1,
            ],

            // ============================================================
            // 5% ضريبة
            // ============================================================
            [
                'name' => 'الإلكترونيات والهواتف',
                'slug' => 'electronics',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 2,
            ],
            [
                'name' => 'المنزل والأجهزة المنزلية',
                'slug' => 'home-appliances',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 3,
            ],
            [
                'name' => 'الأزياء والموضة',
                'slug' => 'fashion',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 4,
            ],
            [
                'name' => 'الرياضة والمعدات الرياضية',
                'slug' => 'sports',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 5,
            ],
            [
                'name' => 'الأثاث والمفروشات',
                'slug' => 'furniture',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 6,
            ],
            [
                'name' => 'ألعاب الأطفال',
                'slug' => 'toys',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 7,
            ],
            [
                'name' => 'القرطاسية والأدوات المكتبية',
                'slug' => 'stationery',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 8,
            ],
            [
                'name' => 'الإكسسوارات والهدايا',
                'slug' => 'accessories',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 9,
            ],
            [
                'name' => 'المنتجات اليدوية والحرفية',
                'slug' => 'handmade',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 10,
            ],
            [
                'name' => 'الحقائب ومستلزمات السفر',
                'slug' => 'luggage',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 11,
            ],
            [
                'name' => 'المجوهرات والساعات',
                'slug' => 'jewelry',
                'tax_rate' => 5,
                'tax_label' => 'ضريبة مبيعات 5%',
                'order_position' => 12,
            ],

            // ============================================================
            // 10% ضريبة
            // ============================================================
            [
                'name' => 'الصحة والجمال',
                'slug' => 'health-beauty',
                'tax_rate' => 10,
                'tax_label' => 'ضريبة مبيعات 10%',
                'order_position' => 13,
            ],
            [
                'name' => 'السيارات وقطع الغيار',
                'slug' => 'cars',
                'tax_rate' => 10,
                'tax_label' => 'ضريبة مبيعات 10%',
                'order_position' => 14,
            ],
            [
                'name' => 'الطاقة البديلة والكهربائيات',
                'slug' => 'renewable-energy',
                'tax_rate' => 10,
                'tax_label' => 'ضريبة مبيعات 10%',
                'order_position' => 15,
            ],

            // ============================================================
            // 15% ضريبة
            // ============================================================
            [
                'name' => 'مواد وأدوات البناء',
                'slug' => 'construction',
                'tax_rate' => 15,
                'tax_label' => 'ضريبة مبيعات 15%',
                'order_position' => 16,
            ],
            [
                'name' => 'الرخام والغرانيت',
                'slug' => 'marble-granite',
                'tax_rate' => 15,
                'tax_label' => 'ضريبة مبيعات 15%',
                'order_position' => 17,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'tax_rate' => $category['tax_rate'],
                    'tax_label' => $category['tax_label'],
                    'order_position' => $category['order_position'],
                    'is_visible' => true,
                    'image_url' => null,
                    'icon_url' => null,
                ]
            );
        }

        $this->command->info('✅ تم إنشاء التصنيفات بنجاح!');
        $this->command->info('   0%  → منتجات غذائية، أدوات زراعية');
        $this->command->info('   5%  → الإلكترونيات، الأزياء، المنزل، الرياضة...');
        $this->command->info('   10% → الصحة والجمال، السيارات وقطع الغيار');
        $this->command->info('   15% → مواد البناء، الرخام والغرانيت');
    }
}