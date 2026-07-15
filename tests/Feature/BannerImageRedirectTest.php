<?php

use App\Models\Banner;
use App\Models\BannerRotator;
use App\Models\BannerStat;
use App\Models\User;

test('banner image tracker redirects to the banner image url', function () {
    $user = User::factory()->create();
    $banner = Banner::create([
        'user_id' => $user->id,
        'name' => 'Demo banner',
        'banner_slug' => 'abc123',
        'target_url' => 'https://example.com',
        'image_url' => 'https://cdn.example/banner.jpg',
    ]);

    $this->get(route('bannertrackers.image', $banner->banner_slug))
        ->assertRedirect($banner->image_url);

    expect(BannerStat::query()->where('banner_id', $banner->id)->where('event_type', 'impression')->count())->toBe(1);
});

test('banner rotator image redirects to the selected banner image url', function () {
    $user = User::factory()->create();
    $banner = Banner::create([
        'user_id' => $user->id,
        'name' => 'Rotator banner',
        'banner_slug' => 'def456',
        'target_url' => 'https://example.com',
        'image_url' => 'https://cdn.example/rotator-banner.gif',
    ]);
    $rotator = BannerRotator::create([
        'user_id' => $user->id,
        'name' => 'Demo rotator',
        'rotator_slug' => 'rot123',
        'rotation_type' => 'round_robin',
    ]);

    $rotator->banners()->attach($banner->id, ['weight' => 1, 'order_column' => 1]);

    $this->get(route('bannerrotators.image.extension', [
        'slug' => $rotator->rotator_slug,
        'extension' => 'gif',
    ]))
        ->assertRedirect($banner->image_url)
        ->assertPlainCookie("banner_rotator_{$rotator->id}_selected_banner", (string) $banner->id);

    expect(BannerStat::query()
        ->where('banner_id', $banner->id)
        ->where('banner_rotator_id', $rotator->id)
        ->where('event_type', 'impression')
        ->count())->toBe(1);
});

test('banner rotator click uses the banner selected by the image cookie', function () {
    $user = User::factory()->create();
    $firstBanner = Banner::create([
        'user_id' => $user->id,
        'name' => 'First banner',
        'banner_slug' => 'first-banner',
        'target_url' => 'https://first-target.example',
        'image_url' => 'https://cdn.example/first.gif',
    ]);
    $secondBanner = Banner::create([
        'user_id' => $user->id,
        'name' => 'Second banner',
        'banner_slug' => 'second-banner',
        'target_url' => 'https://second-target.example',
        'image_url' => 'https://cdn.example/second.gif',
    ]);
    $rotator = BannerRotator::create([
        'user_id' => $user->id,
        'rotator_slug' => 'cookie-rotator',
        'rotation_type' => 'round_robin',
    ]);

    $rotator->banners()->attach([
        $firstBanner->id => ['weight' => 1, 'order_column' => 1],
        $secondBanner->id => ['weight' => 1, 'order_column' => 2],
    ]);

    $this->get(route('bannerrotators.image', $rotator->rotator_slug))
        ->assertRedirect($firstBanner->image_url);

    $cookieName = "banner_rotator_{$rotator->id}_selected_banner";

    $this->withUnencryptedCookie($cookieName, (string) $firstBanner->id)
        ->get(route('bannerrotators.click', $rotator->rotator_slug))
        ->assertRedirect($firstBanner->target_url);

    $this->assertDatabaseHas('banner_stats', [
        'banner_id' => $firstBanner->id,
        'banner_rotator_id' => $rotator->id,
        'event_type' => 'click',
    ]);
});
