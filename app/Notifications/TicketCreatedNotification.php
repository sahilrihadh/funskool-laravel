<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SupportTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[#{$this->ticket->ticket_number}] We've received your request")
            ->greeting("Hi {$this->ticket->name},")
            ->line('Thanks for contacting '.Setting::get('store_name', config('app.name')).". Your ticket number is #{$this->ticket->ticket_number}.")
            ->line("Subject: {$this->ticket->subject}")
            ->line("We'll reply by email, usually within one business day.")
            ->action('View your ticket', self::ticketUrl($this->ticket));
    }

    public static function ticketUrl(SupportTicket $ticket): string
    {
        return $ticket->user_id && ! $ticket->sales_channel_id
            ? route('support.show', $ticket)
            : $ticket->guestUrl();
    }
}
