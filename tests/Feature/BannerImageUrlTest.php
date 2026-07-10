<?php

test('banner tracker and rotator image urls use gif-style slugs', function () {
    expect(route('bannertrackers.image', 'abc123', false))->toBe('/b/abc123.gif')
        ->and(route('bannerrotators.image', 'rot123', false))->toBe('/br/rot123.gif');
});

