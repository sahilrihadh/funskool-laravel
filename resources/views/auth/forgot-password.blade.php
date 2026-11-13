@extends('layouts.storefront')

@section('title', 'Forgot Password')

@section('content')
    <div class="max-w-sm mx-auto bg-white border border-black/5 rounded-2xl p-6">
        <div class="text-center mb-6">
            <h1 class="font-display text-xl font-bold text-ink-900">Reset your password</h1>
            <p class="text-sm text-ink-900/50 mt-1">We'll email you a link to choose a new one.</p>
        </div>

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1 text-ink-900">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full border border-black/10 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5 rounded-full transition-colors">Send reset link</button>
        </form>

        <p class="text-sm text-ink-900/50 mt-4 text-center"><a href="{{ route('login') }}" class="text-brand-600 font-semibold">Back to sign in</a></p>
    </div>
@endsection
