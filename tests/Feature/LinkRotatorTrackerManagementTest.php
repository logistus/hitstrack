<?php

use App\Models\LinkRotator;
use App\Models\LinkTracker;
use App\Models\User;
use Livewire\Livewire;

test('round robin tracker management hides weight and increments the next order', function () {
    $user = User::factory()->create();

    $firstTracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'target_url' => 'https://first.example',
        'tracker_slug' => 'first-tracker',
    ]);

    $secondTracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'target_url' => 'https://second.example',
        'tracker_slug' => 'second-tracker',
    ]);

    $rotator = LinkRotator::query()->create([
        'user_id' => $user->id,
        'rotator_slug' => 'round-robin-rotator',
        'rotation_type' => 'round_robin',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::linkrotators')
        ->call('manageTrackers', $rotator->id)
        ->assertSet('order_column', 1)
        ->assertSee('Used by round robin. Lower values run first; equal values fall back to the default record order.')
        ->assertDontSee('Used by weighted rotation. Higher values receive a larger share of visits.')
        ->set('tracker_id', (string) $firstTracker->id)
        ->call('saveTracker')
        ->assertHasNoErrors()
        ->assertSet('order_column', 2)
        ->set('tracker_id', (string) $secondTracker->id)
        ->call('saveTracker')
        ->assertHasNoErrors()
        ->assertSet('order_column', 3);

    $this->assertDatabaseHas('rotator_tracker', [
        'rotator_id' => $rotator->id,
        'tracker_id' => $firstTracker->id,
        'weight' => 1,
        'order_column' => 1,
    ]);

    $this->assertDatabaseHas('rotator_tracker', [
        'rotator_id' => $rotator->id,
        'tracker_id' => $secondTracker->id,
        'weight' => 1,
        'order_column' => 2,
    ]);

    $orderedTargetUrls = $rotator->trackers()
        ->orderBy('rotator_tracker.created_at', 'desc')
        ->orderBy('rotator_tracker.id', 'desc')
        ->pluck('target_url')
        ->all();

    expect($orderedTargetUrls)->toBe([
        'https://second.example',
        'https://first.example',
    ]);
});

test('weighted tracker management shows weight instead of order', function () {
    $user = User::factory()->create();

    $rotator = LinkRotator::query()->create([
        'user_id' => $user->id,
        'rotator_slug' => 'weighted-rotator',
        'rotation_type' => 'weighted',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::linkrotators')
        ->call('manageTrackers', $rotator->id)
        ->assertSee('Used by weighted rotation. Higher values receive a larger share of visits.')
        ->assertDontSee('Used by round robin. Lower values run first; equal values fall back to the default record order.');
});
