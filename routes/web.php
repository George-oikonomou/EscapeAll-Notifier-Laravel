<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FavouriteController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// ── Public routes ──
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', [SearchController::class, 'search'])->name('search');
Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
Route::get('/rooms/{room}/availability', [RoomController::class, 'availability'])->name('rooms.availability');
Route::post('/rooms/{room}/refresh-availability', [RoomController::class, 'refreshAvailability'])->name('rooms.refresh-availability');
Route::get('/rooms/{room}/refresh-status', [RoomController::class, 'refreshStatus'])->name('rooms.refresh-status');
Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');

// ── Dashboard (redirect to home after login) ──
Route::get('/dashboard', function () {
    return redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

// ── Authenticated routes ──
Route::middleware('auth')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Favourites
    Route::get('/favourites', [FavouriteController::class, 'index'])->name('favourites.index');
    Route::post('/favourites/{room}/toggle', [FavouriteController::class, 'toggle'])->name('favourites.toggle');
    Route::get('/favourites/{room}/check', [FavouriteController::class, 'check'])->name('favourites.check');

    // Reminders
    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('/reminders/{room}', [ReminderController::class, 'store'])->name('reminders.store');
    Route::post('/reminders/{room}/toggle', [ReminderController::class, 'toggle'])->name('reminders.toggle');
    Route::delete('/reminders/{room}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
});

require __DIR__.'/auth.php';

