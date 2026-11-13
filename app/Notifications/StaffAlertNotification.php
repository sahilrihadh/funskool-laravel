<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Operational alert to the store's alert email (new order, low stock, failures). */
class StaffAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subject,
        public array $lines,
        public ?string $actionUrl = null,
        public string $actionText = 'Open in admin',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->subject);

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        return $this->actionUrl ? $message->action($this->actionText, $this->actionUrl) : $message;
    }
}
