@extends('layouts.storefront')

@section('title', 'Addresses')

@section('content')
    <h1 class="font-display text-2xl sm:text-3xl font-bold mb-6 text-ink-900">Addresses</h1>

    <div class="grid md:grid-cols-2 gap-6">
        <div class="space-y-3">
            @forelse ($addresses as $address)
                <div class="bg-white border border-black/5 rounded-2xl p-4 flex justify-between items-start">
                    <div class="text-sm text-ink-900/70">
                        <p class="font-semibold text-ink-900">{{ $address->full_name }} {{ $address->label ? "($address->label)" : '' }}</p>
                        <p>{{ $address->line1 }}, {{ $address->line2 }}</p>
                        <p>{{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}</p>
                        <p>{{ $address->phone }}</p>
                    </div>
                    <form method="POST" action="{{ route('addresses.destroy', $address) }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-sm font-semibold text-brand-600 hover:text-brand-700">Remove</button>
                    </form>
                </div>
            @empty
                <p class="text-ink-900/50 text-sm">No saved addresses yet.</p>
            @endforelse
        </div>

        <div class="bg-white border border-black/5 rounded-2xl p-5">
            <h2 class="font-display font-bold text-ink-900 mb-4">Add Address</h2>
            <form method="POST" action="{{ route('addresses.store') }}" class="grid grid-cols-2 gap-3">
                @csrf
                <input type="text" name="label" placeholder="Label (e.g. Home)" class="border border-black/10 rounded-lg px-3 py-2 text-sm col-span-2">
                <input type="text" name="full_name" placeholder="Full Name" required class="border border-black/10 rounded-lg px-3 py-2 text-sm col-span-2">
                <input type="text" name="phone" placeholder="Phone" required class="border border-black/10 rounded-lg px-3 py-2 text-sm col-span-2">
                <input type="text" name="line1" placeholder="Address Line 1" required class="border border-black/10 rounded-lg px-3 py-2 text-sm col-span-2">
                <input type="text" name="line2" placeholder="Address Line 2" class="border border-black/10 rounded-lg px-3 py-2 text-sm col-span-2">
                <input type="text" name="city" placeholder="City" required class="border border-black/10 rounded-lg px-3 py-2 text-sm">
                <input type="text" name="state" placeholder="State" required class="border border-black/10 rounded-lg px-3 py-2 text-sm">
                <input type="text" name="postal_code" placeholder="Postal Code" required class="border border-black/10 rounded-lg px-3 py-2 text-sm">
                <input type="text" name="country" placeholder="Country" value="India" required class="border border-black/10 rounded-lg px-3 py-2 text-sm">
                <button type="submit" class="col-span-2 bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5 rounded-full mt-2 transition-colors">Save Address</button>
            </form>
        </div>
    </div>
@endsection
