<?php

namespace App\Services;

use App\Models\User;

class ShippingService
{
    /**
     * Build shipping options for a seller cart subtotal.
     */
    public function getOptionsForSeller(User $seller, float $subtotal, bool $hasFreeShippingProduct = false): array
    {
        $settings = $seller->shipping_settings ?? [];
        $baseFee  = (float) ($settings['base_fee'] ?? 5000);
        $express  = (float) ($settings['express_fee'] ?? ($baseFee * 1.8));
        $threshold = (float) ($settings['free_threshold'] ?? 0);
        $whoPays  = $settings['who_pays'] ?? 'buyer';

        $freeByThreshold = $threshold > 0 && $subtotal >= $threshold;
        $freeByProduct   = $hasFreeShippingProduct;
        $freeByCoupon    = false;

        $standardCost = ($whoPays === 'seller' || $freeByThreshold || $freeByProduct)
            ? 0.0
            : $baseFee;

        $options = [
            [
                'id'                  => 'standard',
                'name'                => 'Standard Delivery',
                'name_ar'             => 'توصيل عادي',
                'cost'                => round($standardCost, 2),
                'estimated_delivery'  => '2-4 days',
                'estimated_delivery_ar' => '2-4 أيام',
            ],
            [
                'id'                  => 'express',
                'name'                => 'Express Delivery',
                'name_ar'             => 'توصيل سريع',
                'cost'                => round($standardCost > 0 ? $express : 0, 2),
                'estimated_delivery'  => '1-2 days',
                'estimated_delivery_ar' => '1-2 أيام',
            ],
        ];

        if ($seller->pickup_enabled ?? true) {
            $options[] = [
                'id'                  => 'pickup',
                'name'                => 'Self Pickup',
                'name_ar'             => 'استلام شخصي',
                'cost'                => 0.0,
                'estimated_delivery'  => 'Same day',
                'estimated_delivery_ar' => 'نفس اليوم',
            ];
        }

        return $options;
    }

    public function resolveOption(array $options, string $optionId): ?array
    {
        foreach ($options as $option) {
            if ($option['id'] === $optionId) {
                return $option;
            }
        }
        return null;
    }
}
