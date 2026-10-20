<?php

use Illuminate\Support\Facades\Route;
use SergeyBruhin\NovaSupportRays\Http\Controllers\ConnectionController;
use SergeyBruhin\NovaSupportRays\Http\Controllers\ContextController;
use SergeyBruhin\NovaSupportRays\Http\Controllers\ProxyController;

Route::get('/connection', [ConnectionController::class, 'show']);
Route::post('/connection/code', [ConnectionController::class, 'requestCode'])->middleware('throttle:6,1');
Route::post('/connection', [ConnectionController::class, 'verify'])->middleware('throttle:10,1');
Route::delete('/connection', [ConnectionController::class, 'destroy']);

Route::get('/context/{uuid}', ContextController::class)->whereUuid('uuid');

Route::match(['GET', 'POST', 'PATCH', 'DELETE'], '/api/{path}', ProxyController::class)->where('path', '.*');
