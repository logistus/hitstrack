<?php

use App\Models\Banner;
use App\Models\BannerRotator;
use App\Models\BannerStat;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('banner image tracker returns proxied image content instead of redirecting', function () {
    Http::fake([
        'https://cdn.example/banner.jpg' => Http::response('jpeg-bytes', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $user = User::factory()->create();
    $banner = Banner::create([
        'user_id' => $user->id,
        'name' => 'Demo banner',
        'banner_slug' => 'abc123',
        'target_url' => 'https://example.com',
        'image_url' => 'https://cdn.example/banner.jpg',
    ]);

    $this->get(route('bannertrackers.image', $banner->banner_slug))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Banner-Image-Cache', 'MISS')
        ->assertSee('jpeg-bytes', false);

    expect(BannerStat::query()->where('banner_id', $banner->id)->where('event_type', 'impression')->count())->toBe(1);
});

test('banner image tracker caches proxied image content on the server', function () {
    Cache::clear();

    Http::fake([
        'https://cdn.example/cached-banner.png' => Http::response('png-bytes', 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    $user = User::factory()->create();
    $banner = Banner::create([
        'user_id' => $user->id,
        'name' => 'Cached banner',
        'banner_slug' => 'cache1',
        'target_url' => 'https://example.com',
        'image_url' => 'https://cdn.example/cached-banner.png',
    ]);

    $this->get(route('bannertrackers.image', $banner->banner_slug))
        ->assertOk()
        ->assertHeader('X-Banner-Image-Cache', 'MISS')
        ->assertSee('png-bytes', false);

    $this->get(route('bannertrackers.image', $banner->banner_slug))
        ->assertOk()
        ->assertHeader('X-Banner-Image-Cache', 'HIT')
        ->assertSee('png-bytes', false);

    Http::assertSentCount(1);

    expect(BannerStat::query()->where('banner_id', $banner->id)->where('event_type', 'impression')->count())->toBe(2);
});

test('banner rotator image returns proxied image content instead of redirecting', function () {
    Http::fake([
        'https://cdn.example/rotator-banner.gif' => Http::response('gif-bytes', 200, [
            'Content-Type' => 'image/gif',
        ]),
    ]);

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
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif')
        ->assertHeader('X-Banner-Image-Cache', 'BYPASS')
        ->assertSee('gif-bytes', false);

    expect(BannerStat::query()
        ->where('banner_id', $banner->id)
        ->where('banner_rotator_id', $rotator->id)
        ->where('event_type', 'impression')
        ->count())->toBe(1);
});

test('banner rotator image does not use tracker image cache', function () {
    Cache::clear();

    Http::fake([
        'https://cdn.example/rotator-dynamic.gif' => Http::sequence()
            ->push('first-gif-bytes', 200, ['Content-Type' => 'image/gif'])
            ->push('second-gif-bytes', 200, ['Content-Type' => 'image/gif']),
    ]);

    $user = User::factory()->create();
    $banner = Banner::create([
        'user_id' => $user->id,
        'name' => 'Dynamic rotator banner',
        'banner_slug' => 'dyn123',
        'target_url' => 'https://example.com',
        'image_url' => 'https://cdn.example/rotator-dynamic.gif',
    ]);
    $rotator = BannerRotator::create([
        'user_id' => $user->id,
        'name' => 'Dynamic rotator',
        'rotator_slug' => 'dynrot',
        'rotation_type' => 'round_robin',
    ]);

    $rotator->banners()->attach($banner->id, ['weight' => 1, 'order_column' => 1]);

    $this->get(route('bannerrotators.image', $rotator->rotator_slug))
        ->assertOk()
        ->assertHeader('X-Banner-Image-Cache', 'BYPASS')
        ->assertSee('first-gif-bytes', false);

    $this->get(route('bannerrotators.image', $rotator->rotator_slug))
        ->assertOk()
        ->assertHeader('X-Banner-Image-Cache', 'BYPASS')
        ->assertSee('second-gif-bytes', false);

    Http::assertSentCount(2);
});

test('banner image tracker rejects non image responses', function () {
    Http::fake([
        'https://cdn.example/not-image' => Http::response('<html>nope</html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]),
    ]);

    $user = User::factory()->create();
    $banner = Banner::create([
        'user_id' => $user->id,
        'name' => 'Bad banner',
        'banner_slug' => 'bad123',
        'target_url' => 'https://example.com',
        'image_url' => 'https://cdn.example/not-image',
    ]);

    $this->get(route('bannertrackers.image', $banner->banner_slug))
        ->assertStatus(502)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});
