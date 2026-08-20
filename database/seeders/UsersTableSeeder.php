<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        
        // 1.2 Vendor (بائع عادي)
        $vendor = User::create([
            'first_name' => 'أحمد',
            'last_name' => 'المالكي',
            'email' => 'vendor@example.com',
            'phone' => '0912345679',
            'password' => Hash::make('password'),
            'role' => 'vendor',
            'status' => 'approved',
            'store_name' => 'متجر المالكي للإلكترونيات',
            'store_description' => 'متجر متخصص في الإلكترونيات والأجهزة الذكية',
            'balance' => 5000,
            'email_verified_at' => now(),
            'detailed_address' => 'الرياض، حي النخيل، شارع الأمير عبدالعزيز',
            'store_email' => 'info@maliki-store.com',
            'social_links' => json_encode([
                'facebook' => 'https://facebook.com/maliki-store',
                'instagram' => 'https://instagram.com/maliki-store',
            ]),
        ]);

        // 1.3 Wholesale (تاجر جملة)
        $wholesale = User::create([
            'first_name' => 'محمد',
            'last_name' => 'الزهراني',
            'email' => 'wholesale@example.com',
            'phone' => '0912345680',
            'password' => Hash::make('password'),
            'role' => 'wholesale',
            'status' => 'approved',
            'store_name' => 'مؤسسة الزهراني للجملة',
            'store_description' => 'استيراد وتوزيع الجملة لجميع أنحاء المملكة',
            'balance' => 10000,
            'email_verified_at' => now(),
            'detailed_address' => 'جدة، حي البلد، شارع الملك عبدالعزيز',
            'store_email' => 'info@zahrani-wholesale.com',
            'commercial_registration_number' => 'CR-2024-001',
            'tax_number' => '3001234567',
            'social_links' => json_encode([
                'twitter' => 'https://twitter.com/zahrani-wholesale',
                'linkedin' => 'https://linkedin.com/company/zahrani-wholesale',
            ]),
        ]);

        // 1.4 Buyers (مشترين)
        $buyers = [
            [
                'first_name' => 'سارة',
                'last_name' => 'العتيبي',
                'email' => 'buyer1@example.com',
                'phone' => '0912345681',
                'balance' => 5000,
            ],
            [
                'first_name' => 'عبدالله',
                'last_name' => 'العمري',
                'email' => 'buyer2@example.com',
                'phone' => '0912345682',
                'balance' => 3000,
            ],
            [
                'first_name' => 'نورة',
                'last_name' => 'السعيد',
                'email' => 'buyer3@example.com',
                'phone' => '0912345683',
                'balance' => 2000,
            ],
        ];

        foreach ($buyers as $buyerData) {
            User::create([
                'first_name' => $buyerData['first_name'],
                'last_name' => $buyerData['last_name'],
                'email' => $buyerData['email'],
                'phone' => $buyerData['phone'],
                'password' => Hash::make('password'),
                'role' => 'buyer',
                'status' => 'approved',
                'balance' => $buyerData['balance'],
                'email_verified_at' => now(),
            ]);
        }

        $this->command->info('✅ تم إنشاء المستخدمين بنجاح!');
        $this->command->info('   Vendor: vendor@example.com / password');
        $this->command->info('   Wholesale: wholesale@example.com / password');
        $this->command->info('   Buyers: buyer1@example.com / password');
    }
}