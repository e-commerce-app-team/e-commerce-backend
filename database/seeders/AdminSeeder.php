<?php

namespace Database\Seeders;

use App\Models\Admin;
use Hash;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ 1. الأدمن الرئيسي (Super Admin)
        Admin::updateOrCreate(
            ['phone' => '0911111111'],  // 🔍 ابحث بهذا الرقم
            [                           // 📝 إذا وجد، حدث البيانات، وإلا أنشئها
                'first_name' => 'Main',
                'last_name' => 'Admin',
                'password' => Hash::make('Admin@123'),
                'role' => 'super_admin',
            ]
        );

        // ✅ 2. أدمن المستخدمين
        Admin::updateOrCreate(
            ['phone' => '0922222222'],
            [
                'first_name' => 'Users',
                'last_name' => 'Manager',
                'password' => Hash::make('User@123'),
                'role' => 'users_admin',
            ]
        );

        // ✅ 3. أدمن الطلبات
        Admin::updateOrCreate(
            ['phone' => '0933333333'],
            [
                'first_name' => 'Orders',
                'last_name' => 'Manager',
                'password' => Hash::make('Order@123'),
                'role' => 'orders_admin',
            ]
        );

        // ✅ 4. أدمن المنتجات
        Admin::updateOrCreate(
            ['phone' => '0944444444'],
            [
                'first_name' => 'Products',
                'last_name' => 'Manager',
                'password' => Hash::make('Prod@123'),
                'role' => 'products_admin',
            ]
        );

        $this->command->info('✅ تم إنشاء/تحديث الأدمن بنجاح!');
        $this->command->info('   Super Admin: 0911111111 / Admin@123');
        $this->command->info('   Users Admin: 0922222222 / User@123');
        $this->command->info('   Orders Admin: 0933333333 / Order@123');
        $this->command->info('   Products Admin: 0944444444 / Prod@123');
    }
}