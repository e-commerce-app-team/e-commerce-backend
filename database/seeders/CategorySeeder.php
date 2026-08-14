<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // ✅ استخدم delete بدلاً من truncate
        Category::query()->delete();

        $data = [
            ['name' => 'منتجات غذائية', 'slug' => 'food-products'],
            ['name' => 'الطاقة البدنية والكهربائيات', 'slug' => 'renewable-energy-and-electrical'],
            ['name' => 'إلكترونيات وموبايلات', 'slug' => 'electronics-and-phones'],
            ['name' => 'المنزل والأجهزة المنزلية', 'slug' => 'home-and-appliances'],
            ['name' => 'أزياء وموضة', 'slug' => 'fashion-and-style'],
            ['name' => 'الصحة والجمال', 'slug' => 'health-and-beauty'],
            ['name' => 'ألعاب أطفال', 'slug' => 'kids-toys'],
            ['name' => 'أدوات زراعية', 'slug' => 'agricultural-tools'],
            ['name' => 'سيارات وقطع غيار', 'slug' => 'cars-and-spare-parts'],
            ['name' => 'قرطاسية ومكتبات', 'slug' => 'stationery-and-books'],
            ['name' => 'رياضة', 'slug' => 'sports'],
            ['name' => 'اكسسوارات وهدايا', 'slug' => 'accessories-and-gifts'],
            ['name' => 'منتجات يدوية وحرفية', 'slug' => 'handmade-and-crafts'],
            ['name' => 'حقائب وسفر', 'slug' => 'bags-and-travel'],
            ['name' => 'مجوهرات', 'slug' => 'jewelry'],
            ['name' => 'مواد وأدوات بناء', 'slug' => 'building-materials-and-tools'],
        ];

        foreach ($data as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name']]
            );
        }
    }
}