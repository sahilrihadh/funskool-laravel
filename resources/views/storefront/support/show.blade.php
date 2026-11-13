@extends('layouts.storefront')

@section('title', 'Ticket #'.$ticket->ticket_number)

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink-900">#{{ $ticket->ticket_number }} · {{ $ticket->subject }}</h1>
                <p class="text-ink-900/50 text-sm">{{ $ticket->categoryLabel() }} · opened {{ $ticket->created_at->format('M j, Y g:i A') }}
                    @if ($ticket->order) · Order {{ $ticket->order->order_number }} @endif</p>
            </div>
            <span class="rounded-full bg-black/5 px-3 py-1 text-sm font-semibold text-ink-900">{{ \App\Enums\TicketStatus::labelFor($ticket->status) }}</span>
        </div>

        <div class="space-y-4">
            @foreach ($ticket->publicMessages as $message)
                <article @class([
                    'rounded-2xl border p-5',
                    'bg-white border-black/5' => ! $message->is_staff,
                    'bg-sky-tint-100 border-sky-400/30' => $message->is_staff,
                ])>
                    <div class="flex flex-wrap justify-between gap-2 text-sm">
                        <strong class="text-ink-900">{{ $message->is_staff ? $message->author_name.' · '.\App\Models\Setting::get('store_name', 'Support') : 'You' }}</strong>
                        <time class="text-ink-900/40">{{ $message->created_at->format('M j, Y g:i A') }}</time>
                    </div>
                    <div class="mt-2 text-sm leading-6 whitespace-pre-line text-ink-900/80">{{ $message->body }}</div>
                    @if (! empty($message->attachments))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($message->attachments as $index => $file)
                                <a href="{{ route('support.attachment', $token ? [$message, $index, 'token' => $token] : [$message, $index]) }}" target="_blank" rel="noopener"
                                   class="rounded-full border border-black/10 bg-white px-3 py-1 text-xs font-semibold text-ink-900/70 hover:text-brand-600">📎 {{ \Illuminate\Support\Str::limit($file['name'] ?? 'attachment', 30) }}</a>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        @if ($ticket->status !== \App\Enums\TicketStatus::Closed->value)
            <form method="POST" action="{{ $replyUrl }}" enctype="multipart/form-data" class="mt-6 bg-white border border-black/5 rounded-2xl p-5 space-y-3">
                @csrf
                <label for="body" class="block font-display font-bold text-ink-900">Add a reply</label>
                <textarea id="body" name="body" rows="4" required maxlength="5000" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">{{ old('body') }}</textarea>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <input type="file" name="attachments[]" multiple accept="image/*,.pdf,.txt" class="text-sm" aria-label="Attach files">
                    <button class="rounded-full bg-brand-500 px-5 py-2 text-sm font-bold text-white hover:bg-brand-600">Send reply</button>
                </div>
                @if ($ticket->isResolved())
                    <p class="text-xs text-ink-900/50">This ticket is marked solved — replying reopens it.</p>
                @endif
            </form>
        @else
            <p class="mt-6 text-sm text-ink-900/60">This ticket is closed. <a href="{{ route('support.contact') }}" class="text-brand-600 font-semibold">Open a new request</a> if you still need help.</p>
        @endif
    </div>
@endsection
