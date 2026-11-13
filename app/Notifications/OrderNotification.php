<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Setting;
use App\Notifications\Channels\SmsChannel;
use App\Sms\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Transactional customer message about an order. Sent to the order's
 * contact snapshot (works for guests / channel orders too), never gated by
 * marketing consent.
 */
abstract class OrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Order $order) {}

    abstract protected function subject(): string;

    abstract protected function lines(): array;

    abstract protected function smsTemplate(): string;

    public static function sendFor(Order $order, mixed ...$arguments): void
    {
        $email = $order->contactEmail();
        $phone = $order->contactPhone();

        if (! $email && ! $phone) {
            return;
        }

        $route = NotificationFacade::route('mail', $email);

        if ($phone) {
            $route->route('sms', $phone);
        }

        $route->notify(new static($order, ...$arguments));
    }

    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable instanceof AnonymousNotifiable && ! empty($notifiable->routes['mail'])) {
            $channels[] = 'mail';
        }

        if ($notifiable instanceof AnonymousNotifiable && ! empty($notifiable->routes['sms'])) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject().' — '.$this->storeName())
            ->greeting("Hi {$this->order->contactName()},");

        foreach ($this->lines() as $line) {
            $message->line($line);
        }

        if ($this->order->user_id && ! $this->order->sales_channel_id) {
            $message->action('View your order', route('orders.show', $this->order));
        }

        return $message
            ->line('Order total: '.$this->money($this->order->total))
            ->salutation('Thanks for shopping with '.$this->storeName().'!');
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return new SmsMessage(
            text: $this->storeName().': '.$this->subject().'. Order '.$this->order->order_number.'.',
            template: $this->smsTemplate(),
            variables: ['var1' => $this->order->contactName(), 'var2' => $this->order->order_number],
        );
    }

    protected function storeName(): string
    {
        return (string) Setting::get('store_name', config('app.name'));
    }

    protected function money(float|string $amount): string
    {
        return Setting::get('currency_symbol', '₹').number_format((float) $amount, 2);
    }
}
