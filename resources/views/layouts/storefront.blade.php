<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Funskool Store'))</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('images/funskool-logo.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col bg-sky-tint-100 text-ink-900 font-sans">

    <div class="bg-brand-500 text-white text-xs sm:text-sm font-medium">
        <div class="max-w-6xl mx-auto px-4 h-9 flex items-center justify-center text-center gap-2">
            <span>🎉 Free shipping on orders above ₹999 &middot; 38+ years of play, trusted nationwide</span>
        </div>
    </div>

    <header class="bg-white border-b border-black/5 sticky top-0 z-40 shadow-sm">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex items-center justify-between h-20 gap-6">
                <a href="{{ route('home') }}" class="shrink-0">
                    <img src="{{ asset('images/funskool-logo.svg') }}" alt="{{ \App\Models\Setting::get('store_name', 'Funskool') }}" class="h-10 w-auto">
                </a>

                <nav class="hidden lg:flex items-center gap-1 text-sm font-semibold">
                    @foreach (\App\Models\Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->limit(7)->get() as $navCategory)
                        <a href="{{ route('categories.show', $navCategory) }}"
                           class="px-3 py-2 rounded-full text-ink-900/80 hover:text-brand-600 hover:bg-brand-50 transition-colors">
                            {{ $navCategory->name }}
                        </a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-1 sm:gap-3">
                    @auth
                        <div class="relative group">
                            <button class="flex items-center gap-1.5 px-3 py-2 rounded-full text-sm font-semibold text-ink-900/80 hover:bg-brand-50 hover:text-brand-600 transition-colors">
                                <span class="w-7 h-7 rounded-full bg-sun-400 text-ink-900 flex items-center justify-center text-xs font-bold">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
                                <span class="hidden sm:inline">{{ Str::before(auth()->user()->name, ' ') }}</span>
                            </button>
                            <div class="absolute right-0 hidden group-hover:block pt-2 w-48 z-50">
                                <div class="bg-white border border-black/5 rounded-xl shadow-lg py-1.5">
                                    <a href="{{ route('orders.index') }}" class="block px-4 py-2 text-sm font-medium hover:bg-brand-50 hover:text-brand-600">My Orders</a>
                                    <a href="{{ route('wishlist.index') }}" class="block px-4 py-2 text-sm font-medium hover:bg-brand-50 hover:text-brand-600">Wishlist</a>
                                    <a href="{{ route('addresses.index') }}" class="block px-4 py-2 text-sm font-medium hover:bg-brand-50 hover:text-brand-600">Addresses</a>
                                    <form method="POST" action="{{ route('logout') }}" class="border-t border-black/5 mt-1 pt-1">
                                        @csrf
                                        <button class="w-full text-left px-4 py-2 text-sm font-medium hover:bg-brand-50 hover:text-brand-600">Sign out</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="px-3 py-2 rounded-full text-sm font-semibold text-ink-900/80 hover:bg-brand-50 hover:text-brand-600 transition-colors">Sign in</a>
                    @endauth

                    <a href="{{ route('cart.index') }}" class="relative flex items-center gap-1.5 px-3 py-2 rounded-full text-sm font-semibold text-ink-900/80 hover:bg-brand-50 hover:text-brand-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span class="hidden sm:inline">Cart</span>
                        @php $cartCount = auth()->check() ? (auth()->user()->cart?->items->sum('quantity') ?? 0) : 0; @endphp
                        @if ($cartCount)
                            <span class="absolute -top-1 -right-1 bg-brand-500 text-white text-[11px] font-bold rounded-full w-5 h-5 flex items-center justify-center">{{ $cartCount }}</span>
                        @endif
                    </a>
                </div>
            </div>

            <nav class="lg:hidden flex items-center gap-1 overflow-x-auto pb-3 text-sm font-semibold -mx-1 px-1">
                @foreach (\App\Models\Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->get() as $navCategory)
                    <a href="{{ route('categories.show', $navCategory) }}"
                       class="shrink-0 px-3 py-1.5 rounded-full text-ink-900/80 bg-sky-tint-100 hover:text-brand-600 hover:bg-brand-50 transition-colors">
                        {{ $navCategory->name }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="flex-1">
        <div class="max-w-6xl mx-auto px-4 py-8">
            @if (session('success'))
                <div class="mb-6 rounded-xl bg-leaf-400/20 text-cocoa-600 border border-leaf-400/40 px-4 py-3 text-sm font-medium">{{ session('success') }}</div>
            @endif
            @if (session('info'))
                <div class="mb-6 rounded-xl bg-sky-tint-300/50 text-sky-500 border border-sky-400/40 px-4 py-3 text-sm font-medium">{{ session('info') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-xl bg-brand-50 text-brand-700 border border-brand-200 px-4 py-3 text-sm font-medium">
                    <ul class="list-disc pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    <footer class="bg-ink-900 text-white mt-16">
        <div class="max-w-6xl mx-auto px-4 py-12 grid grid-cols-2 md:grid-cols-4 gap-8">
            <div class="col-span-2 md:col-span-1">
                <img src="{{ asset('images/funskool-logo.svg') }}" alt="Funskool" class="h-9 w-auto mb-4 bg-white rounded-md p-1.5">
                <p class="text-sm text-white/60 leading-relaxed">India's most loved toy brand — 38+ years of play, 30+ countries, thousands of happy families.</p>
            </div>

            <div>
                <h3 class="font-display font-semibold text-sun-400 mb-3 text-sm tracking-wide uppercase">Shop</h3>
                <ul class="space-y-2 text-sm text-white/70">
                    @foreach (\App\Models\Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->limit(6)->get() as $footerCategory)
                        <li><a href="{{ route('categories.show', $footerCategory) }}" class="hover:text-white transition-colors">{{ $footerCategory->name }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h3 class="font-display font-semibold text-sun-400 mb-3 text-sm tracking-wide uppercase">Account</h3>
                <ul class="space-y-2 text-sm text-white/70">
                    <li><a href="{{ route('orders.index') }}" class="hover:text-white transition-colors">My Orders</a></li>
                    <li><a href="{{ route('wishlist.index') }}" class="hover:text-white transition-colors">Wishlist</a></li>
                    <li><a href="{{ route('cart.index') }}" class="hover:text-white transition-colors">Cart</a></li>
                    <li><a href="{{ route('addresses.index') }}" class="hover:text-white transition-colors">Addresses</a></li>
                    <li><a href="{{ route('account.edit') }}" class="hover:text-white transition-colors">Profile &amp; Privacy</a></li>
                    <li><a href="{{ route('support.index') }}" class="hover:text-white transition-colors">Support Tickets</a></li>
                </ul>
            </div>

            <div>
                <h3 class="font-display font-semibold text-sun-400 mb-3 text-sm tracking-wide uppercase">Get in Touch</h3>
                <ul class="space-y-2 text-sm text-white/70">
                    <li><a href="{{ route('support.contact') }}" class="font-semibold text-white hover:text-sun-300 transition-colors">Contact &amp; Help →</a></li>
                    @if ($email = \App\Models\Setting::get('store_email'))
                        <li>{{ $email }}</li>
                    @endif
                    @if ($phone = \App\Models\Setting::get('store_phone'))
                        <li>{{ $phone }}</li>
                    @endif
                    @if ($address = \App\Models\Setting::get('store_address'))
                        <li>{{ $address }}</li>
                    @endif
                    @foreach (\App\Models\Page::query()->published()->where('show_in_footer', true)->orderBy('sort_order')->get(['title', 'slug']) as $footerPage)
                        <li><a href="{{ route('pages.show', $footerPage->slug) }}" class="hover:text-white transition-colors">{{ $footerPage->title }}</a></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="border-t border-white/10">
            <div class="max-w-6xl mx-auto px-4 py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-white/50">
                <p>&copy; {{ now()->year }} {{ \App\Models\Setting::get('store_name', 'Funskool') }}. All rights reserved.</p>
                <p>Built with Laravel &amp; Filament — product data referenced from funskoolindia.com for demo purposes.</p>
            </div>
        </div>
    </footer>
</body>
</html>
