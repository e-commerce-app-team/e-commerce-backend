<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrdersTableSeeder extends Seeder
{
    public function run(): void
    {
        $buyer = User::where('role', 'buyer')->first();
        $vendor = User::where('role', 'vendor')->first();
        $wholesale = User::where('role', 'wholesale')->first();
        $product = Product::first();

        $orders = [
            // طلب مكتمل - Vendor
            [
                'user_id' => $buyer->id,
                'seller_id' => $vendor->id,
                'total_price' => 3150,
                'subtotal_before_tax' => 3000,
                'tax_amount' => 150,
                'status' => 'delivered',
                'payment_status' => 'released',
                'payment_method' => 'wallet',
                'shipping_address_title' => 'المنزل',
                'shipping_address_details' => 'الرياض، حي النخيل، شارع الأمير عبدالعزيز، بناء 10',
                'customer_notes' => 'يرجى الاتصال قبل التسليم بنصف ساعة',
                'discount_amount' => 0,
                'commission_rate_snapshot' => 10,
                'status_timeline' => [
                    ['status' => 'pending', 'title' => 'تم استلام الطلب', 'time' => now()->subDays(5)->toDateTimeString()],
                    ['status' => 'processing', 'title' => 'جاري التجهيز', 'time' => now()->subDays(4)->toDateTimeString()],
                    ['status' => 'shipped', 'title' => 'تم الشحن', 'time' => now()->subDays(3)->toDateTimeString()],
                    ['status' => 'delivered', 'title' => 'تم التسليم', 'time' => now()->subDays(1)->toDateTimeString()],
                ],
                'shipped_at' => now()->subDays(3),
                'delivered_at' => now()->subDays(1),
                'created_at' => now()->subDays(5),
            ],
            // طلب قيد التجهيز - Vendor
            [
                'user_id' => $buyer->id,
                'seller_id' => $vendor->id,
                'total_price' => 1200,
                'subtotal_before_tax' => 1142.86,
                'tax_amount' => 57.14,
                'status' => 'processing',
                'payment_status' => 'paid_escrow',
                'payment_method' => 'wallet',
                'shipping_address_title' => 'العمل',
                'shipping_address_details' => 'جدة، طريق المدينة، برج الأعمال، الطابق 5',
                'customer_notes' => null,
                'discount_amount' => 0,
                'commission_rate_snapshot' => 10,
                'status_timeline' => [
                    ['status' => 'pending', 'title' => 'تم استلام الطلب', 'time' => now()->subDays(2)->toDateTimeString()],
                    ['status' => 'processing', 'title' => 'جاري التجهيز', 'time' => now()->subDays(1)->toDateTimeString()],
                ],
                'shipped_at' => null,
                'delivered_at' => null,
                'created_at' => now()->subDays(2),
            ],
            // طلب قيد الانتظار - Vendor
            [
                'user_id' => $buyer->id,
                'seller_id' => $vendor->id,
                'total_price' => 2500,
                'subtotal_before_tax' => 2380.95,
                'tax_amount' => 119.05,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => 'wallet',
                'shipping_address_title' => 'المنزل',
                'shipping_address_details' => 'الدمام، حي الخليج، شارع الملك فهد، بناء 5',
                'customer_notes' => 'التوصيل في المساء بعد الساعة 6',
                'discount_amount' => 0,
                'commission_rate_snapshot' => 10,
                'status_timeline' => [
                    ['status' => 'pending', 'title' => 'تم استلام الطلب', 'time' => now()->subHours(2)->toDateTimeString()],
                ],
                'shipped_at' => null,
                'delivered_at' => null,
                'created_at' => now()->subHours(2),
            ],
            // طلب مكتمل - Wholesale
            [
                'user_id' => $buyer->id,
                'seller_id' => $wholesale->id,
                'total_price' => 5000,
                'subtotal_before_tax' => 4761.90,
                'tax_amount' => 238.10,
                'status' => 'delivered',
                'payment_status' => 'released',
                'payment_method' => 'wallet',
                'shipping_address_title' => 'المستودع',
                'shipping_address_details' => 'جدة، المنطقة الصناعية، مستودع 12',
                'customer_notes' => 'شحن سريع',
                'discount_amount' => 0,
                'commission_rate_snapshot' => 5,
                'status_timeline' => [
                    ['status' => 'pending', 'title' => 'تم استلام الطلب', 'time' => now()->subDays(7)->toDateTimeString()],
                    ['status' => 'processing', 'title' => 'جاري التجهيز', 'time' => now()->subDays(6)->toDateTimeString()],
                    ['status' => 'shipped', 'title' => 'تم الشحن', 'time' => now()->subDays(5)->toDateTimeString()],
                    ['status' => 'delivered', 'title' => 'تم التسليم', 'time' => now()->subDays(3)->toDateTimeString()],
                ],
                'shipped_at' => now()->subDays(5),
                'delivered_at' => now()->subDays(3),
                'created_at' => now()->subDays(7),
            ],
            // طلب ملغي
            [
                'user_id' => $buyer->id,
                'seller_id' => $vendor->id,
                'total_price' => 800,
                'subtotal_before_tax' => 761.90,
                'tax_amount' => 38.10,
                'status' => 'cancelled_returned',
                'payment_status' => 'refunded',
                'payment_method' => 'wallet',
                'shipping_address_title' => 'المنزل',
                'shipping_address_details' => 'مكة، حي العزيزية، شارع الحج',
                'customer_notes' => 'تم إلغاء الطلب',
                'discount_amount' => 0,
                'commission_rate_snapshot' => 10,
                'status_timeline' => [
                    ['status' => 'pending', 'title' => 'تم استلام الطلب', 'time' => now()->subDays(7)->toDateTimeString()],
                    ['status' => 'cancelled_returned', 'title' => 'تم الإلغاء', 'time' => now()->subDays(6)->toDateTimeString()],
                ],
                'shipped_at' => null,
                'delivered_at' => null,
                'created_at' => now()->subDays(7),
            ],
        ];

        foreach ($orders as $orderData) {
            $order = Order::create($orderData);

            // ربط المنتج بالطلب
            if ($product) {
                $order->products()->attach($product->id, [
                    'quantity' => rand(1, 3),
                    'price' => $orderData['total_price'] / rand(1, 3),
                ]);
            }
        }

        $this->command->info('✅ تم إنشاء الطلبات بنجاح!');
        $this->command->info('   - ' . count($orders) . ' طلب بحالات مختلفة');
        $this->command->info('   - حالات: delivered, processing, pending, cancelled_returned');
    }
}