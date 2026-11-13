<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\SupportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SupportController extends Controller
{
    public function __construct(protected SupportService $support) {}

    public function contact()
    {
        return view('storefront.support.contact', [
            'user' => Auth::user(),
            'orders' => Auth::user()?->orders()->latest()->limit(20)->get(['id', 'order_number', 'created_at']) ?? collect(),
            'categories' => SupportTicket::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        // Honeypot: real people never fill the hidden "website" field.
        if (filled($request->input('website'))) {
            return redirect()->route('home');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:'.implode(',', array_keys(SupportTicket::CATEGORIES))],
            'order_id' => ['nullable', 'integer'],
            'message' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => SupportService::ATTACHMENT_RULES,
        ]);

        $ticket = $this->support->open($data, Auth::user(), $request->file('attachments', []));

        return Auth::check()
            ? redirect()->route('support.show', $ticket)->with('success', "Ticket #{$ticket->ticket_number} created — we'll reply by email.")
            : redirect()->to($ticket->guestUrl())->with('success', "Ticket #{$ticket->ticket_number} created. We've emailed you a link to this page.");
    }

    public function index()
    {
        return view('storefront.support.index', [
            'tickets' => Auth::user()->supportTickets()->latest()->paginate(10),
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === Auth::id(), 403);

        return $this->thread($ticket, route('support.reply', $ticket));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === Auth::id(), 403);

        $this->storeReply($request, $ticket);

        return back()->with('success', 'Reply sent.');
    }

    /** Guests (and channel customers) reach their ticket through the emailed token link. */
    public function guest(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->verifyToken($request->query('token')), 404);

        return $this->thread($ticket, route('support.guest.reply', ['ticket' => $ticket, 'token' => $request->query('token')]), $request->query('token'));
    }

    public function guestReply(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->verifyToken($request->query('token')), 404);

        $this->storeReply($request, $ticket);

        return back()->with('success', 'Reply sent.');
    }

    public function attachment(Request $request, SupportTicketMessage $message, int $index)
    {
        $ticket = $message->ticket;
        $user = $request->user();

        $isStaff = (bool) $user?->hasPermission('support.view');
        $isOwner = $user && $ticket->user_id === $user->id;
        $hasToken = $ticket->verifyToken($request->query('token'));

        abort_unless($isStaff || (! $message->is_internal && ($isOwner || $hasToken)), 403);

        $file = $message->attachments[$index] ?? abort(404);

        return Storage::disk(SupportService::ATTACHMENT_DISK)->download($file['path'], $file['name'] ?? basename($file['path']));
    }

    protected function thread(SupportTicket $ticket, string $replyUrl, ?string $token = null)
    {
        return view('storefront.support.show', [
            'ticket' => $ticket->load('publicMessages', 'order'),
            'replyUrl' => $replyUrl,
            'token' => $token,
        ]);
    }

    protected function storeReply(Request $request, SupportTicket $ticket): void
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => SupportService::ATTACHMENT_RULES,
        ]);

        $this->support->customerReply($ticket, $data['body'], Auth::user(), $request->file('attachments', []));
    }
}
