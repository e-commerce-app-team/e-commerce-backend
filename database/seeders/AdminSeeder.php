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
        // إنشاء حساب أدمن افتراضي
        Admin::create([
            'first_name' => 'ahmad',
            'last_name' => 'mokdad',
            'email' => 'ahmadmk123@gmail.com', // الإيميل اللي رح تستخدميه باللوغن
            'password' => Hash::make('Admin@123'), // الباسورد مشفرة
            'profile_photo' => null // مسار افتراضي للصورة
        ]);
    }
}
