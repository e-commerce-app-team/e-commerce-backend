<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['name' => 'إلكترونيات وهواتف', 'slug' => 'electronics-and-phones'],
            ['name' => 'ألبسة وأحذية', 'slug' => 'clothes-and-shoes'],
            ['name' => 'أدوات منزلية', 'slug' => 'home-appliances'],
            ['name' => 'عطور وتجميل', 'slug' => 'perfumes-and-cosmetics'],
            ['name' => 'أخرى', 'slug' => 'others'],
        ];

        foreach ($data as $item) {
            \App\Models\Category::create($item);
        }
    }
}
