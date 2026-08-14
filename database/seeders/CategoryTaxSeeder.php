<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryTaxSeeder extends Seeder
{
    /**
     * ضبط النسب الضريبية السورية على التصنيفات الموجودة.
     *
     * الجدول المعتمد:
     * 0%  → منتجات غذائية، أدوات زراعية
     * 5%  → الأغلبية (إلكترونيات، أزياء، منزل، رياضة...)
     * 10% → الصحة والجمال (تصنيف واحد ثابت)، السيارات وقطع الغيار
     * 15% → رخام وغرانيت
     */
    public function run(): void
    {
        // الخطوة 1: تعيين 5% لكل التصنيفات كقيمة افتراضية
        Category::query()->update([
            'tax_rate'  => 5.00,
            'tax_label' => 'ضريبة مبيعات 5%',
        ]);

        // الخطوة 2: تعيين 0% للتصنيفات المعفاة
        $exempt = [
            'food', 'منتجات-غذائية', 'منتجات_غذائية',
            'groceries', 'grocery',
            'agricultural', 'الأدوات-الزراعية', 'ادوات-زراعية', 'ادوات_زراعية',
            'agriculture', 'farming',
        ];
        Category::whereIn('slug', $exempt)->update([
            'tax_rate'  => 0.00,
            'tax_label' => 'معفى من الضريبة - 0%',
        ]);

        // الخطوة 3: تعيين 10% للصحة والجمال (تصنيف واحد ثابت) والسيارات
        $ten_percent = [
            // الصحة والجمال — تصنيف واحد بقيمة ثابتة 10%
            'health-beauty', 'health_beauty', 'الصحة-والجمال', 'الصحة_والجمال',
            'beauty', 'cosmetics', 'health', 'الصحة', 'الجمال', 'التجميل',
            // السيارات وقطع الغيار
            'cars', 'السيارات', 'الحديد-والسيارات',
            'automotive', 'spare-parts', 'قطع-غيار', 'قطع_غيار',
            'vehicles',
        ];
        Category::whereIn('slug', $ten_percent)->update([
            'tax_rate'  => 10.00,
            'tax_label' => 'ضريبة مبيعات 10%',
        ]);

        // الخطوة 4: تعيين 15% للرخام والغرانيت
        $fifteen_percent = [
            'marble', 'الرخام', 'رخام',
            'granite', 'الغرانيت', 'غرانيت',
            'marble-granite', 'رخام-وغرانيت',
        ];
        Category::whereIn('slug', $fifteen_percent)->update([
            'tax_rate'  => 15.00,
            'tax_label' => 'ضريبة مبيعات 15%',
        ]);

        $this->command->info('✅ تم ضبط النسب الضريبية على جميع التصنيفات.');
        $this->command->info('   0%  → منتجات غذائية، أدوات زراعية');
        $this->command->info('   5%  → الافتراضي (إلكترونيات، أزياء، منزل...)');
        $this->command->info('   10% → الصحة والجمال، السيارات وقطع الغيار');
        $this->command->info('   15% → رخام وغرانيت');
        $this->command->info('');
        $this->command->warn('⚠️  إذا لم تتطابق السلاجات، يمكن ضبط النسبة يدوياً من لوحة الأدمن.');
    }
}
