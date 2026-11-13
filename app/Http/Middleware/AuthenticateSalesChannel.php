<?php

namespace App\Http\Middleware;

use App\Models\SalesChannel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a connected website by its channel API key, sent as
 * "Authorization: Bearer <key>" or "X-Api-Key: <key>". Keys are server-side
 * secrets — never ship them to a browser or mobile bundle.
 */
class AuthenticateSalesChannel
{
    public function handle(Request $request, Closure $next): Response
    {
        $channel = SalesChannel::findByApiKey($request->header('X-Api-Key') ?: $request->bearerToken());

        if (! $channel || ! $channel->is_active) {
            return response()->json(['message' => 'Invalid or inactive API key.'], 401);
        }

        if (! $channel->last_used_at || $channel->last_used_at->lt(now()->subMinute())) {
            $channel->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('sales_channel', $channel);

        return $next($request);
    }
}
