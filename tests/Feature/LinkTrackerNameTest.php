<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('a link tracker can be created with an optional name', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    config(['services.google_web_risk.key' => null]);

    Http::fake([
        'https://93.184.216.34/*' => Http::response('<html><body>Landing page</body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]),
    ]);

    Livewire::test('pages::linktrackers')
        ->set('tracker_name', 'Campaign landing page')
        ->set('target_url', 'https://93.184.216.34/landing-page')
        ->call('checkTargetUrl')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('trackers', [
        'user_id' => $user->id,
        'tracker_name' => 'Campaign landing page',
        'target_url' => 'https://93.184.216.34/landing-page',
    ]);
});

test('a link tracker cannot be created before the target url is approved', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::linktrackers')
        ->set('tracker_name', 'Campaign landing page')
        ->set('target_url', 'https://example.com/landing-page')
        ->call('save')
        ->assertHasErrors(['target_url']);

    $this->assertDatabaseMissing('trackers', [
        'user_id' => $user->id,
        'target_url' => 'https://example.com/landing-page',
    ]);
});
