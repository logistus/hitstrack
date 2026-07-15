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
