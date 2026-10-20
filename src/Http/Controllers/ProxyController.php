<?php

namespace SergeyBruhin\NovaSupportRays\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use SergeyBruhin\NovaSupportRays\Client\ManagerClient;
use SergeyBruhin\NovaSupportRays\Exceptions\SupportRaysUnavailable;
use SergeyBruhin\NovaSupportRays\Http\Controllers\Concerns\UsesIntegrationKey;

/**
 * Relays the tool's ticket work to `/org/*` with the Nova user's own key.
 *
 * Only ticket-work roots pass; an integration key can't reach anything else
 * in SupportRays anyway, and the allowlist keeps the proxy from becoming a
 * general relay. A rejected or expired key is forgotten, which sends the page
 * back to the connect screen.
 *
 * SupportRays' 401 and 403 never reach the browser as such: Nova's own axios
 * treats a 401 as an expired Nova session (redirect to login) and a 403 as a
 * forbidden page. Both arrive as 409 with a `code` the page handles.
 */
class ProxyController extends Controller
{
    use UsesIntegrationKey;

    /** @var list<string> */
    public const ALLOWED_ROOTS = ['me', 'tickets', 'users', 'tags', 'clients', 'projects', 'attachments'];

    public function __construct(private readonly ManagerClient $client) {}

    public function __invoke(Request $request, string $path): JsonResponse|Response
    {
        $segments = array_values(array_filter(explode('/', $path), fn (string $segment): bool => $segment !== ''));

        if ($segments === []
            || ! in_array($segments[0], self::ALLOWED_ROOTS, true)
            || array_filter($segments, fn (string $segment): bool => ! preg_match('/^[A-Za-z0-9_-]+$/', $segment)) !== []) {
            return response()->json(['message' => 'Unknown SupportRays route.'], 404);
        }

        $key = $this->currentKey($request);

        if ($key === null) {
            return $this->notConnected();
        }

        try {
            $upstream = $this->client->forward(
                $key->token,
                $request->method(),
                implode('/', $segments),
                $request->query(),
                $request->isMethod('GET') || $request->isMethod('DELETE') ? [] : $request->json()->all(),
                $request->header('Idempotency-Key'),
            );
        } catch (SupportRaysUnavailable) {
            return $this->unavailable();
        }

        if ($upstream->status() === 401) {
            $key->delete();

            return $this->notConnected();
        }

        if ($upstream->status() === 403) {
            return response()->json([
                'message' => $upstream->json('message') ?: 'Недостаточно прав в Лучах Поддержки.',
                'code' => $upstream->json('code') ?: 'forbidden',
            ], 409);
        }

        if ($upstream->status() === 204) {
            return response()->noContent();
        }

        // An attachment download is a file, not JSON: pass it through as one.
        if ($segments[0] === 'attachments' && $request->isMethod('GET') && $upstream->successful()) {
            return response($upstream->body(), 200, array_filter([
                'Content-Type' => $upstream->header('Content-Type') ?: 'application/octet-stream',
                'Content-Disposition' => $upstream->header('Content-Disposition') ?: null,
                'X-Content-Type-Options' => 'nosniff',
            ]));
        }

        return response()->json($upstream->json() ?? [], $upstream->status());
    }
}
