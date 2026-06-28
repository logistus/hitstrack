<?php

use App\Models\LinkRotator;
use App\Models\LinkRotatorStat;
use App\Models\LinkTracker;
use App\Models\LinkTrackerStat;
use App\Models\User;
use Illuminate\Support\Carbon;

test('all referrers combines direct tracker and rotator hits without double counting', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $tracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'target_url' => 'https://target.example',
        'tracker_slug' => 'tracker-one',
    ]);

    $rotator = LinkRotator::query()->create([
        'user_id' => $user->id,
        'rotator_slug' => 'rotator-one',
        'rotation_type' => 'round_robin',
    ]);

    LinkTrackerStat::query()->forceCreate([
        'tracker_id' => $tracker->id,
        'ref_url' => 'source.example',
        'ip_address' => '192.0.2.1',
        'created_at' => Carbon::parse('2026-06-01 09:00:00'),
    ]);
    LinkTrackerStat::query()->forceCreate([
        'tracker_id' => $tracker->id,
        'ref_url' => 'source.example',
        'ip_address' => '192.0.2.1',
        'created_at' => Carbon::parse('2026-06-02 10:00:00'),
    ]);

    LinkRotatorStat::query()->forceCreate([
        'rotator_id' => $rotator->id,
        'tracker_id' => $tracker->id,
        'ref_url' => 'source.example',
        'ip_address' => '192.0.2.2',
        'created_at' => Carbon::parse('2026-06-03 11:30:00'),
    ]);
    LinkTrackerStat::query()->forceCreate([
        'tracker_id' => $tracker->id,
        'rotator_id' => $rotator->id,
        'ref_url' => 'source.example',
        'ip_address' => '192.0.2.2',
        'created_at' => Carbon::parse('2026-06-04 12:00:00'),
    ]);

    $otherTracker = LinkTracker::query()->create([
        'user_id' => $otherUser->id,
        'target_url' => 'https://other-target.example',
        'tracker_slug' => 'other-tracker',
    ]);
    LinkTrackerStat::query()->forceCreate([
        'tracker_id' => $otherTracker->id,
        'ref_url' => 'private.example',
        'ip_address' => '192.0.2.3',
        'created_at' => Carbon::parse('2026-06-05 13:00:00'),
    ]);

    $this->actingAs($user)
        ->get(route('referrers'))
        ->assertOk()
        ->assertSee('All Referrers')
        ->assertSee('source.example')
        ->assertSeeInOrder(['Total Hits', '3', 'Unique Hits', '2'])
        ->assertSee('66.67%')
        ->assertSee('Unique Rate')
        ->assertSee('Last Hit')
        ->assertSee('2026-06-03 11:30:00')
        ->assertDontSee('Best Unique Rate')
        ->assertDontSee('private.example');
});
