@extends('layouts.storefront')

@section('title', 'My Support Tickets')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="font-display text-2xl font-bold text-ink-900">My Support Tickets</h1>
        <a href="{{ route('support.contact') }}" class="rounded-full bg-brand-500 px-5 py-2 text-sm font-bold text-white hover:bg-brand-600">New request</a>
    </div>

    @if ($tickets->isEmpty())
        <p class="text-ink-900/60">You haven't contacted us yet. <a href="{{ route('support.contact') }}" class="text-brand-600 font-semibold">Ask a question</a>.</p>
    @else
        <div class="bg-white border border-black/5 rounded-2xl divide-y divide-black/5">
            @foreach ($tickets as $ticket)
                <a href="{{ route('support.show', $ticket) }}" class="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-black/[0.02]">
                    <div class="min-w-0">
                        <p class="font-semibold text-ink-900 truncate">#{{ $ticket->ticket_number }} · {{ $ticket->subject }}</p>
                        <p class="text-sm text-ink-900/50">{{ $ticket->categoryLabel() }} · opened {{ $ticket->created_at->format('M j, Y') }}</p>
                    </div>
                    <span class="rounded-full bg-black/5 px-3 py-1 text-sm font-semibold text-ink-900">{{ \App\Enums\TicketStatus::labelFor($ticket->status) }}</span>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $tickets->links() }}</div>
    @endif
@endsection
