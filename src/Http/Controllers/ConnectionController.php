<?php

namespace SergeyBruhin\NovaSupportRays\Http\Controllers;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SergeyBruhin\NovaSupportRays\Client\ManagerClient;
use SergeyBruhin\NovaSupportRays\Exceptions\SupportRaysUnavailable;
use SergeyBruhin\NovaSupportRays\Http\Controllers\Concerns\UsesIntegrationKey;
use SergeyBruhin\NovaSupportRays\Models\IntegrationKey;
use SergeyBruhin\NovaSupportRays\Support\Settings;

/**
 * Connecting a Nova user to their SupportRays manager account: a login code
 * goes to their Nova email, and the code is traded for a personal
 * integration key stored against the user.
 */
class ConnectionController extends Controller
{
    use UsesIntegrationKey;

    public function __construct(private readonly ManagerClient $client) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->state($request));
    }

    public function requestCode(Request $request): JsonResponse
    {
        abort_unless(Settings::configured(), 503, 'Лучи Поддержки не настроены.');

        try {
            $response = $this->client->requestCode((string) $request->user()->email);
        } catch (SupportRaysUnavailable) {
            return $this->unavailable();
        }

        return $this->relay($response);
    }

    public function verify(Request $request): JsonResponse
    {
        abort_unless(Settings::configured(), 503, 'Лучи Поддержки не настроены.');
        $request->validate(['code' => ['required', 'string', 'max:32']]);

        $email = (string) $request->user()->email;

        try {
            $response = $this->client->issueKey(
                $email,
                (string) $request->string('code')->trim(),
                (string) config('nova-supportrays.integration_name'),
                Settings::projectUuid(),
            );
        } catch (SupportRaysUnavailable) {
            return $this->unavailable();
        }

        if (! $response->successful()) {
            return $this->relay($response);
        }

        IntegrationKey::query()->updateOrCreate(['user_id' => $request->user()->getKey()], [
            'email' => $email,
            'key_id' => $response->json('integration_key.id'),
            'token' => (string) $response->json('token'),
            'expires_at' => $response->json('integration_key.expires_at'),
        ]);

        return response()->json($this->state($request));
    }

    /**
     * Forgets the key here. Revoking it in SupportRays needs a portal login,
     * so the page tells the user where to do that.
     */
    public function destroy(Request $request): JsonResponse
    {
        IntegrationKey::query()->where('user_id', $request->user()->getKey())->delete();

        return response()->json($this->state($request));
    }

    /** @return array<string, mixed> */
    private function state(Request $request): array
    {
        $key = $this->currentKey($request);

        return [
            'configured' => Settings::configured(),
            'connected' => $key !== null,
            'email' => $request->user()->email,
            'connected_as' => $key?->email,
            'expires_at' => $key?->expires_at?->toIso8601String(),
            'portal_url' => Settings::portalUrl(),
        ];
    }

    /**
     * SupportRays' answer as is, except 401/403: Nova's axios would read those
     * as the Nova session itself failing (see ProxyController).
     */
    private function relay(Response $response): JsonResponse
    {
        $status = in_array($response->status(), [401, 403], true) ? 422 : $response->status();

        return response()->json($response->json() ?? [], $status);
    }
}
