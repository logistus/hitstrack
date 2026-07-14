<?php

use App\Models\Banner;
use App\Models\BannerRotator;
use App\Models\BannerStat;
use App\Models\LinkRotator;
use App\Models\LinkRotatorStat;
use App\Models\LinkTracker;
use App\Models\LinkTrackerStat;
use App\Models\User;
use Livewire\Livewire;

test('dashboard recent traffic shows tracker and rotator source urls', function () {
    $user = User::factory()->create();
    $tracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'target_url' => 'https://target.example',
        'tracker_slug' => 'link-source',
    ]);
    $linkRotator = LinkRotator::query()->create([
        'user_id' => $user->id,
        'rotator_slug' => 'link-rotator-source',
        'rotation_type' => 'round_robin',
    ]);

    LinkTrackerStat::query()->create([
        'tracker_id' => $tracker->id,
        'ip_address' => '192.0.2.1',
    ]);
    LinkRotatorStat::query()->create([
        'rotator_id' => $linkRotator->id,
        'tracker_id' => $tracker->id,
        'ip_address' => '192.0.2.2',
    ]);

    $banner = Banner::query()->create([
        'user_id' => $user->id,
        'name' => 'Source banner',
        'banner_slug' => 'banner-source',
        'target_url' => 'https://banner-target.example',
        'image_url' => 'https://cdn.example/banner.gif',
    ]);
    $bannerRotator = BannerRotator::query()->create([
        'user_id' => $user->id,
        'rotator_slug' => 'banner-rotator-source',
        'rotation_type' => 'round_robin',
    ]);

    BannerStat::query()->create([
        'banner_id' => $banner->id,
        'event_type' => 'impression',
    ]);
    BannerStat::query()->create([
        'banner_id' => $banner->id,
        'banner_rotator_id' => $bannerRotator->id,
        'event_type' => 'impression',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSee(route('linktrackers.redirect', $tracker->tracker_slug))
        ->assertSee(route('linkrotators.redirect', $linkRotator->rotator_slug))
        ->assertSee(route('bannertrackers.image', $banner->banner_slug))
        ->assertSee(route('bannerrotators.image', $bannerRotator->rotator_slug));
});
