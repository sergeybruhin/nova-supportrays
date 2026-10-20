# nova-supportrays

A [Laravel Nova 4](https://nova.laravel.com) tool for working [SupportRays](https://supportrays.ru)
(«Лучи Поддержки») helpdesk tickets without leaving the admin panel.

Every Nova user connects **their own** SupportRays manager account once. After that, each reply,
internal note, status change or assignment is made under that person's name, and nobody shares a
staff token. The host app can add its own records (the customer, their orders, deep links into its
Nova) to every ticket's sidebar.

Used by [qskn.ru](https://qskn.ru) and [peptera.ru](https://peptera.ru).

## Features

- **Inbox** at `/nova/supportrays`:
  - tabs «Ждут ответа», «Мои», «Без исполнителя» and «Все активные», with counts;
  - search, and filters for status, priority and «Просрочено» (SLA breached);
  - pagination; refreshes every 60 s.
- **Ticket page** at `/nova/supportrays/tickets/{uuid}`:
  - the thread, with internal notes highlighted, delivery marks («Не доставлено клиенту: …») and
    attachment downloads;
  - a reply or internal note, sent with an `Idempotency-Key` so a double click never posts twice;
  - status, priority, assignee, category, tags and watchers; the SLA timers; the change history;
  - the SupportRays client card, the ticket's custom fields and links;
  - the host's own sidebar cards (see [Host context](#host-context));
  - «Открыть в Лучах Поддержки»; refreshes every 20 s.
- **Menu badge:** tickets awaiting a reply, cached 60 s per user. The call has a 3 s timeout, so a
  slow SupportRays never holds up a Nova page.
- **Connecting:**
  - the login code is emailed to the Nova user's own address;
  - the personal integration key is stored encrypted (`nova_supportrays_keys`);
  - «Отключить» forgets it locally.

## Requirements

- PHP 8.2+ and Laravel Nova 4.x (built and tested on 4.35).
- SupportRays API **v0.2.0+**, which adds integration keys (`/org/login/verify` with `integration`)
  and `/org/tickets/stats`.
- Every Nova user who works tickets must be an active SupportRays manager in the organization,
  with **the same email** as in Nova.
- The `ph-lifebuoy` menu icon comes from a Phosphor icon set registered in the host's Nova. Without
  one, Nova shows its default icon.

## Install

```bash
composer require sergeybruhin/nova-supportrays
php artisan migrate        # creates nova_supportrays_keys
```

Register the tool in `app/Providers/NovaServiceProvider.php`:

```php
use SergeyBruhin\NovaSupportRays\NovaSupportRays;

public function tools()
{
    return [
        new NovaSupportRays,
    ];
}
```

Decide who may open it with the `viewNovaSupportRays` gate. Without the gate, every Nova user may,
and SupportRays still checks whether their email belongs to a manager.

```php
Gate::define('viewNovaSupportRays', fn ($user) => $user->isSupportStaff());
```

## Configure

The tool reads the same `SUPPORTRAYS_*` variables as a host integration that pushes tickets, so a
site that already has one needs nothing extra.

| Variable | Default | |
|---|---|---|
| `SUPPORTRAYS_BASE_URL` | `https://api.supportrays.ru/api/v1` | API root, including the version |
| `SUPPORTRAYS_PROJECT_UUID` | — | **Required.** The project the tool works with; every key is issued for it only |
| `SUPPORTRAYS_PORTAL_URL` | `https://supportrays.ru` | For «Открыть в Лучах Поддержки» links |
| `SUPPORTRAYS_INTEGRATION_NAME` | `APP_NAME Nova` | How the key is named in the manager's SupportRays profile |
| `SUPPORTRAYS_TIMEOUT` | `10` | Seconds per API call |

To change the menu label (`Лучи Поддержки`) or the badge cache time, publish the config:

```bash
php artisan vendor:publish --provider="SergeyBruhin\NovaSupportRays\Providers\NovaSupportRaysServiceProvider" --tag=config
```

## Host context

Bind a `SergeyBruhin\NovaSupportRays\Contracts\TicketContextProvider` to put the host's own cards
and links in the ticket sidebar. It gets the SupportRays ticket and client, read with the current
user's key, so it only ever describes a ticket that user may see. Nothing comes from the browser.

```php
// AppServiceProvider::register()
$this->app->bind(TicketContextProvider::class, MyTicketContext::class);
```

```php
final class MyTicketContext implements TicketContextProvider
{
    public function forTicket(array $ticket, ?array $client): array
    {
        // $ticket['external_ref'] / $client['external_id'] are the ids your integration sent.
        $customer = Customer::find((int) Str::after($client['external_id'] ?? '', 'customer:'));

        return [
            'sections' => [[
                'title' => 'Клиент',
                'rows' => [
                    ['label' => 'Заказов', 'value' => $customer?->orders()->count(), 'url' => "/nova/resources/customers/{$customer?->id}"],
                ],
            ]],
            'links' => [
                ['title' => 'Клиент в Nova', 'url' => "/nova/resources/customers/{$customer?->id}"],
            ],
        ];
    }
}
```

Paths starting with `/` open inside Nova; other `http(s)` links open in a new tab.

## How it works

```
Nova page (Vue) ──► /nova-vendor/supportrays/api/{path} ──► ManagerClient ──► SupportRays /api/v1/org/{path}
                    nova + auth + viewNovaSupportRays        the user's own integration key
```

- **Connect:**
  1. `POST /nova-vendor/supportrays/connection/code` asks SupportRays (`/org/login/request`) to
     email a code.
  2. `POST /nova-vendor/supportrays/connection` trades it (`/org/login/verify` with
     `integration: {name, project_uuid}`) for a long-lived key bound to one project and to ticket
     work only.
- **Proxy:** only the ticket-work roots `me`, `tickets`, `users`, `tags`, `clients`, `projects` and
  `attachments` pass; attachment downloads stream through as files. Anything else is refused
  before it leaves the server.
- **Errors:**
  - a missing, expired or revoked key answers `409 not_connected`, and the page shows the connect
    screen again;
  - SupportRays' own 401/403 never reaches the browser as such, because Nova's front end would read
    them as its session expiring or its forbidden page;
  - SupportRays being down answers `503`, and the page keeps what it has.
- **Disconnect:** the key is deleted locally. Revoking it in SupportRays needs a full session
  login, so the user (or an organization owner) does that in the portal profile.

## Develop

```bash
npm install
npm run prod     # builds dist/, which is committed
```

The pages live in `resources/js/pages` (`Inbox.vue`, `Ticket.vue`) and use Nova's own components.
Feature tests run in the host app, with `Http::fake()` against `nova-supportrays.base_url`.

## Versioning

[SemVer](https://semver.org). Tags are `vX.Y.Z`; hosts require `^0.1`.

## License

MIT
