@extends('layouts.storefront')

@section('title', 'Contact & Help')

@section('content')
    <div class="max-w-2xl mx-auto">
        <h1 class="font-display text-2xl sm:text-3xl font-bold text-ink-900">How can we help?</h1>
        <p class="text-ink-900/60 mt-1 mb-6">Send us a message and we'll reply by email — usually within one business day.
            @auth You can follow your requests under <a href="{{ route('support.index') }}" class="text-brand-600 font-semibold">My Support Tickets</a>.@endauth
        </p>

        <form method="POST" action="{{ route('support.store') }}" enctype="multipart/form-data" class="bg-white border border-black/5 rounded-2xl p-6 space-y-4">
            @csrf
            {{-- Honeypot for bots; hidden from people and screen readers. --}}
            <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-semibold mb-1 text-ink-900">Name</label>
                    <input id="name" name="name" value="{{ old('name', $user?->name) }}" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="email" class="block text-sm font-semibold mb-1 text-ink-900">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user?->email) }}" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-semibold mb-1 text-ink-900">Phone <span class="font-normal text-ink-900/40">(optional)</span></label>
                    <input id="phone" name="phone" value="{{ old('phone', $user?->phone) }}" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="category" class="block text-sm font-semibold mb-1 text-ink-900">Topic</label>
                    <select id="category" name="category" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}" @selected(old('category', 'general') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($orders->isNotEmpty())
                <div>
                    <label for="order_id" class="block text-sm font-semibold mb-1 text-ink-900">Related order <span class="font-normal text-ink-900/40">(optional)</span></label>
                    <select id="order_id" name="order_id" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach ($orders as $order)
                            <option value="{{ $order->id }}" @selected((string) old('order_id', request('order')) === (string) $order->id)>{{ $order->order_number }} · {{ $order->created_at->format('M j, Y') }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="subject" class="block text-sm font-semibold mb-1 text-ink-900">Subject</label>
                <input id="subject" name="subject" value="{{ old('subject') }}" required maxlength="255" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label for="message" class="block text-sm font-semibold mb-1 text-ink-900">Message</label>
                <textarea id="message" name="message" rows="6" required maxlength="5000" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">{{ old('message') }}</textarea>
            </div>
            <div>
                <label for="attachments" class="block text-sm font-semibold mb-1 text-ink-900">Photos or documents <span class="font-normal text-ink-900/40">(optional, up to 3 · 5 MB each)</span></label>
                <input id="attachments" type="file" name="attachments[]" multiple accept="image/*,.pdf,.txt" class="text-sm">
            </div>
            <button type="submit" class="w-full sm:w-auto bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5 rounded-full transition-colors">Send message</button>
        </form>
    </div>
@endsection
