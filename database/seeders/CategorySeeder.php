<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['ar' => 'منتجات غذائية', 'en' => 'Food & Groceries'],
            ['ar' => 'الطاقة البديلة والكهربائيات', 'en' => 'Renewable Energy & Appliances'],
            ['ar' => 'الإلكترونيات والهواتف', 'en' => 'Electronics & Phones'],
            ['ar' => 'المنزل والأجهزة المنزلية', 'en' => 'Home & Appliances'],
            ['ar' => 'الأزياء والموضة', 'en' => 'Clothing & Fashion'],
            ['ar' => 'الصحة والجمال', 'en' => 'Beauty & Wellness'],
            ['ar' => 'ألعاب الأطفال', 'en' => 'Toys & Games'],
            ['ar' => 'الأدوات الزراعية', 'en' => 'Garden & Farming Tools'],
            ['ar' => 'السيارات وقطع الغيار', 'en' => 'Cars & Auto Parts'],
            ['ar' => 'القرطاسية والأدوات المكتبية', 'en' => 'Stationery & Office Supplies'],
            ['ar' => 'الرياضة والمعدات الرياضية', 'en' => 'Sports & Fitness'],
            ['ar' => 'الإكسسوارات والهدايا', 'en' => 'Gifts & Accessories'],
            ['ar' => 'المنتجات اليدوية والحرفية', 'en' => 'Handmade & Crafts'],
            ['ar' => 'الحقائب ومستلزمات السفر', 'en' => 'Luggage & Travel'],
            ['ar' => 'المجوهرات والساعات', 'en' => 'Jewelry & Watches'],
            ['ar' => 'مواد وأدوات البناء', 'en' => 'Hardware & Building Materials'],
        ];

        foreach ($categories as $index => $category) {
            Category::updateOrCreate(
                ['slug' => Str::slug($category['en'])],
                [
                    'name' => [
                        'ar' => $category['ar'],
                        'en' => $category['en'],
                    ],
                    'order_position' => $index,
                    'is_visible' => true,
                ]
            );
        }
    }
}