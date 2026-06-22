<?php

use App\Models\PixelStat;

test('pixel tracking stores referrer from query parameter before request header', function () {
    $this->withHeader('Referer', 'https://landing.example/articles/demo')
        ->get(route('pixels.track', [
            'page_url' => 'https://landing.example/articles/demo',
            'ref_url' => 'https://source.example/campaign?utm=one',
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    $stat = PixelStat::query()->firstOrFail();

    expect($stat->page_url)->toBe('https://landing.example/articles/demo')
        ->and($stat->ref_url)->toBe('source.example');
});

test('pixel tracking falls back to request referrer header', function () {
    $this->withHeader('Referer', 'https://landing.example/articles/demo')
        ->get(route('pixels.track'))
        ->assertOk();

    expect(PixelStat::query()->firstOrFail()->ref_url)->toBe('landing.example');
});

test('pixel tracking stores direct visit when referrer query parameter is empty', function () {
    $this->withHeader('Referer', 'https://datacrove.com/')
        ->get(route('pixels.track', [
            'page_url' => 'https://datacrove.com/',
            'ref_url' => '',
        ]))
        ->assertOk();

    expect(PixelStat::query()->firstOrFail()->ref_url)->toBeNull();
});
