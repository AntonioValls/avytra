<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the named "register" limiter (AppServiceProvider) to Fortify's POST /register.
 * Fortify offers no limiter option for registration and mutating its route at boot does not
 * survive route caching, so the limiter is attached here, inside the web group (docs/16).
 */
class ThrottleRegistration
{
    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register.store')) {
            return $next($request);
        }

        // Exactly three arguments: that is how ThrottleRequests recognises a named limiter.
        return $this->throttle->handle($request, $next, 'register');
    }
}
