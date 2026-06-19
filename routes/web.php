<?php

use App\Http\Controllers\LinkRotatorRedirectController;
use App\Http\Controllers\LinkTrackerRedirectController;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('t/{slug}', LinkTrackerRedirectController::class)->name('linktrackers.redirect');
Route::get('r/{slug}', LinkRotatorRedirectController::class)->name('linkrotators.redirect');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('linktrackers', 'pages::linktrackers')->name('linktrackers');
    Route::livewire('linktrackers/{slug}/stats', 'pages::linktracker-stats')->name('linktrackers.stats');
    Route::livewire('linkrotators', 'pages::linkrotators')->name('linkrotators');
    Route::livewire('linkrotators/{slug}/stats', 'pages::linkrotator-stats')->name('linkrotators.stats');
});

require __DIR__ . '/settings.php';
