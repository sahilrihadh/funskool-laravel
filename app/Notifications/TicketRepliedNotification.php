<?php

namespace App\Notifications;

use App\Enums\TicketStatus;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SupportTicket $ticket, public SupportTicketMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[#{$this->ticket->ticket_number}] {$this->ticket->subject}")
            ->greeting("Hi {$this->ticket->name},")
            ->line("{$this->message->author_name} from ".Setting::get('store_name', config('app.name')).' replied:');

        foreach (preg_split('/\R{2,}/', trim($this->message->body)) as $paragraph) {
            $mail->line($paragraph);
        }

        if ($this->ticket->status === TicketStatus::Solved->value) {
            $mail->line("We've marked this ticket as solved. Reply from the ticket page if you still need help — it will reopen automatically.");
        }

        return $mail->action('View & reply', TicketCreatedNotification::ticketUrl($this->ticket));
    }
}
