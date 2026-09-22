<?php

namespace Modules\Inventory\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Modules\Inventory\Models\Product;

class LowStockReport extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  Collection<int, Product>  $products */
    public function __construct(public readonly Collection $products) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Alacsony készlet: {$this->products->count()} termék")
            ->greeting('Szia!')
            ->line('Az alábbi termékek elérték az újrarendelési szintet:');

        foreach ($this->products as $product) {
            $mail->line("• {$product->name} ({$product->sku}): {$product->stock} db, újrarendelési szint: {$product->reorder_level}");
        }

        return $mail->action('Készlet megnyitása', url('/?view=inventory'));
    }
}
