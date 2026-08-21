<?php

namespace App\Services;

use App\Models\User;

class ShippingService
{
    /** Return enabled methods. Standard/express are quoted after seller acceptance. */
    public function getOptionsForSeller(User $seller, float $subtotal = 0, bool $hasFreeShippingProduct = false): array
    {
        $settings = $seller->shipping_settings ?? [];
        $delivery = is_array($settings['delivery_options'] ?? null)
            ? $settings['delivery_options']
            : [];
        $standard = array_key_exists('standard', $delivery) ? (bool) $delivery['standard'] : true;
        $express = array_key_exists('express', $delivery) ? (bool) $delivery['express'] : false;
        $pickup = array_key_exists('pickup', $delivery)
            ? (bool) $delivery['pickup']
            : (bool) ($seller->pickup_enabled ?? true);

        $options = [];
        if ($standard) {
            $options[] = $this->pendingOption(
                'standard', 'Standard Delivery', 'توصيل عادي',
                '2-4 days', '2-4 أيام'
            );
        }
        if ($express) {
            $options[] = $this->pendingOption(
                'express', 'Express Delivery', 'توصيل سريع',
                '1-2 days', '1-2 أيام'
            );
        }
        if ($pickup && $this->hasMainStoreLocation($seller)) {
            $options[] = [
                'id' => 'pickup',
                'name' => 'Self Pickup',
                'name_ar' => 'استلام شخصي',
                'cost' => 0.0,
                'cost_pending' => false,
                'estimated_delivery' => 'Same day',
                'estimated_delivery_ar' => 'في نفس اليوم',
                'store_address' => $seller->detailed_address,
                'store_latitude' => $seller->latitude,
                'store_longitude' => $seller->longitude,
            ];
        }

        return $options;
    }

    private function pendingOption(string $id, string $name, string $nameAr, string $eta, string $etaAr): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'name_ar' => $nameAr,
            'cost' => null,
            'cost_pending' => true,
            'estimated_delivery' => null,
            'estimated_delivery_ar' => null,
            'eta_hint' => $eta,
            'eta_hint_ar' => $etaAr,
        ];
    }

    public function hasMainStoreLocation(User $seller): bool
    {
        return is_numeric($seller->latitude)
            && is_numeric($seller->longitude)
            && trim((string) $seller->detailed_address) !== '';
    }

    public function resolveOption(array $options, string $optionId): ?array
    {
        foreach ($options as $option) {
            if (($option['id'] ?? null) === $optionId) return $option;
        }
        return null;
    }

    public function optionIsEnabled(User $seller, string $optionId): bool
    {
        return $this->resolveOption($this->getOptionsForSeller($seller), $optionId) !== null;
    }
}
