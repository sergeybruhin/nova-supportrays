<?php

namespace SergeyBruhin\NovaSupportRays\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SergeyBruhin\NovaSupportRays\Exceptions\SupportRaysUnavailable;

/**
 * Transport over the SupportRays manager API (`/org/*`). Login calls go
 * without a token; everything else carries the Nova user's integration key.
 *
 * Returns raw responses: the tool relays SupportRays' status and JSON to the
 * browser as they are, so validation messages reach the form unchanged.
 */
class ManagerClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    /** Emails a login code to the manager, if the address belongs to one. */
    public function requestCode(string $email): Response
    {
        return $this->send($this->http(), 'POST', 'login/request', ['email' => $email]);
    }

    /**
     * Trades the code for a long-lived key for one project. On success the
     * body holds `token` and `integration_key` (`id`, `expires_at`, …).
     */
    public function issueKey(string $email, string $code, string $name, string $projectUuid): Response
    {
        return $this->send($this->http(), 'POST', 'login/verify', [
            'email' => $email,
            'code' => $code,
            'integration' => ['name' => $name, 'project_uuid' => $projectUuid],
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    public function forward(string $token, string $method, string $path, array $query = [], array $body = [], ?string $idempotencyKey = null): Response
    {
        $request = $this->http()->withToken($token);

        if ($idempotencyKey !== null) {
            $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        if ($query !== []) {
            $request->withQueryParameters($query);
        }

        return $this->send($request, $method, $path, $body);
    }

    /** @param  array<string, mixed>  $body */
    private function send(PendingRequest $request, string $method, string $path, array $body = []): Response
    {
        try {
            return $request->send($method, $this->url($path), $method === 'GET' || $method === 'DELETE' ? [] : ['json' => $body]);
        } catch (ConnectionException $exception) {
            throw new SupportRaysUnavailable("SupportRays {$method} {$path}: ".$exception->getMessage(), previous: $exception);
        }
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->timeout($this->timeout);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/org/'.ltrim($path, '/');
    }
}
