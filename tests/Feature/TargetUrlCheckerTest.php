<?php

use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('target url checker cannot be opened directly', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->get(route('target-url-checker'))
        ->assertRedirect(route('linktrackers', absolute: false));
});

test('target url checker blocks private network targets before fetching', function () {
    $user = \App\Models\User::factory()->create();

    Http::fake();

    Livewire::withQueryParams([
        'target_url' => 'http://127.0.0.1/admin',
        'add_link' => '1',
    ])
        ->actingAs($user)
        ->test('pages::target-url-checker')
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

    Livewire::withQueryParams([
        'target_url' => 'https://93.184.216.34/landing',
        'add_link' => '1',
    ])
        ->actingAs($user)
        ->test('pages::target-url-checker')
        ->assertSet('result.frame.status', 'danger')
        ->assertSee('X-Frame-Options is DENY');
});

test('a clean checker result can add the link tracker', function () {
    $user = \App\Models\User::factory()->create();

    Http::fake([
        'https://93.184.216.34/*' => Http::response('<html><body>Landing page</body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]),
    ]);

    Livewire::withQueryParams([
        'target_url' => 'https://93.184.216.34/landing',
        'tracker_name' => 'Campaign landing page',
        'add_link' => '1',
    ])
        ->actingAs($user)
        ->test('pages::target-url-checker')
        ->assertSet('result.status', 'safe')
        ->assertSee('Add Link')
        ->call('addLink')
        ->assertRedirect(route('linktrackers', absolute: false));

    $this->assertDatabaseHas('trackers', [
        'user_id' => $user->id,
        'tracker_name' => 'Campaign landing page',
        'target_url' => 'https://93.184.216.34/landing',
    ]);
});

test('a checker result with issues shows the link trackers return action instead of add link', function () {
    $user = \App\Models\User::factory()->create();

    Http::fake([
        'https://93.184.216.34/*' => Http::response('<html><body>Landing page</body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'DENY',
        ]),
    ]);

    Livewire::withQueryParams([
        'target_url' => 'https://93.184.216.34/landing',
        'tracker_name' => 'Campaign landing page',
        'add_link' => '1',
    ])
        ->actingAs($user)
        ->test('pages::target-url-checker')
        ->assertSet('result.status', 'danger')
        ->assertDontSee('Add Link')
        ->assertSee('Link Trackers');

    $this->assertDatabaseMissing('trackers', [
        'user_id' => $user->id,
        'target_url' => 'https://93.184.216.34/landing',
    ]);
});

test('a clean checker result can update an existing link tracker url', function () {
    $user = \App\Models\User::factory()->create();
    $tracker = \App\Models\LinkTracker::query()->create([
        'user_id' => $user->id,
        'tracker_slug' => 'abc123',
        'tracker_name' => 'Old campaign',
        'target_url' => 'https://example.com/old-page',
    ]);

    Http::fake([
        'https://93.184.216.34/*' => Http::response('<html><body>Landing page</body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]),
    ]);

    Livewire::withQueryParams([
        'target_url' => 'https://93.184.216.34/new-page',
        'tracker_name' => 'New campaign',
        'tracker_id' => (string) $tracker->id,
        'add_link' => '1',
    ])
        ->actingAs($user)
        ->test('pages::target-url-checker')
        ->assertSet('result.status', 'safe')
        ->assertSee('Update Link')
        ->call('addLink')
        ->assertRedirect(route('linktrackers', absolute: false));

    $this->assertDatabaseHas('trackers', [
        'id' => $tracker->id,
        'tracker_name' => 'New campaign',
        'target_url' => 'https://93.184.216.34/new-page',
    ]);
});
