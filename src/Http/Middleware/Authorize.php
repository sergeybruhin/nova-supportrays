<?php

namespace SergeyBruhin\NovaSupportRays\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SergeyBruhin\NovaSupportRays\Support\Authorization;

/** Every route behind the same check that decides whether the tool appears at all. */
class Authorize
{
    public function handle(Request $request, Closure $next): mixed
    {
        return Authorization::canView($request) ? $next($request) : abort(403);
    }
}
