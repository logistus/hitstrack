<?php

use App\Models\LinkRotator;
use App\Models\LinkRotatorStat;
use App\Models\LinkTracker;
use App\Models\LinkTrackerStat;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('all referrers combines direct tracker and rotator hits without double counting', function () {
    Carbon::setTestNow('2026-06-03 14:00:00');

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
        'created_at' => Carbon::parse('2026-06-03 09:00:00'),
    ]);
    LinkTrackerStat::query()->forceCreate([
        'tracker_id' => $tracker->id,
        'ref_url' => 'source.example',
        'ip_address' => '192.0.2.1',
        'created_at' => Carbon::parse('2026-06-03 10:00:00'),
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
        'created_at' => Carbon::parse('2026-06-03 12:00:00'),
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
        'created_at' => Carbon::parse('2026-06-03 13:00:00'),
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

test('all referrers keeps the last hit after raw hit records are pruned', function () {
    $user = User::factory()->create();
    $tracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'target_url' => 'https://target.example',
        'tracker_slug' => 'pruned-tracker',
    ]);

    DB::table('daily_link_referrer_stats')->insert([
        'stat_date' => '2026-06-01',
        'user_id' => $user->id,
        'tracker_id' => $tracker->id,
        'rotator_id' => null,
        'source_type' => 'tracker',
        'source_id' => $tracker->id,
        'ref_url' => 'archived.example',
        'ref_url_hash' => hash('sha256', 'archived.example'),
        'total_hits' => 4,
        'daily_unique_hits' => 3,
        'last_hit_at' => '2026-06-01 21:45:12',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('referrers'))
        ->assertOk()
        ->assertSee('archived.example')
        ->assertSee('2026-06-01 21:45:12');
});
