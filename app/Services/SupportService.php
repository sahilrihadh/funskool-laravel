<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\SalesChannel;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\StaffAlertNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SupportService
{
    /** Private disk: attachments are only served through an authorised download route. */
    public const ATTACHMENT_DISK = 'local';

    public const ATTACHMENT_RULES = ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif,pdf,txt'];

    public function __construct(protected StaffNotifier $staff) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string, subject: string, category?: ?string, priority?: ?string, message: string, order_id?: ?int}  $data
     * @param  array<int, UploadedFile|array|string>  $attachments
     */
    public function open(array $data, ?User $user = null, array $attachments = [], string $source = 'storefront', ?SalesChannel $channel = null, ?User $openedByStaff = null): SupportTicket
    {
        $order = ! empty($data['order_id']) ? Order::query()->find($data['order_id']) : null;

        // Customers can only attach their own orders.
        if ($order && $user && ! $openedByStaff && $order->user_id !== $user->id) {
            throw ValidationException::withMessages(['order_id' => 'That order is not on your account.']);
        }

        $ticket = DB::transaction(function () use ($data, $user, $attachments, $source, $channel, $order, $openedByStaff) {
            $ticket = SupportTicket::query()->create([
                'user_id' => $user?->id,
                'order_id' => $order?->id,
                'sales_channel_id' => $channel?->id,
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'subject' => $data['subject'],
                'category' => array_key_exists($data['category'] ?? '', SupportTicket::CATEGORIES) ? $data['category'] : 'general',
                'priority' => $data['priority'] ?? 'medium',
                'status' => TicketStatus::Open->value,
                'source' => $source,
                'last_customer_reply_at' => now(),
            ]);

            $ticket->messages()->create([
                'user_id' => $openedByStaff?->id ?? $user?->id,
                'author_name' => $data['name'],
                'author_email' => $data['email'],
                'is_staff' => false,
                'body' => $data['message'],
                'attachments' => $this->storeAttachments($ticket, $attachments),
            ]);

            return $ticket;
        });

        Notification::route('mail', $ticket->email)->notify(new TicketCreatedNotification($ticket));

        $this->alertStaff($ticket, "New ticket #{$ticket->ticket_number}", "{$ticket->name}: {$ticket->subject}");

        return $ticket;
    }

    /** A customer (or guest via their emailed link) adds a message. Re-opens solved tickets. */
    public function customerReply(SupportTicket $ticket, string $body, ?User $user = null, array $attachments = []): SupportTicketMessage
    {
        if ($ticket->status === TicketStatus::Closed->value) {
            throw ValidationException::withMessages(['body' => 'This ticket is closed. Please open a new ticket.']);
        }

        $message = DB::transaction(function () use ($ticket, $body, $user, $attachments) {
            $message = $ticket->messages()->create([
                'user_id' => $user?->id,
                'author_name' => $user?->name ?? $ticket->name,
                'author_email' => $user?->email ?? $ticket->email,
                'is_staff' => false,
                'body' => $body,
                'attachments' => $this->storeAttachments($ticket, $attachments),
            ]);

            $ticket->update([
                'last_customer_reply_at' => now(),
                'status' => $ticket->status === TicketStatus::Solved->value ? TicketStatus::Open->value : $ticket->status,
                'solved_at' => null,
            ]);

            return $message;
        });

        $this->alertStaff($ticket, "Customer replied on #{$ticket->ticket_number}", str($body)->limit(120)->toString());

        return $message;
    }

    /**
     * Staff reply or internal note. Public replies email the customer.
     *
     * @param  array<int, UploadedFile|string>  $attachments
     */
    public function staffReply(SupportTicket $ticket, User $staff, string $body, ?string $status = null, bool $internal = false, array $attachments = []): SupportTicketMessage
    {
        $message = DB::transaction(function () use ($ticket, $staff, $body, $status, $internal, $attachments) {
            $message = $ticket->messages()->create([
                'user_id' => $staff->id,
                'author_name' => $staff->name,
                'author_email' => $staff->email,
                'is_staff' => true,
                'is_internal' => $internal,
                'body' => $body,
                'attachments' => $this->storeAttachments($ticket, $attachments),
            ]);

            $updates = ['assigned_to' => $ticket->assigned_to ?? $staff->id];

            if (! $internal) {
                $updates['last_staff_reply_at'] = now();
                $updates['first_response_at'] = $ticket->first_response_at ?? now();
            }

            $ticket->update($updates);

            if ($status) {
                $this->setStatus($ticket, $status);
            } elseif (! $internal && $ticket->status === TicketStatus::Open->value) {
                $this->setStatus($ticket, TicketStatus::InProgress->value);
            }

            return $message;
        });

        if (! $internal) {
            Notification::route('mail', $ticket->email)->notify(new TicketRepliedNotification($ticket->fresh(), $message));
        }

        return $message;
    }

    public function setStatus(SupportTicket $ticket, string $status): void
    {
        $status = TicketStatus::from($status)->value;

        if ($ticket->status === $status) {
            return;
        }

        $ticket->update([
            'status' => $status,
            'solved_at' => in_array($status, TicketStatus::resolved(), true) ? now() : null,
        ]);
    }

    public function assign(SupportTicket $ticket, ?User $assignee): void
    {
        $ticket->update(['assigned_to' => $assignee?->id]);

        if ($assignee && $assignee->id !== auth()->id()) {
            $this->staff->notify('support.view', "Ticket #{$ticket->ticket_number} assigned to you", $ticket->subject, $this->adminUrl($ticket), 'heroicon-o-lifebuoy', recipients: [$assignee]);
        }
    }

    public function adminUrl(SupportTicket $ticket): string
    {
        return url("/admin/support-tickets/{$ticket->ticket_number}");
    }

    /** @return array<int, array{path: string, name: string, size: int}> */
    protected function storeAttachments(SupportTicket $ticket, array $files): array
    {
        return collect($files)->filter()->map(function (UploadedFile|array|string $file) use ($ticket) {
            // Filament uploads arrive already stored on the private disk: a path, or ['path' => …, 'name' => …].
            if (! $file instanceof UploadedFile) {
                $path = is_array($file) ? $file['path'] : $file;

                return ['path' => $path, 'name' => is_array($file) ? ($file['name'] ?? basename($path)) : basename($path), 'size' => (int) Storage::disk(self::ATTACHMENT_DISK)->size($path)];
            }

            return [
                'path' => $file->store("support-attachments/{$ticket->id}", self::ATTACHMENT_DISK),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ];
        })->values()->all();
    }

    protected function alertStaff(SupportTicket $ticket, string $title, string $body): void
    {
        $recipients = $ticket->assignee ? [$ticket->assignee] : null;

        $this->staff->notify('support.view', $title, $body, $this->adminUrl($ticket), 'heroicon-o-lifebuoy', 'warning', $recipients);

        if ($email = Setting::get('support_alert_email')) {
            Notification::route('mail', $email)->notify(new StaffAlertNotification($title, [$body, 'Category: '.$ticket->categoryLabel()], $this->adminUrl($ticket), 'Open ticket'));
        }
    }
}
