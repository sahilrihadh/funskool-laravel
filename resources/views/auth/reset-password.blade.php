@extends('layouts.storefront')

@section('title', 'Choose a New Password')

@section('content')
    <div class="max-w-sm mx-auto bg-white border border-black/5 rounded-2xl p-6">
        <h1 class="font-display text-xl font-bold text-ink-900 text-center mb-6">Choose a new password</h1>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Email</label>
                <input type="email" name="email" value="{{ old('email', $email) }}" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">New password</label>
                <input type="password" name="password" required minlength="8" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Confirm password</label>
                <input type="password" name="password_confirmation" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5 rounded-full transition-colors">Reset password</button>
        </form>
    </div>
@endsection
