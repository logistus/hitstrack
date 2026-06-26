<?php

use App\Services\GA4Service;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('ga4 tracker click request includes measurement credentials in url', function () {
    config()->set('services.ga4.measurement_id', 'G-TEST123');
    config()->set('services.ga4.api_secret', 'secret-123');
    config()->set('services.ga4.enabled', true);
    config()->set('services.ga4.debug', false);

    Http::fake([
        'https://www.google-analytics.com/*' => Http::response('', 204),
    ]);

    expect(app(GA4Service::class)->sendTrackerClick([
        'client_id' => '1234567890.1234567890',
        'tracker_id' => 1,
        'destination' => 'https://hedef.com',
        'country' => 'TR',
        'device_type' => 'mobile',
        'browser' => 'chrome',
        'is_unique' => true,
        'click_id' => 999,
    ]))->toBeTrue();

    Http::assertSent(function (Request $request) {
        $url = parse_url($request->url());
        parse_str($url['query'] ?? '', $query);

        return ($url['scheme'] ?? null) === 'https'
            && ($url['host'] ?? null) === 'www.google-analytics.com'
            && ($url['path'] ?? null) === '/mp/collect'
            && ($query['measurement_id'] ?? null) === 'G-TEST123'
            && ($query['api_secret'] ?? null) === 'secret-123';
    });
});

test('ga4 debug request includes measurement credentials in url', function () {
    config()->set('services.ga4.measurement_id', 'G-DEBUG123');
    config()->set('services.ga4.api_secret', 'debug-secret');
    config()->set('services.ga4.enabled', true);
    config()->set('services.ga4.debug', true);

    Http::fake([
        'https://www.google-analytics.com/*' => Http::response([
            'validationMessages' => [],
        ], 200),
    ]);

    app(GA4Service::class)->sendTrackerClick([
        'client_id' => '1234567890.1234567890',
        'tracker_id' => 1,
        'destination' => 'https://hedef.com',
        'click_id' => 999,
    ]);

    Http::assertSent(function (Request $request) {
        $url = parse_url($request->url());
        parse_str($url['query'] ?? '', $query);

        return ($url['path'] ?? null) === '/debug/mp/collect'
            && ($query['measurement_id'] ?? null) === 'G-DEBUG123'
            && ($query['api_secret'] ?? null) === 'debug-secret';
    });
});
