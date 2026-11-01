<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records when the signed-in user last used the service. It writes at most once every few minutes per user, and does
 * not touch updated_at, so it stays cheap.
 */
class RecordLastSeen
{
    /**
     * How long, in minutes, a recorded time is good enough before it is updated again.
     */
    public const RESOLUTION_MINUTES = 5;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinutes(self::RESOLUTION_MINUTES)))) {
            User::withoutTimestamps(fn () => $user->forceFill(['last_seen_at' => now()])->save());
        }

        return $next($request);
    }
}
