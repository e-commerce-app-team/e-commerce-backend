<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryTaxSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // 1. تعيين 5% لكل التصنيفات كقيمة افتراضية
        // ============================================================
        Category::query()->update([
            'tax_rate' => 5.00,
            'tax_label' => 'ضريبة مبيعات 5%',
        ]);

        // ============================================================
        // 2. تعيين 0% للتصنيفات المعفاة
        // ============================================================
        $exempt = [
            'food',
            'agricultural',
            'groceries',
            'grocery',
            'agriculture',
            'farming',
        ];
        Category::whereIn('slug', $exempt)->update([
            'tax_rate' => 0.00,
            'tax_label' => 'معفى من الضريبة - 0%',
        ]);

        // ============================================================
        // 3. تعيين 10% للصحة والجمال والسيارات
        // ============================================================
        $ten_percent = [
            'health-beauty',
            'beauty',
            'cosmetics',
            'health',
            'cars',
            'automotive',
            'spare-parts',
            'vehicles',
        ];
        Category::whereIn('slug', $ten_percent)->update([
            'tax_rate' => 10.00,
            'tax_label' => 'ضريبة مبيعات 10%',
        ]);

        // ============================================================
        // 4. تعيين 15% للرخام والغرانيت
        // ============================================================
        $fifteen_percent = [
            'marble',
            'granite',
            'marble-granite',
        ];
        Category::whereIn('slug', $fifteen_percent)->update([
            'tax_rate' => 15.00,
            'tax_label' => 'ضريبة مبيعات 15%',
        ]);

        $this->command->info('✅ تم تحديث نسب الضريبة على جميع التصنيفات.');
        $this->command->info('   0%  → منتجات غذائية، أدوات زراعية');
        $this->command->info('   5%  → الافتراضي (إلكترونيات، أزياء، منزل...)');
        $this->command->info('   10% → الصحة والجمال، السيارات وقطع الغيار');
        $this->command->info('   15% → رخام وغرانيت');
    }
}