<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Services\SupportService;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(protected SupportService $support) {}

    public function index(Request $request)
    {
        return SupportTicketResource::collection($request->user()->supportTickets()->with('order')->latest()->paginate(10));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(SupportTicket::CATEGORIES))],
            'order_id' => ['nullable', 'integer'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $ticket = $this->support->open([...$data, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone], $user, source: 'api');

        return (new SupportTicketResource($ticket->load('publicMessages', 'order')))->response()->setStatusCode(201);
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        return new SupportTicketResource($ticket->load('publicMessages', 'order'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $this->support->customerReply($ticket, $data['body'], $request->user());

        return new SupportTicketResource($ticket->fresh()->load('publicMessages', 'order'));
    }
}
