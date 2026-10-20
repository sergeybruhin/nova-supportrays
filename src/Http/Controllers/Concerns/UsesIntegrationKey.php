<?php

namespace SergeyBruhin\NovaSupportRays\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SergeyBruhin\NovaSupportRays\Models\IntegrationKey;

trait UsesIntegrationKey
{
    /** The user's usable key; an expired one is forgotten on the way. */
    protected function currentKey(Request $request): ?IntegrationKey
    {
        $key = IntegrationKey::forUser($request->user()->getKey());

        if ($key?->isExpired()) {
            $key->delete();

            return null;
        }

        return $key;
    }

    /**
     * 409, not 401: Nova's axios turns a 401 into a redirect to its own login
     * page. The tool's pages show the connect screen on this code.
     */
    protected function notConnected(): JsonResponse
    {
        return response()->json([
            'message' => 'Подключите учётную запись Лучей Поддержки.',
            'code' => 'not_connected',
        ], 409);
    }

    protected function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'Лучи Поддержки сейчас недоступны. Попробуйте позже.'], 503);
    }
}
