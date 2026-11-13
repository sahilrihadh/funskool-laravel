<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by('auth:'.$request->ip()),
            Limit::perMinute(5)->by('auth:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by('checkout:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('support', fn (Request $request) => Limit::perMinute(5)->by('support:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('channel', fn (Request $request) => Limit::perMinute(300)->by('channel:'.($request->attributes->get('sales_channel')?->id ?? $request->ip())));

        // Headless frontends can point reset emails at their own page.
        if ($frontend = config('app.frontend_url')) {
            ResetPassword::createUrlUsing(fn (User $user, string $token) => rtrim($frontend, '/')."/reset-password?token={$token}&email=".urlencode($user->email));
        }

        // Security events for staff accounts go to the audit log.
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User && $event->user->isStaff()) {
                ActivityLog::record('login', $event->user, description: 'Staff sign-in');
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            if ($event->user instanceof User && $event->user->isStaff()) {
                ActivityLog::record('login_failed', $event->user, description: 'Failed staff sign-in attempt');
            }
        });
    }
}
