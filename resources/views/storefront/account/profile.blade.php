@extends('layouts.storefront')

@section('title', 'Profile & Privacy')

@section('content')
    <h1 class="font-display text-2xl font-bold mb-6 text-ink-900">Profile &amp; Privacy</h1>

    <div class="grid md:grid-cols-2 gap-6">
        <form method="POST" action="{{ route('account.update') }}" class="bg-white border border-black/5 rounded-2xl p-5 space-y-4">
            @csrf
            @method('PATCH')
            <h2 class="font-display font-bold text-ink-900">Your details</h2>
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Name</label>
                <input name="name" value="{{ old('name', $user->name) }}" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Email</label>
                <input value="{{ $user->email }}" disabled class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm bg-black/5">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Phone</label>
                <input name="phone" value="{{ old('phone', $user->phone) }}" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <label class="flex items-start gap-2 text-sm text-ink-900/70">
                <input type="hidden" name="marketing_opt_in" value="0">
                <input type="checkbox" name="marketing_opt_in" value="1" class="mt-1" @checked($user->marketing_opt_in)>
                <span>Send me marketing emails about new toys and offers. Order and delivery updates are always sent.</span>
            </label>
            @if ($user->marketing_opt_in_at)
                <p class="text-xs text-ink-900/40">Preference last changed {{ $user->marketing_opt_in_at->format('M j, Y') }}.</p>
            @endif
            <button class="rounded-full bg-brand-500 px-5 py-2 text-sm font-bold text-white hover:bg-brand-600">Save</button>
        </form>

        <form method="POST" action="{{ route('account.password') }}" class="bg-white border border-black/5 rounded-2xl p-5 space-y-4">
            @csrf
            @method('PUT')
            <h2 class="font-display font-bold text-ink-900">Change password</h2>
            <input type="password" name="current_password" placeholder="Current password" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            <input type="password" name="password" placeholder="New password (min 8 characters)" required minlength="8" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            <input type="password" name="password_confirmation" placeholder="Confirm new password" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            <button class="rounded-full bg-ink-900 px-5 py-2 text-sm font-bold text-white">Update password</button>
            <p class="text-xs text-ink-900/40">To delete your account or get a copy of your data, contact {{ \App\Models\Setting::get('store_email', 'support') }}.</p>
        </form>
    </div>
@endsection
