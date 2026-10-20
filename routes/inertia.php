<?php

use Illuminate\Support\Facades\Route;

/*
 * The tool's pages inside Nova. Nova::router() has already applied the Nova
 * path prefix and domain, so these are /nova/supportrays/….
 */
Route::get('/', static fn () => inertia('NovaSupportRaysInbox'));
Route::get('/tickets/{uuid}', static fn (string $uuid) => inertia('NovaSupportRaysTicket', ['uuid' => $uuid]))
    ->whereUuid('uuid');
