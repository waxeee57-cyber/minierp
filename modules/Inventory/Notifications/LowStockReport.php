<?php

namespace Modules\Inventory\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Modules\Inventory\Data\StockForecast;

class LowStockReport extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  Collection<int, StockForecast>  $products */
    public function __construct(public readonly Collection $products) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Készletriasztás: {$this->products->count()} termék")
            ->greeting('Szia!')
            ->line('Az alábbi termékek fogyóban vannak, vagy a beszerzési átfutáson belül kifogynak:');

        foreach ($this->products as $product) {
            $cover = $product->daysOfCover === null ? 'nincs mozgás' : "~{$product->daysOfCover} napig elég";
            $mail->line("• {$product->name} ({$product->sku}): {$product->stock} db, {$cover}. Javasolt rendelés: {$product->suggestedReorder} db.");
        }

        return $mail->action('Készlet megnyitása', url('/?view=inventory'));
    }
}
