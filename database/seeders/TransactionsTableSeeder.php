<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Order;
use Illuminate\Database\Seeder;

class TransactionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $buyer = User::where('role', 'buyer')->first();
        $seller = User::where('role', 'vendor')->first();
        $order = Order::first();

        $transactions = [
            // معاملات المشتري
            [
                'user_id' => $buyer->id,
                'order_id' => $order?->id,
                'type' => 'payment',
                'amount' => 3150,
                'description' => 'دفع قيمة الطلب #' . ($order?->id ?? 1),
                'created_at' => now()->subDays(2),
            ],
            [
                'user_id' => $buyer->id,
                'order_id' => null,
                'type' => 'deposit',
                'amount' => 5000,
                'description' => 'إيداع في المحفظة',
                'created_at' => now()->subDays(5),
            ],
            // معاملات البائع
            [
                'user_id' => $seller->id,
                'order_id' => $order?->id,
                'type' => 'deposit',
                'amount' => 2835,
                'description' => 'أرباح من الطلب #' . ($order?->id ?? 1) . ' (بعد خصم العمولة)',
                'created_at' => now()->subDays(1),
            ],
            [
                'user_id' => $seller->id,
                'order_id' => null,
                'type' => 'withdrawal',
                'amount' => 1000,
                'description' => 'سحب من المحفظة',
                'created_at' => now()->subDays(3),
            ],
        ];

        foreach ($transactions as $transaction) {
            Transaction::create($transaction);
        }

        $this->command->info('✅ تم إنشاء المعاملات بنجاح!');
    }
}