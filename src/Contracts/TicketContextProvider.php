<?php

namespace SergeyBruhin\NovaSupportRays\Contracts;

/**
 * What the host app knows about a ticket's customer, shown in the ticket
 * page's sidebar: its own records and deep links into its Nova.
 *
 * Bind an implementation in the container; without one the sidebar shows
 * only what SupportRays holds. Both arguments come from SupportRays, fetched
 * with the Nova user's key, so they only ever describe a ticket that user may
 * see.
 */
interface TicketContextProvider
{
    /**
     * @param  array<string, mixed>  $ticket  The SupportRays ticket (`external_ref`, `source`, `custom_fields`, …)
     * @param  array<string, mixed>|null  $client  The SupportRays client (`external_id`, `email`, `phone`, …)
     * @return array{
     *     sections?: list<array{title: string, rows: list<array{label: string, value: string|int|float|null, url?: string|null}>}>,
     *     links?: list<array{title: string, url: string}>
     * }
     */
    public function forTicket(array $ticket, ?array $client): array;
}
