<?php

namespace SergeyBruhin\NovaSupportRays\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Who may open the tool. The host defines the `viewNovaSupportRays` gate;
 * without it every Nova user may, since SupportRays itself still decides
 * whether their email belongs to a manager.
 */
final class Authorization
{
    public const VIEW = 'viewNovaSupportRays';

    public static function canView(Request $request): bool
    {
        if ($request->user() === null) {
            return false;
        }

        if (! Gate::has(self::VIEW)) {
            return true;
        }

        return Gate::forUser($request->user())->check(self::VIEW);
    }
}
