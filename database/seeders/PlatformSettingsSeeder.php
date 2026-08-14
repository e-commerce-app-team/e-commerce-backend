<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key'   => 'vendor_commission_rate',
                'value' => '10.00',
                'type'  => 'decimal',
                'label' => 'عمولة التاجر العادي (Vendor) %',
            ],
            [
                'key'   => 'wholesale_commission_rate',
                'value' => '5.00',
                'type'  => 'decimal',
                'label' => 'عمولة تاجر الجملة (Wholesale) %',
            ],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
