<?php

namespace SergeyBruhin\NovaSupportRays;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;
use SergeyBruhin\NovaSupportRays\Client\ManagerClient;
use SergeyBruhin\NovaSupportRays\Models\IntegrationKey;
use SergeyBruhin\NovaSupportRays\Support\Authorization;
use SergeyBruhin\NovaSupportRays\Support\Settings;
use Throwable;

class NovaSupportRays extends Tool
{
    public function boot(): void
    {
        Nova::script('nova-supportrays', __DIR__.'/../dist/js/tool.js');
        Nova::style('nova-supportrays', __DIR__.'/../dist/css/tool.css');
    }

    public function menu(Request $request): mixed
    {
        return MenuSection::make((string) config('nova-supportrays.menu_label'))
            ->path('/supportrays')
            ->icon('ph-lifebuoy')
            ->withBadgeIf(fn (): string => (string) $this->awaitingReply($request), 'danger', fn (): bool => $this->awaitingReply($request) > 0);
    }

    public function authorize(Request $request): bool
    {
        return Authorization::canView($request);
    }

    /**
     * Tickets waiting for a staff reply, for the menu badge. Cached per user,
     * since the menu renders on every Nova page; any failure shows no badge
     * rather than breaking the menu.
     */
    private function awaitingReply(Request $request): int
    {
        $userId = $request->user()?->getKey();

        if ($userId === null) {
            return 0;
        }

        return (int) Cache::remember("nova-supportrays:awaiting:{$userId}", (int) config('nova-supportrays.badge_ttl', 60), function () use ($userId): int {
            $key = IntegrationKey::forUser($userId);

            if ($key === null || $key->isExpired() || ! Settings::configured()) {
                return 0;
            }

            try {
                // Short timeout: this runs inside a Nova page render.
                $response = (new ManagerClient(Settings::baseUrl(), 3))->forward($key->token, 'GET', 'tickets/stats');
            } catch (Throwable) {
                return 0;
            }

            return $response->successful() ? (int) $response->json('data.awaiting_reply') : 0;
        });
    }
}
