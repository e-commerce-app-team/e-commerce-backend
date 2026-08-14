<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Category;
use App\Models\Department;
use App\Models\Branch;
use App\Models\Product;
use Faker\Factory as Faker;

class RealDataSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('بدء زراعة 10 تجار (5 جملة، 5 مفرق) مع 15 منتج لكل منهم...');

        $faker = Faker::create('ar_SA'); // لتوليد أسماء وبيانات عربية

        // مسارات الصور
        $userImagesPath = 'C:\Users\Alaa\Pictures\Camera Roll';
        $productImagesPath = 'C:\Users\Alaa\Pictures\e_commerce';

        Storage::disk('public')->makeDirectory('users');
        Storage::disk('public')->makeDirectory('stores');
        Storage::disk('public')->makeDirectory('products');
        Storage::disk('public')->makeDirectory('categories');

        $userImages = File::exists($userImagesPath) ? File::files($userImagesPath) : [];
        $productImages = File::exists($productImagesPath) ? File::files($productImagesPath) : [];

        // إنشاء تصنيفات 
        $categories = ['إلكترونيات', 'ملابس', 'أحذية', 'إكسسوارات', 'عطور', 'أدوات منزلية'];
        $createdCategories = [];
        foreach ($categories as $catName) {
            $createdCategories[] = Category::firstOrCreate(
                ['slug' => Str::slug($catName)],
                ['name' => $catName, 'is_visible' => true]
            );
        }

        $copyImage = function ($sourceFiles, $destinationDir) {
            if (empty($sourceFiles)) return null;
            $randomFile = $sourceFiles[array_rand($sourceFiles)];
            $extension = pathinfo($randomFile->getFilename(), PATHINFO_EXTENSION);
            $newName = $destinationDir . '/' . Str::random(10) . '.' . $extension;
            File::copy($randomFile->getRealPath(), storage_path('app/public/' . $newName));
            return $newName;
        };

        // توليد رقم هاتف من 10 أرقام فريد
        $generatePhone = function () {
            do {
                $phone = '05' . mt_rand(10000000, 99999999);
            } while (User::where('phone', $phone)->exists());
            return $phone;
        };

        // كلمة المرور تستوفي الشروط (حرف كبير، صغير، رقم)
        $password = Hash::make('Password123');

        $sellers = [];

        // 1. إنشاء 5 تجار جملة
        for ($i = 1; $i <= 5; $i++) {
            $sellers[] = User::create([
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName . ' (جملة)',
                'email' => "wholesale{$i}_" . time() . "@test.com",
                'phone' => $generatePhone(),
                'password' => $password,
                'role' => 'wholesale',
                'status' => 'approved',
                'store_name' => 'شركة ' . $faker->company,
                'store_description' => $faker->realText(100),
                'category' => $categories[array_rand($categories)],
                'commercial_registration_number' => mt_rand(1000000000, 9999999999),
                'profile_photo' => $copyImage($userImages, 'users'),
                'store_logo' => $copyImage($userImages, 'stores'),
                'store_cover_photo' => $copyImage($userImages, 'stores'),
            ]);
        }

        // 2. إنشاء 5 تجار مفرق
        for ($i = 1; $i <= 5; $i++) {
            $sellers[] = User::create([
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName . ' (مفرق)',
                'email' => "vendor{$i}_" . time() . "@test.com",
                'phone' => $generatePhone(),
                'password' => $password,
                'role' => 'vendor',
                'status' => 'approved',
                'store_name' => 'متجر ' . $faker->company,
                'store_description' => $faker->realText(100),
                'category' => $categories[array_rand($categories)],
                'profile_photo' => $copyImage($userImages, 'users'),
                'store_logo' => $copyImage($userImages, 'stores'),
                'store_cover_photo' => $copyImage($userImages, 'stores'),
            ]);
        }

        // 3. إضافة مستودعات وأقسام ومنتجات للتجار
        foreach ($sellers as $index => $seller) {
            $branch = Branch::create([
                'user_id' => $seller->id,
                'name' => 'مستودع ' . $seller->store_name,
                'address' => $faker->address,
                'lat' => 24.7136 + (mt_rand(-100, 100) / 1000),
                'lng' => 46.6753 + (mt_rand(-100, 100) / 1000),
                'phone' => $seller->phone,
                'manager_name' => $seller->first_name,
                'is_active' => true,
            ]);

            $department = Department::create([
                'seller_id' => $seller->id,
                'name' => 'قسم المنتجات الشاملة',
                'slug' => Str::slug($seller->store_name . '-' . Str::random(5)),
                'is_visible' => true,
            ]);

            // إضافة 15 منتج لكل تاجر
            for ($p = 1; $p <= 15; $p++) {
                $prodImages = [];
                for($j = 0; $j < rand(1,3); $j++) {
                    $img = $copyImage($productImages, 'products');
                    if($img) $prodImages[] = $img;
                }
                
                $originalPrice = rand(50, 1000);
                
                Product::create([
                    'user_id' => $seller->id,
                    'category_id' => $createdCategories[array_rand($createdCategories)]->id,
                    'department_id' => $department->id,
                    'name' => $faker->catchPhrase,
                    'description' => $faker->realText(150),
                    'images' => $prodImages,
                    'original_price' => $originalPrice,
                    'wholesale_price' => $seller->role == 'wholesale' ? $originalPrice * 0.7 : null,
                    'offer_price' => rand(0, 1) ? $originalPrice * 0.9 : null,
                    'sku' => Str::upper(Str::random(8)),
                    'quantity' => rand(10, 200),
                    'min_wholesale_qty' => $seller->role == 'wholesale' ? rand(5, 20) : null,
                    'warehouse_stock' => [$branch->id => rand(10, 200)],
                    'status' => 'active',
                    'is_free_shipping' => (bool)rand(0, 1),
                ]);
            }
            $this->command->info('تم إكمال التاجر رقم: ' . ($index + 1) . ' | الإيميل: ' . $seller->email . ' | الهاتف: ' . $seller->phone);
        }

        $this->command->info('تمت العملية بنجاح! كلمة المرور لجميع الحسابات هي: Password123');
    }
}
