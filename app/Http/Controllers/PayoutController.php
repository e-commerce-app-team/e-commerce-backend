<?php

namespace App\Http\Controllers;

use App\Models\PayoutRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayoutController extends Controller
{
    public function __construct(private WalletService $wallet)
    {
    }

    public function getBalance()
    {
        $user = auth()->user();
        $this->wallet->reconcileApprovedDeposits($user);
        return response()->json(array_merge($this->wallet->summary($user->fresh()), [
            'payout_method' => $user->payout_method,
            'payout_account' => $user->payout_account,
        ]));
    }

    public function requestWithdrawal(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payout_method' => 'nullable|string|max:50',
            'payout_account' => 'nullable|string|max:255',
        ]);
        $userId = auth()->id();
        $amount = round((float) $data['amount'], 2);

        try {
            return DB::transaction(function () use ($data, $userId, $amount) {
                $user = $this->wallet->lockUser($userId);
                $payout = PayoutRequest::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'payout_method' => $data['payout_method'] ?? 'manual',
                    'payout_account' => $data['payout_account'] ?? 'Manual',
                    'status' => 'pending',
                ]);
                $this->wallet->hold($user, $amount, [
                    'type' => 'withdrawal',
                    'status' => 'pending',
                    'reference' => "withdrawal:{$payout->id}:reservation",
                    'description' => 'Withdrawal amount reserved pending admin approval',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Withdrawal request submitted for admin approval.',
                    'details' => $payout,
                    'wallet' => $this->wallet->summary($user->fresh()),
                ], 201);
            });
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function instantWithdraw(Request $request)
    {
        return $this->requestWithdrawal($request);
    }

    public function payoutHistory()
    {
        return response()->json([
            'success' => true,
            'data' => auth()->user()->payoutRequests()->latest()->get(),
        ]);
    }
}
