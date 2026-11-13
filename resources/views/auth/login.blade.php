@extends('layouts.storefront')

@section('title', 'Sign In')

@section('content')
    <div class="max-w-sm mx-auto bg-white border border-black/5 rounded-2xl p-6">
        <div class="text-center mb-6">
            <img src="{{ asset('images/funskool-logo.svg') }}" alt="Funskool" class="h-8 w-auto mx-auto mb-4">
            <h1 class="font-display text-xl font-bold text-ink-900">Sign In</h1>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Password</label>
                <input type="password" name="password" required class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm text-ink-900/70">
                <input type="checkbox" name="remember" class="accent-brand-500"> Remember me
            </label>
            <button type="submit" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5 rounded-full transition-colors">Sign In</button>
        </form>

        <p class="text-sm text-ink-900/50 mt-4 text-center"><a href="{{ route('password.request') }}" class="text-brand-600 font-semibold">Forgot your password?</a></p>

        <p class="text-sm text-ink-900/50 mt-4 text-center">Don't have an account? <a href="{{ route('register') }}" class="text-brand-600 font-semibold">Register</a></p>
    </div>
@endsection
