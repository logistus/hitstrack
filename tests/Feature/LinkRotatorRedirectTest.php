<?php

use App\Models\LinkRotator;
use App\Models\LinkTracker;
use App\Models\User;

test('link rotator skips a tracker when its target domain matches the referrer', function () {
    $user = User::factory()->create();

    $sameReferrerTracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'target_url' => 'https://source.example/landing',
        'tracker_slug' => 'same-referrer',
    ]);

    $alternateTracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'target_url' => 'https://alternate.example/offer',
        'tracker_slug' => 'alternate',
    ]);

    $rotator = LinkRotator::query()->create([
        'user_id' => $user->id,
        'rotator_slug' => 'rotator-skip-referrer',
        'rotation_type' => 'round_robin',
    ]);

    $rotator->trackers()->attach($sameReferrerTracker->id, ['weight' => 1, 'order_column' => 1]);
    $rotator->trackers()->attach($alternateTracker->id, ['weight' => 1, 'order_column' => 2]);

    $this->withHeader('referer', 'https://www.source.example/articles/one')
        ->get(route('linkrotators.redirect', $rotator->rotator_slug))
        ->assertRedirect($alternateTracker->target_url);

    $this->assertDatabaseHas('rotator_stats', [
        'rotator_id' => $rotator->id,
        'tracker_id' => $alternateTracker->id,
        'ref_url' => 'source.example',
    ]);

    $this->assertDatabaseHas('tracker_stats', [
        'tracker_id' => $alternateTracker->id,
        'rotator_id' => $rotator->id,
        'ref_url' => 'source.example',
    ]);
});

test('link rotator falls back to the matching tracker when no alternate exists', function () {
    $user = User::factory()->create();

    $tracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'target_url' => 'https://source.example/landing',
        'tracker_slug' => 'only-referrer',
    ]);

    $rotator = LinkRotator::query()->create([
        'user_id' => $user->id,
        'rotator_slug' => 'rotator-only-referrer',
        'rotation_type' => 'round_robin',
    ]);

    $rotator->trackers()->attach($tracker->id, ['weight' => 1, 'order_column' => 1]);

    $this->withHeader('referer', 'https://source.example/articles/one')
        ->get(route('linkrotators.redirect', $rotator->rotator_slug))
        ->assertRedirect($tracker->target_url);
});
