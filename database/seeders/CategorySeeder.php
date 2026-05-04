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
            ['name' => 'إلكترونيات وهواتف'],
            ['name' => 'ألبسة وأحذية'],
            ['name' => 'أدوات منزلية'],
            ['name' => 'عطور وتجميل'],
            ['name' => 'أخرى'],
        ];

        foreach ($data as $item) {
            \App\Models\Category::create($item);
        }
    }
}
