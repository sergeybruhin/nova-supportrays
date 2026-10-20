<?php

/*
 * Reads the same SUPPORTRAYS_* variables as the host's own integration, so a
 * site that already pushes tickets needs no extra configuration.
 */
return [
    // The API root, including the version: https://api.supportrays.ru/api/v1
    'base_url' => env('SUPPORTRAYS_BASE_URL', 'https://api.supportrays.ru/api/v1'),

    // The manager portal, for «Открыть в Лучах Поддержки» links.
    'portal_url' => env('SUPPORTRAYS_PORTAL_URL', 'https://supportrays.ru'),

    // The one project the tool works with. A manager's integration key is
    // issued for this project only.
    'project_uuid' => env('SUPPORTRAYS_PROJECT_UUID'),

    // How the key is named in the manager's SupportRays profile.
    'integration_name' => env('SUPPORTRAYS_INTEGRATION_NAME', env('APP_NAME', 'Laravel').' Nova'),

    'timeout' => (int) env('SUPPORTRAYS_TIMEOUT', 10),

    // Sidebar label of the tool.
    'menu_label' => 'Лучи Поддержки',

    // How long the «ждут ответа» menu badge is cached per user, in seconds.
    'badge_ttl' => 60,
];
