<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('target url checker page renders for authenticated users', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get(route('target-url-checker'))
        ->assertOk()
        ->assertSee('Target URL Checker')
        ->assertSee('Check URL');
});

test('target url checker blocks private network targets before fetching', function () {
    $user = \App\Models\User::factory()->create();

    Http::fake();

    Livewire::actingAs($user)
        ->test('pages::target-url-checker')
        ->set('target_url', 'http://127.0.0.1/admin')
        ->call('check')
        ->assertSet('result.status', 'danger')
        ->assertSee('Private, local, or reserved network addresses cannot be checked.');

    Http::assertNothingSent();
});

test('target url checker detects iframe blocking headers', function () {
    $user = \App\Models\User::factory()->create();

    Http::fake([
        'https://93.184.216.34/*' => Http::response('<html><body>Landing page</body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'DENY',
        ]),
    ]);

    Livewire::actingAs($user)
        ->test('pages::target-url-checker')
        ->set('target_url', 'https://93.184.216.34/landing')
        ->call('check')
        ->assertSet('result.frame.status', 'danger')
        ->assertSee('X-Frame-Options is DENY');
});

test('target url checker can include google web risk reputation results', function () {
    $user = \App\Models\User::factory()->create();

    config(['services.google_web_risk.key' => 'test-key']);

    Http::fake([
        'https://93.184.216.34/*' => Http::response('<html><body>Landing page</body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]),
        'https://webrisk.googleapis.com/*' => Http::response([
            'threat' => [
                'threatTypes' => ['MALWARE'],
            ],
        ], 200),
    ]);

    Livewire::actingAs($user)
        ->test('pages::target-url-checker')
        ->set('target_url', 'https://93.184.216.34/landing')
        ->call('check')
        ->assertSet('result.reputation.status', 'danger')
        ->assertSee('Google Web Risk matched this URL as');
});
