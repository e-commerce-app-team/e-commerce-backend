<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewProductNotification extends Notification
{
    use Queueable;

    protected $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function via($notifiable): array
    {
        return ['database']; // حفظ الإشعار في قاعدة البيانات
    }

    public function toArray($notifiable): array
    {
        return [
            'product_id'   => $this->product->id,
            'product_name' => $this->product->name,
            'seller_id'    => $this->product->user_id, // أو seller_id حسب تسميتك
            'message'      => 'New product added: ' . $this->product->name,
        ];
    }
}