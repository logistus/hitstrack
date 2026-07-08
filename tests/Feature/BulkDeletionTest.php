<?php

use App\Models\Banner;
use App\Models\BannerRotator;
use App\Models\LinkRotator;
use App\Models\LinkTracker;
use App\Models\User;
use Livewire\Livewire;

test('bulk deletion only deletes selected resources owned by the user', function (
    string $component,
    string $model,
    string $selectionProperty,
    array $attributes,
) {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $slugKey = collect(['tracker_slug', 'banner_slug', 'rotator_slug'])
        ->first(fn (string $key) => array_key_exists($key, $attributes));
    $otherAttributes = $attributes;
    $otherAttributes[$slugKey] .= '-other';

    $owned = $model::query()->create([...$attributes, 'user_id' => $user->id]);
    $notOwned = $model::query()->create([...$otherAttributes, 'user_id' => $otherUser->id]);

    $this->actingAs($user);

    Livewire::test($component)
        ->set($selectionProperty, [$owned->id, $notOwned->id])
        ->call('deleteSelected')
        ->assertSet($selectionProperty, []);

    $this->assertModelMissing($owned);
    $this->assertModelExists($notOwned);
})->with([
    'link trackers' => [
        'pages::linktrackers',
        LinkTracker::class,
        'selectedTrackerIds',
        ['tracker_slug' => 'owned-tracker', 'target_url' => 'https://example.com'],
    ],
    'link rotators' => [
        'pages::linkrotators',
        LinkRotator::class,
        'selectedRotatorIds',
        ['rotator_slug' => 'owned-link-rotator', 'rotation_type' => 'round_robin'],
    ],
    'banner trackers' => [
        'pages::bannertrackers',
        Banner::class,
        'selectedBannerIds',
        [
            'name' => 'Banner',
            'banner_slug' => 'owned-banner',
            'target_url' => 'https://example.com',
            'image_url' => 'https://example.com/banner.png',
        ],
    ],
    'banner rotators' => [
        'pages::bannerrotators',
        BannerRotator::class,
        'selectedRotatorIds',
        ['rotator_slug' => 'owned-banner-rotator', 'rotation_type' => 'round_robin'],
    ],
]);
