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

    // Notification flow: get rooms with reminders, then POST scraped availability to trigger emails
    Route::get('/reminder-room-ids', [WebhookController::class, 'reminderRoomIds']);
    Route::post('/notify-availability', [WebhookController::class, 'notifyAvailability']);

    // Room details enrichment (nightly sync)
    Route::get('/room-slugs', [WebhookController::class, 'roomSlugs']);
    Route::post('/sync-room-details', [WebhookController::class, 'syncRoomDetails']);
});
