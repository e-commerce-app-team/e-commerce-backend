<?php

namespace Database\Seeders;

use App\Models\Admin;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. الأدمن الرئيسي (Super Admin)
        Admin::create([
            'first_name' => 'Main',
            'last_name' => 'Admin',
            'phone' => '0911111111',
            'password' => Hash::make('Admin@123'),
            'role' => 'super_admin',
        ]);

        // 2. أدمن المستخدمين
        Admin::create([
            'first_name' => 'Users',
            'last_name' => 'Manager',
            'phone' => '0922222222',
            'password' => Hash::make('User@123'),
            'role' => 'users_admin',
        ]);

        // 3. أدمن الطلبات
        Admin::create([
            'first_name' => 'Orders',
            'last_name' => 'Manager',
            'phone' => '0933333333',
            'password' => Hash::make('Order@123'),
            'role' => 'orders_admin',
        ]);

        // 4. أدمن المنتجات
        Admin::create([
            'first_name' => 'Products',
            'last_name' => 'Manager',
            'phone' => '0944444444',
            'password' => Hash::make('Prod@123'),
            'role' => 'products_admin',
        ]);
    }
}
