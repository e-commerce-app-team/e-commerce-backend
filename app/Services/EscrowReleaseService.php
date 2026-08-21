<?php

namespace App\Services;

use App\Models\SubOrder;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EscrowReleaseService
{
    public function __construct(
        private WalletService $wallet,
        private TaxService $taxService,
    ) {
    }

    /** Release exactly one seller escrow. Call inside a database transaction. */
    public function release(SubOrder $subOrder, string $confirmationType): array
    {
        $subOrder = SubOrder::with('order')->whereKey($subOrder->id)->lockForUpdate()->firstOrFail();
        if ($subOrder->escrow_released_at) {
            return ['released' => false, 'amount' => 0.0, 'already_released' => true];
        }
        if ($subOrder->status !== 'shipped' || (float) ($subOrder->escrow_amount ?? 0) <= 0) {
            throw new RuntimeException('This seller escrow is not eligible for release.');
        }

        $order = $subOrder->order;
        if (! $order || $order->payment_status !== 'paid_escrow') {
            throw new RuntimeException('The order escrow is not active.');
        }

        $amount = round((float) $subOrder->escrow_amount, 2);
        $buyer = User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
        $seller = User::whereKey($subOrder->seller_id)->lockForUpdate()->firstOrFail();
        $commission = $this->taxService->calculateCommission($amount, $seller->role, $order->id);

        $this->wallet->releaseLocked($buyer, $amount, [
            'order_id' => $order->id,
            'type' => 'escrow_release',
            'reference' => "suborder:{$subOrder->id}:escrow_release:buyer",
            'description' => "Escrow released from Buyer for SubOrder #{$subOrder->id}",
        ]);
        $this->wallet->credit($seller, $commission['net'], [
            'order_id' => $order->id,
            'type' => 'escrow_release',
            'reference' => "suborder:{$subOrder->id}:seller:release",
            'description' => "Escrow released to Seller for SubOrder #{$subOrder->id}",
        ]);
        $this->wallet->record([
            'user_id' => $seller->id,
            'order_id' => $order->id,
            'type' => 'commission',
            'amount' => $commission['commission'],
            'direction' => 'debit',
            'reference' => "suborder:{$subOrder->id}:seller:commission",
            'description' => "Platform commission for SubOrder #{$subOrder->id}",
        ]);
        $this->wallet->record([
            'user_id' => null,
            'account_type' => 'platform',
            'order_id' => $order->id,
            'type' => 'commission',
            'amount' => $commission['commission'],
            'direction' => 'credit',
            'reference' => "suborder:{$subOrder->id}:platform_commission",
            'description' => "Platform commission from SubOrder #{$subOrder->id}",
        ]);

        $now = now();
        $subOrder->update([
            'status' => 'delivered',
            'shipment_state' => 'delivered',
            'delivery_confirmed_at' => $now,
            'delivery_confirmation_type' => $confirmationType,
            'escrow_released_at' => $now,
            'platform_commission' => $commission['commission'],
            'commission_rate_snapshot' => $commission['rate'],
            'seller_net_amount' => $commission['net'],
        ]);

        return [
            'released' => true,
            'amount' => $amount,
            'commission' => (float) $commission['commission'],
            'seller_net' => (float) $commission['net'],
        ];
    }
}
