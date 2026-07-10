<?php

use App\Models\User;
use App\Models\LinkTracker;
use Livewire\Livewire;

test('creating a link tracker opens the target url checker first', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::linktrackers')
        ->set('tracker_name', 'Campaign landing page')
        ->set('target_url', 'https://93.184.216.34/landing-page')
        ->call('save')
        ->assertRedirectContains(route('target-url-checker', absolute: false));

    $this->assertDatabaseMissing('trackers', [
        'user_id' => $user->id,
        'target_url' => 'https://93.184.216.34/landing-page',
    ]);
});

test('editing only the link tracker name does not require the target url checker', function () {
    $user = User::factory()->create();
    $tracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'tracker_slug' => 'abc123',
        'tracker_name' => 'Old name',
        'target_url' => 'https://example.com/landing-page',
    ]);

    Livewire::actingAs($user)
        ->test('pages::linktrackers')
        ->set('editingTrackerId', $tracker->id)
        ->set('tracker_name', 'New name')
        ->set('target_url', 'https://example.com/landing-page')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('trackers', [
        'id' => $tracker->id,
        'tracker_name' => 'New name',
        'target_url' => 'https://example.com/landing-page',
    ]);
});

test('editing the target url opens the target url checker first', function () {
    $user = User::factory()->create();
    $tracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'tracker_slug' => 'abc123',
        'tracker_name' => 'Campaign landing page',
        'target_url' => 'https://example.com/old-page',
    ]);

    Livewire::actingAs($user)
        ->test('pages::linktrackers')
        ->set('editingTrackerId', $tracker->id)
        ->set('tracker_name', 'Campaign landing page')
        ->set('target_url', 'https://93.184.216.34/new-page')
        ->call('save')
        ->assertRedirectContains(route('target-url-checker', absolute: false));

    $this->assertDatabaseHas('trackers', [
        'id' => $tracker->id,
        'target_url' => 'https://example.com/old-page',
    ]);
});
