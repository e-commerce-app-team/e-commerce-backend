<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletDepositRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Single source of truth for the simulated wallet ledger.
 *
 * `users.balance` is the available balance and `users.locked_balance` is the
 * reserved balance. All callers must invoke these methods from a database
 * transaction when they are part of a larger business operation.
 */
class WalletService
{
    public const INITIAL_BALANCE = 1000000.00;

    public function summary(User $user): array
    {
        $available = round((float) ($user->balance ?? 0), 2);
        $locked = round((float) ($user->locked_balance ?? 0), 2);

        return [
            'total_balance' => round($available + $locked, 2),
            'available_balance' => $available,
            'locked_balance' => $locked,
            'commission' => round((float) Transaction::query()
                ->where('user_id', $user->id)
                ->where('type', 'commission')
                ->where('direction', 'debit')
                ->where('status', 'completed')
                ->sum('amount'), 2),
            'incoming' => round((float) Transaction::query()
                ->where('user_id', $user->id)
                ->where('direction', 'credit')
                ->where('status', 'completed')
                ->sum('amount'), 2),
            'outgoing' => round((float) Transaction::query()
                ->where('user_id', $user->id)
                ->where('direction', 'debit')
                ->where('status', 'completed')
                ->sum('amount'), 2),
            'withdrawn' => round((float) Transaction::query()
                ->where('user_id', $user->id)
                ->where('type', 'withdrawal')
                ->where('direction', 'debit')
                ->where('status', 'completed')
                ->sum('amount'), 2),
        ];
    }

    /**
     * Finalize legacy/admin-approved deposits that were marked `approved`
     * without passing through the approval endpoint. The unique ledger
     * reference keeps this reconciliation idempotent.
     */
    public function reconcileApprovedDeposits(User $user): void
    {
        DB::transaction(function () use ($user) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $deposits = WalletDepositRequest::query()
                ->where('user_id', $lockedUser->id)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->get();

            foreach ($deposits as $deposit) {
                $reference = "deposit:{$deposit->id}";
                if (! Transaction::query()->where('reference', $reference)->exists()) {
                    $this->credit($lockedUser, (float) $deposit->amount, [
                        'type' => 'deposit',
                        'reference' => $reference,
                        'description' => "Approved deposit request #{$deposit->id}",
                    ]);
                }
                $deposit->update(['status' => 'completed', 'reviewed_at' => $deposit->reviewed_at ?: now()]);
            }
        });
    }

    /** Initialize a newly-created buyer/vendor/wholesale account exactly once. */
    public function initializeNewUser(User $user): void
    {
        if (! in_array($user->role, ['buyer', 'vendor', 'wholesale'], true)) {
            return;
        }

        $reference = 'initial_balance:' . $user->id;
        if (Transaction::query()->where('reference', $reference)->exists()) {
            return;
        }

        $user->update([
            'balance' => self::INITIAL_BALANCE,
            'locked_balance' => 0,
            'wallet_qr_token' => $user->wallet_qr_token ?: (string) Str::uuid(),
        ]);

        $this->record([
            'user_id' => $user->id,
            'type' => 'initial_balance',
            'amount' => self::INITIAL_BALANCE,
            'direction' => 'credit',
            'status' => 'completed',
            'reference' => $reference,
            'description' => 'Initial simulated wallet balance',
        ]);
    }

    public function ensureQrToken(User $user): string
    {
        if (! $user->wallet_qr_token) {
            $user->forceFill(['wallet_qr_token' => (string) Str::uuid()])->save();
        }

        return $user->wallet_qr_token;
    }

    public function credit(User $user, float $amount, array $ledger): Transaction
    {
        $amount = $this->positiveAmount($amount);
        $user->balance = round((float) $user->balance + $amount, 2);
        $user->save();

        return $this->record(array_merge($ledger, [
            'user_id' => $ledger['user_id'] ?? $user->id,
            'amount' => $amount,
            'direction' => 'credit',
            'status' => $ledger['status'] ?? 'completed',
        ]));
    }

    public function debitAvailable(User $user, float $amount, array $ledger): Transaction
    {
        $amount = $this->positiveAmount($amount);
        if ((float) $user->balance < $amount) {
            throw new RuntimeException('Insufficient available wallet balance.');
        }

        $user->balance = round((float) $user->balance - $amount, 2);
        $user->save();

        return $this->record(array_merge($ledger, [
            'user_id' => $ledger['user_id'] ?? $user->id,
            'amount' => $amount,
            'direction' => 'debit',
            'status' => $ledger['status'] ?? 'completed',
        ]));
    }

    /** Move money from available to locked without changing total balance. */
    public function hold(User $user, float $amount, array $ledger): Transaction
    {
        $amount = $this->positiveAmount($amount);
        if ((float) $user->balance < $amount) {
            throw new RuntimeException('Insufficient available wallet balance.');
        }

        $user->balance = round((float) $user->balance - $amount, 2);
        $user->locked_balance = round((float) $user->locked_balance + $amount, 2);
        $user->save();

        return $this->record(array_merge($ledger, [
            'user_id' => $ledger['user_id'] ?? $user->id,
            'amount' => $amount,
            'direction' => 'debit',
            'status' => $ledger['status'] ?? 'completed',
        ]));
    }

    /** Release locked money out of the user's wallet, optionally crediting a different user separately. */
    public function releaseLocked(User $user, float $amount, array $ledger): Transaction
    {
        $amount = $this->positiveAmount($amount);
        if ((float) $user->locked_balance < $amount) {
            throw new RuntimeException('Insufficient locked wallet balance.');
        }

        $user->locked_balance = round((float) $user->locked_balance - $amount, 2);
        $user->save();

        return $this->record(array_merge($ledger, [
            'user_id' => $ledger['user_id'] ?? $user->id,
            'amount' => $amount,
            'direction' => 'debit',
            'status' => $ledger['status'] ?? 'completed',
        ]));
    }

    /** Return a locked amount to available balance, used for a real refund/rejected withdrawal. */
    public function unlockToAvailable(User $user, float $amount, array $ledger): Transaction
    {
        $amount = $this->positiveAmount($amount);
        if ((float) $user->locked_balance < $amount) {
            throw new RuntimeException('Insufficient locked wallet balance.');
        }

        $user->locked_balance = round((float) $user->locked_balance - $amount, 2);
        $user->balance = round((float) $user->balance + $amount, 2);
        $user->save();

        return $this->record(array_merge($ledger, [
            'user_id' => $ledger['user_id'] ?? $user->id,
            'amount' => $amount,
            'direction' => 'credit',
            'status' => $ledger['status'] ?? 'completed',
        ]));
    }

    public function record(array $attributes): Transaction
    {
        $attributes['amount'] = $this->positiveAmount((float) ($attributes['amount'] ?? 0));
        $attributes['direction'] = $attributes['direction'] ?? 'credit';
        $attributes['status'] = $attributes['status'] ?? 'completed';
        $attributes['reference'] = $attributes['reference'] ?? (string) Str::uuid();
        $attributes['account_type'] = $attributes['account_type'] ?? 'user';

        return Transaction::firstOrCreate(
            ['reference' => $attributes['reference']],
            $attributes,
        );
    }

    public function lockUser(int $id): User
    {
        return User::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function positiveAmount(float $amount): float
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        return $amount;
    }
}
