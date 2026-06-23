<?php

use App\Http\Controllers\BannerClickRedirectController;
use App\Http\Controllers\BannerImageController;
use App\Http\Controllers\BannerRotatorClickRedirectController;
use App\Http\Controllers\BannerRotatorImageController;
use App\Http\Controllers\DataCroveController;
use App\Http\Controllers\LinkRotatorRedirectController;
use App\Http\Controllers\LinkTrackerRedirectController;
use App\Http\Controllers\PixelTrackingController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('t/{slug}', LinkTrackerRedirectController::class)->name('linktrackers.redirect');
Route::get('r/{slug}', LinkRotatorRedirectController::class)->name('linkrotators.redirect');
Route::get('b/{slug}', BannerClickRedirectController::class)->name('bannertrackers.click');
Route::get('b/{slug}/image', BannerImageController::class)->name('bannertrackers.image');
Route::get('b/{slug}/image.{extension}', BannerImageController::class)
    ->whereIn('extension', ['jpg', 'jpeg', 'png', 'gif', 'webp'])
    ->name('bannertrackers.image.extension');
Route::get('br/{slug}', BannerRotatorClickRedirectController::class)->name('bannerrotators.click');
Route::get('br/{slug}/image', BannerRotatorImageController::class)->name('bannerrotators.image');
Route::get('br/{slug}/image.{extension}', BannerRotatorImageController::class)
    ->whereIn('extension', ['jpg', 'jpeg', 'png', 'gif', 'webp'])
    ->name('bannerrotators.image.extension');

Route::get('pixel', PixelTrackingController::class)->name('pixels.track');
Route::get('datacrove', DataCroveController::class)->name('datacrove');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('referrers', 'pages::all-referrers')->name('referrers');
    Route::livewire('linktrackers', 'pages::linktrackers')->name('linktrackers');
    Route::livewire('linktrackers/{slug}/stats', 'pages::linktracker-stats')->name('linktrackers.stats');
    Route::livewire('linkrotators', 'pages::linkrotators')->name('linkrotators');
    Route::livewire('linkrotators/{slug}/stats', 'pages::linkrotator-stats')->name('linkrotators.stats');
    Route::livewire('bannertrackers', 'pages::bannertrackers')->name('bannertrackers');
    Route::livewire('bannertrackers/{slug}/stats', 'pages::bannertracker-stats')->name('bannertrackers.stats');
    Route::livewire('bannerrotators', 'pages::bannerrotators')->name('bannerrotators');
    Route::livewire('bannerrotators/{slug}/stats', 'pages::bannerrotator-stats')->name('bannerrotators.stats');
});

require __DIR__.'/settings.php';
