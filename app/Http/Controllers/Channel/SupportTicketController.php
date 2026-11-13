<?php

namespace App\Http\Controllers\Channel;

use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportService;
use Illuminate\Http\Request;

/** Lets a connected website's contact form create and follow tickets here. */
class SupportTicketController extends ChannelController
{
    public function __construct(protected SupportService $support) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(SupportTicket::CATEGORIES))],
            'order_number' => ['nullable', 'string'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $channel = $this->channel($request);

        if (! empty($data['order_number'])) {
            $data['order_id'] = $channel->orders()
                ->where(fn ($query) => $query->where('order_number', $data['order_number'])->orWhere('external_reference', $data['order_number']))
                ->value('id');
        }

        $ticket = $this->support->open($data, User::query()->where('email', strtolower($data['email']))->first(), source: 'channel', channel: $channel, openedByStaff: null);

        return (new SupportTicketResource($ticket->load('publicMessages', 'order')))
            ->additional(['customer_url' => $ticket->guestUrl()])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $ticketNumber)
    {
        return new SupportTicketResource($this->find($request, $ticketNumber)->load('publicMessages', 'order'));
    }

    public function reply(Request $request, string $ticketNumber)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $ticket = $this->find($request, $ticketNumber);

        $this->support->customerReply($ticket, $data['body']);

        return new SupportTicketResource($ticket->fresh()->load('publicMessages', 'order'));
    }

    protected function find(Request $request, string $ticketNumber): SupportTicket
    {
        return SupportTicket::query()
            ->where('sales_channel_id', $this->channel($request)->id)
            ->where('ticket_number', $ticketNumber)
            ->firstOrFail();
    }
}
