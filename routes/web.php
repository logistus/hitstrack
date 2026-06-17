<?php

use App\Http\Controllers\RotatorRedirectController;
use App\Http\Controllers\TrackerRedirectController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('t/{slug}', TrackerRedirectController::class)->name('trackers.redirect');
Route::get('r/{slug}', RotatorRedirectController::class)->name('rotators.redirect');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('trackers', 'pages::trackers')->name('trackers');
    Route::livewire('trackers/{slug}/stats', 'pages::tracker-stats')->name('trackers.stats');
    Route::livewire('rotators', 'pages::rotators')->name('rotators');
    Route::livewire('rotators/{slug}/stats', 'pages::rotator-stats')->name('rotators.stats');
});

require __DIR__ . '/settings.php';
