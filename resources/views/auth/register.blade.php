@extends('layouts.storefront')

@section('title', 'Register')

@section('content')
    <div class="max-w-sm mx-auto bg-white border border-black/5 rounded-2xl p-6">
        <div class="text-center mb-6">
            <img src="{{ asset('images/funskool-logo.svg') }}" alt="Funskool" class="h-8 w-auto mx-auto mb-4">
            <h1 class="font-display text-xl font-bold text-ink-900">Create Account</h1>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Phone</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Password</label>
                <input type="password" name="password" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Confirm Password</label>
                <input type="password" name="password_confirmation" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <label class="flex items-start gap-2 text-sm text-ink-900/70">
                <input type="checkbox" name="marketing_opt_in" value="1" class="mt-1" @checked(old('marketing_opt_in'))>
                <span>Email me about new toys and offers (optional — you can unsubscribe anytime).</span>
            </label>
            <button type="submit" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5 rounded-full transition-colors">Create Account</button>
        </form>

        <p class="text-sm text-ink-900/50 mt-4 text-center">Already have an account? <a href="{{ route('login') }}" class="text-brand-600 font-semibold">Sign in</a></p>
    </div>
@endsection
