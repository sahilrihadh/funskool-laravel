<?php

namespace App\Http\Resources;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupportTicket */
class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'category' => $this->category,
            'status' => $this->status,
            'priority' => $this->priority,
            'order_number' => $this->order?->order_number,
            'customer' => ['name' => $this->name, 'email' => $this->email],
            // Internal staff notes are never exposed.
            'messages' => $this->whenLoaded('publicMessages', fn () => $this->publicMessages->map(fn ($message) => [
                'author' => $message->is_staff ? $message->author_name : 'customer',
                'is_staff' => $message->is_staff,
                'body' => $message->body,
                'attachments' => collect($message->attachments ?? [])->pluck('name'),
                'created_at' => $message->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
