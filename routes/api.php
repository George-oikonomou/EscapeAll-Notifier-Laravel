<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook API Routes
|--------------------------------------------------------------------------
|
| These routes receive scraped data from GitHub Actions and upsert it
| into the database. All routes are guarded by the VerifyWebhookSecret
| middleware which checks the X-Webhook-Secret header.
|
*/

Route::middleware('webhook.secret')->prefix('webhook')->group(function () {
    Route::post('/sync-companies', [WebhookController::class, 'syncCompanies']);
    Route::post('/sync-rooms', [WebhookController::class, 'syncRooms']);
    Route::post('/sync-availability', [WebhookController::class, 'syncAvailability']);

    // Lightweight endpoint to list room external IDs (used by availability orchestrator)
    Route::get('/room-ids', [WebhookController::class, 'roomIds']);
});
