<?php

use App\Http\Controllers\LogWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes here are stateless (no session/CSRF).
| Authentication is handled per-route (e.g. token check in controller).
|
| Base URL: /api
|--------------------------------------------------------------------------
*/

// Log webhook — accepts POSTs from external apps (Laravel, Go, etc.)
Route::post('/logs/{projectId}', [LogWebhookController::class, 'store'])
    ->name('api.logs.store');
