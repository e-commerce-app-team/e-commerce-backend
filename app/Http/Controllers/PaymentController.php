<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SubOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PushNotificationService;
use App\Services\PriceCalculationService;
use App\Services\TaxService;
use App\Services\WalletService;
use App\Services\EscrowReleaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private WalletService $wallet,
        private PriceCalculationService $prices,
        private EscrowReleaseService $escrowRelease,
    )
    {
    }

    public function getWalletBalance()
    {
        $user = auth()->user();
        $this->wallet->reconcileApprovedDeposits($user);
        return response()->json($this->wallet->summary($user->fresh()));
    }

    public function requestDeposit(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:255',
        ]);

        $deposit = auth()->user()->walletDepositRequests()->create([
            'amount' => round((float) $data['amount'], 2),
            'payment_method' => $data['payment_method'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json(['success' => true, 'message' => 'Deposit request submitted for admin approval.', 'data' => $deposit], 201);
    }

    public function depositRequests()
    {
        return response()->json(['success' => true, 'data' => auth()->user()->walletDepositRequests()->latest()->get()]);
    }

    public function getTransactionHistory()
    {
        $transactions = auth()->user()->transactions()->with('order:id,status,created_at')->latest()->paginate(100);
        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => ['current_page' => $transactions->currentPage(), 'last_page' => $transactions->lastPage(), 'total' => $transactions->total()],
        ]);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'recipient_token' => 'required|string|max:120',
            'amount' => 'required|numeric|min:0.01',
            'idempotency_key' => 'nullable|string|max:100',
        ]);
        $senderId = auth()->id();
        $amount = round((float) $data['amount'], 2);
        $key = $data['idempotency_key'] ?? (string) Str::uuid();
        $outReference = "transfer:{$senderId}:{$key}:out";

        try {
            return DB::transaction(function () use ($data, $senderId, $amount, $key, $outReference) {
                $sender = $this->wallet->lockUser($senderId);
                $existing = Transaction::where('reference', $outReference)->first();
                if ($existing) {
                    return response()->json(['success' => true, 'message' => 'Transfer already processed.', 'duplicate' => true, 'data' => $existing]);
                }
                $recipient = User::where('wallet_qr_token', $data['recipient_token'])
                    ->whereIn('role', ['buyer', 'vendor', 'wholesale'])->lockForUpdate()->first();
                if (! $recipient) {
                    throw ValidationException::withMessages(['recipient_token' => 'Recipient wallet was not found.']);
                }
                if ($recipient->id === $sender->id) {
                    throw ValidationException::withMessages(['recipient_token' => 'You cannot transfer money to yourself.']);
                }

                $this->wallet->debitAvailable($sender, $amount, [
                    'type' => 'transfer_out', 'counterparty_user_id' => $recipient->id,
                    'reference' => $outReference, 'description' => "Transfer to {$recipient->first_name} {$recipient->last_name}",
                ]);
                $this->wallet->credit($recipient, $amount, [
                    'type' => 'transfer_in', 'counterparty_user_id' => $sender->id,
                    'reference' => "transfer:{$senderId}:{$key}:in", 'description' => "Transfer from {$sender->first_name} {$sender->last_name}",
                ]);

                return response()->json([
                    'success' => true, 'message' => 'Transfer completed successfully.',
                    'recipient' => $recipient->only(['id', 'first_name', 'last_name', 'store_name']),
                    'wallet' => $this->wallet->summary($sender->fresh()),
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function recipients(Request $request)
    {
        $query = trim((string) $request->query('query', ''));
        if (mb_strlen($query) < 2) return response()->json(['success' => true, 'data' => []]);
        $users = User::whereIn('role', ['buyer', 'vendor', 'wholesale'])
            ->where(fn ($q) => $q->where('first_name', 'like', "%{$query}%")->orWhere('last_name', 'like', "%{$query}%")->orWhere('phone', 'like', "%{$query}%")->orWhere('email', 'like', "%{$query}%"))
            ->limit(20)->get(['id', 'first_name', 'last_name', 'store_name', 'wallet_qr_token'])
            ->map(fn (User $user) => ['id' => $user->id, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'store_name' => $user->store_name, 'wallet_token' => $user->wallet_qr_token]);
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function myWalletQr()
    {
        $token = $this->wallet->ensureQrToken(auth()->user());
        return response()->json([
            'success' => true, 'type' => 'wallet',
            // Wallet QR contains only the opaque wallet token. The backend
            // still determines the wallet and never trusts a client amount.
            'payload' => $token,
        ]);
    }

    public function resolveQr(Request $request)
    {
        $request->validate(['payload' => 'required|string|max:1000']);
        $rawPayload = trim($request->string('payload')->toString());
        $payload = json_decode($rawPayload, true);
        if (is_array($payload) && ($payload['type'] ?? null) === 'wallet') {
            $payload = ['type' => 'wallet', 'token' => $payload['token'] ?? null];
        } elseif (preg_match('/^[0-9a-fA-F-]{36}$/', $rawPayload)) {
            // Backward-compatible support for the new compact Wallet QR.
            $payload = ['type' => 'wallet', 'token' => $rawPayload];
        }
        if (! is_array($payload) || empty($payload['type']) || empty($payload['token'])) {
            return response()->json(['success' => false, 'message' => 'Invalid QR code.'], 422);
        }

        if ($payload['type'] === 'wallet') {
            $user = User::where('wallet_qr_token', $payload['token'])->first();
            if (! $user || ! in_array($user->role, ['buyer', 'vendor', 'wholesale'], true)) {
                return response()->json(['success' => false, 'message' => 'Wallet QR code is not valid.'], 404);
            }
            return response()->json([
                'success' => true, 'type' => 'wallet',
                'recipient' => ['id' => $user->id, 'name' => trim($user->first_name . ' ' . $user->last_name), 'store_name' => $user->store_name, 'wallet_token' => $user->wallet_qr_token],
            ]);
        }

        if ($payload['type'] === 'order_payment') {
            $order = Order::with(['seller', 'subOrders.seller'])->where('payment_qr_token', $payload['token'])->first();
            if (! $order) return response()->json(['success' => false, 'message' => 'Order payment QR code is not valid.'], 404);
            if ($order->payment_status !== 'unpaid') return response()->json(['success' => false, 'message' => 'This order has already been paid or closed.'], 409);
            $seller = $order->seller ?: $order->subOrders->first()?->seller;
            return response()->json([
                'success' => true, 'type' => 'order_payment',
                'order' => ['id' => $order->id, 'number' => '#' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT), 'amount' => (float) $order->total_price, 'payment_status' => $order->payment_status, 'store_name' => $seller?->store_name ?: trim(($seller?->first_name ?? '') . ' ' . ($seller?->last_name ?? ''))],
            ]);
        }
        return response()->json(['success' => false, 'message' => 'Unsupported QR type.'], 422);
    }

    public function generateOrderPaymentQr($orderId)
    {
        $order = Order::with('subOrders')->findOrFail($orderId);
        $allowed = (int) $order->seller_id === (int) auth()->id() || $order->subOrders->contains(fn ($sub) => (int) $sub->seller_id === (int) auth()->id());
        abort_unless($allowed, 403);
        if ($order->payment_status !== 'unpaid') return response()->json(['success' => false, 'message' => 'Only unpaid orders can have a payment QR.'], 409);
        $order->payment_qr_token = $order->payment_qr_token ?: (string) Str::uuid();
        $order->save();
        return response()->json(['success' => true, 'type' => 'order_payment', 'payload' => json_encode(['type' => 'order_payment', 'token' => $order->payment_qr_token], JSON_UNESCAPED_SLASHES), 'order_id' => $order->id]);
    }

    public function generateSubOrderPaymentQr($subOrderId)
    {
        $subOrder = SubOrder::whereKey($subOrderId)->firstOrFail();
        abort_unless((int) $subOrder->seller_id === (int) auth()->id(), 403);
        return $this->generateOrderPaymentQr($subOrder->order_id);
    }

    public function payAndTransfer(Request $request, $orderId)
    {
        $request->validate(['password' => 'nullable|string']);
        $buyerId = auth()->id();
        try {
            return DB::transaction(function () use ($request, $orderId, $buyerId) {
                $buyer = $this->wallet->lockUser($buyerId);
                if ($buyer->role !== 'buyer') abort(403, 'Only buyers can pay orders.');
                if ($request->filled('password') && ! \Hash::check($request->password, $buyer->password)) return response()->json(['success' => false, 'message' => 'Incorrect password.'], 401);
                $order = Order::with(['subOrders.seller', 'subOrders.items', 'products'])
                    ->whereKey($orderId)->where('user_id', $buyer->id)->lockForUpdate()->first();
                if (! $order) return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
                if ($order->payment_status !== 'unpaid') return response()->json(['success' => false, 'message' => 'Order payment has already been processed.'], 409);
                if ($order->shipping_pending || $order->subOrders->contains(fn ($sub) => $sub->shipping_cost === null || ! $sub->shipping_approved)) {
                    return response()->json(['success' => false, 'message' => 'All shipping quotes must be resolved and approved before payment.'], 422);
                }

                foreach ($order->subOrders as $subOrder) {
                    foreach ($subOrder->items as $lineItem) {
                        $product = \App\Models\Product::whereKey($lineItem->product_id)->lockForUpdate()->first();
                        $variant = $lineItem->variant_id
                            ? \App\Models\ProductVariant::whereKey($lineItem->variant_id)->lockForUpdate()->first()
                            : null;
                        if (! $product || $product->status !== 'active') {
                            throw new RuntimeException('A product in this order is no longer available.');
                        }
                        $quote = $this->prices->quote($product, (int) $lineItem->quantity, $variant);
                        if (abs((float) $lineItem->unit_price - (float) $quote['unit_price']) > 0.009) {
                            throw new RuntimeException('A product price changed. Please review the order before paying.');
                        }
                    }
                }

                $amount = round((float) $order->total_price, 2);
                $this->wallet->hold($buyer, $amount, ['order_id' => $order->id, 'type' => 'escrow_hold', 'reference' => "order:{$order->id}:escrow_hold", 'description' => "Funds held in escrow for Order #{$order->id}"]);

                // Legacy seller-created orders do not reserve stock until the
                // buyer actually pays. Cart checkout already marks this flag
                // and therefore never decrements stock twice.
                if (! $order->stock_reserved) {
                    $lineItems = $order->subOrders->flatMap(fn ($sub) => $sub->items);
                    if ($lineItems->isNotEmpty()) {
                        foreach ($lineItems as $lineItem) {
                            $stock = $lineItem->variant_id
                                ? \App\Models\ProductVariant::whereKey($lineItem->variant_id)->lockForUpdate()->first()
                                : \App\Models\Product::whereKey($lineItem->product_id)->lockForUpdate()->first();
                            if (! $stock || (int) $stock->quantity < (int) $lineItem->quantity) {
                                throw new RuntimeException('Insufficient stock for a product in this order.');
                            }
                            $stock->decrement('quantity', (int) $lineItem->quantity);
                            $product = \App\Models\Product::whereKey($lineItem->product_id)->lockForUpdate()->first();
                            $product?->increment('sales_count', (int) $lineItem->quantity);
                        }
                    } else {
                        foreach ($order->products as $product) {
                            $product = \App\Models\Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                            $quantity = (int) $product->pivot->quantity;
                            if ((int) $product->quantity < $quantity) {
                                throw new RuntimeException('Insufficient stock for a product in this order.');
                            }
                            $product->decrement('quantity', $quantity);
                            $product->increment('sales_count', $quantity);
                        }
                    }
                }
                $timeline = $order->status_timeline ?? [];
                $timeline[] = ['status' => 'paid_escrow', 'title' => 'Buyer payment received and held in escrow.', 'time' => now()->toDateTimeString()];
                $order->update(['payment_status' => 'paid_escrow', 'stock_reserved' => true, 'status_timeline' => $timeline]);
                foreach ($order->subOrders as $subOrder) {
                    $subOrder->update(['escrow_amount' => (float) $subOrder->total]);
                }

                app(PushNotificationService::class)->sendToUser($buyer->fresh(), 'Order Payment Confirmed', "Your payment for order #{$order->id} is held safely in escrow.", ['type' => 'order_confirmed', 'order_id' => (string) $order->id]);
                return response()->json(['success' => true, 'message' => 'Payment successful. Funds are locked in escrow until delivery confirmation.', 'wallet' => $this->wallet->summary($buyer->fresh()), 'order_id' => $order->id, 'order_number' => '#' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT), 'order_status' => $order->status, 'payment_status' => 'paid_escrow']);
            });
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function confirmDelivery(Request $request, $orderId)
    {
        $data = $request->validate(['sub_order_id' => 'nullable|integer']);
        $buyerId = auth()->id();
        try {
            return DB::transaction(function () use ($data, $orderId, $buyerId) {
                $buyer = $this->wallet->lockUser($buyerId);
                $order = Order::whereKey($orderId)->where('user_id', $buyer->id)->lockForUpdate()->first();
                if (! $order) return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
                if ($order->payment_status !== 'paid_escrow') return response()->json(['success' => false, 'message' => 'This escrow is already released or is not paid.'], 409);
                $subOrdersQuery = $order->subOrders()->lockForUpdate();
                if (! empty($data['sub_order_id'])) {
                    $subOrdersQuery->whereKey($data['sub_order_id']);
                }
                $subOrders = $subOrdersQuery->get();
                if ($subOrders->isEmpty()) {
                    return response()->json(['success' => false, 'message' => 'Seller sub-order not found.'], 404);
                }
                $eligible = $subOrders->filter(fn ($sub) => $sub->status === 'shipped' && ! $sub->escrow_released_at && (float) ($sub->escrow_amount ?? 0) > 0);
                if ($eligible->isEmpty()) return response()->json(['success' => false, 'message' => 'No shipped seller order is waiting for delivery confirmation.'], 422);
                if (empty($data['sub_order_id']) && $eligible->count() > 1) {
                    return response()->json(['success' => false, 'message' => 'Confirm delivery for each seller order separately.'], 422);
                }

                $released = 0.0;
                foreach ($eligible as $subOrder) {
                    $result = $this->escrowRelease->release($subOrder, 'delivery_confirmed_by_buyer');
                    $released = round($released + (float) ($result['amount'] ?? 0), 2);
                }

                $freshSubOrders = $order->subOrders()->get();
                $allDelivered = $freshSubOrders->isNotEmpty() && $freshSubOrders->every(fn ($sub) => $sub->status === 'delivered');
                $anyShipped = $freshSubOrders->contains(fn ($sub) => $sub->status === 'shipped');
                $anyProcessing = $freshSubOrders->contains(fn ($sub) => in_array($sub->status, ['pending', 'processing'], true));
                $orderStatus = $allDelivered ? 'delivered' : ($anyShipped ? 'shipped' : ($anyProcessing ? 'processing' : 'pending'));
                $allReleased = $freshSubOrders->isNotEmpty() && $freshSubOrders->every(fn ($sub) => (bool) $sub->escrow_released_at);
                $timeline = $order->status_timeline ?? [];
                $timeline[] = ['status' => 'delivery_confirmed_by_buyer', 'title' => 'delivery_confirmed_by_buyer', 'time' => now()->toDateTimeString()];
                $order->update([
                    'status' => $orderStatus,
                    'payment_status' => $allReleased ? 'released' : 'paid_escrow',
                    'delivered_at' => $allDelivered ? now() : null,
                    'status_timeline' => $timeline,
                ]);
                return response()->json(['success' => true, 'message' => 'Delivery confirmed and eligible seller escrow was released.', 'order_status' => $orderStatus, 'payment_status' => $allReleased ? 'released' : 'paid_escrow', 'released_amount' => $released, 'wallet' => $this->wallet->summary($buyer->fresh())]);
            });
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
