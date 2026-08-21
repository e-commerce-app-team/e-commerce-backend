<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * One backend price quote used by cart, checkout and order line snapshots.
 * Flutter may display this quote, but never supplies the payable amount.
 */
class PriceCalculationService
{
    public function quote(Product $product, int $quantity, ?ProductVariant $variant = null): array
    {
        $quantity = max(1, $quantity);
        $original = (float) ($variant?->price ?: $product->original_price);
        $candidates = [$original];

        $offerIsValid = $product->offer_price !== null
            && (float) $product->offer_price > 0
            && (! $product->offer_expires_at || $product->offer_expires_at->isFuture());
        if ($offerIsValid) {
            $candidates[] = (float) $product->offer_price;
        }

        $wholesaleMinimum = (int) ($product->min_wholesale_qty ?: 10);
        if ($product->wholesale_price !== null
            && (float) $product->wholesale_price > 0
            && $quantity >= $wholesaleMinimum) {
            $candidates[] = (float) $product->wholesale_price;
        }

        $baseUnitPrice = round(min($candidates), 2);
        $taxRate = (float) $product->effectiveTaxRate();
        $taxUnitPrice = round($baseUnitPrice * ($taxRate / 100), 2);
        $unitPrice = round($baseUnitPrice + $taxUnitPrice, 2);

        return [
            'base_unit_price' => $baseUnitPrice,
            'tax_rate' => $taxRate,
            'tax_unit_price' => $taxUnitPrice,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'base_subtotal' => round($baseUnitPrice * $quantity, 2),
            'tax_amount' => round($taxUnitPrice * $quantity, 2),
            'line_total' => round($unitPrice * $quantity, 2),
        ];
    }
}
