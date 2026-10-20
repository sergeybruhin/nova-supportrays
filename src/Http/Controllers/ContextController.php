<?php

namespace SergeyBruhin\NovaSupportRays\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SergeyBruhin\NovaSupportRays\Client\ManagerClient;
use SergeyBruhin\NovaSupportRays\Contracts\TicketContextProvider;
use SergeyBruhin\NovaSupportRays\Exceptions\SupportRaysUnavailable;
use SergeyBruhin\NovaSupportRays\Http\Controllers\Concerns\UsesIntegrationKey;

/**
 * The host app's own cards and links for a ticket's sidebar.
 *
 * The ticket and its client are read from SupportRays with the user's key
 * rather than taken from the browser, so a user only ever gets context for a
 * ticket SupportRays lets them see.
 */
class ContextController extends Controller
{
    use UsesIntegrationKey;

    public function __construct(private readonly ManagerClient $client) {}

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        if (! app()->bound(TicketContextProvider::class)) {
            return response()->json(['sections' => [], 'links' => []]);
        }

        $key = $this->currentKey($request);

        if ($key === null) {
            return $this->notConnected();
        }

        try {
            $ticket = $this->client->forward($key->token, 'GET', "tickets/{$uuid}");

            if ($ticket->status() === 401) {
                $key->delete();

                return $this->notConnected();
            }

            if (! $ticket->successful()) {
                return response()->json(['sections' => [], 'links' => []]);
            }

            $clientUuid = $ticket->json('data.client.uuid');
            $client = $clientUuid ? $this->client->forward($key->token, 'GET', "clients/{$clientUuid}") : null;
        } catch (SupportRaysUnavailable) {
            return $this->unavailable();
        }

        $context = app(TicketContextProvider::class)->forTicket(
            (array) $ticket->json('data'),
            $client?->successful() ? (array) $client->json('data') : null,
        );

        return response()->json([
            'sections' => array_values($context['sections'] ?? []),
            'links' => array_values($context['links'] ?? []),
        ]);
    }
}
