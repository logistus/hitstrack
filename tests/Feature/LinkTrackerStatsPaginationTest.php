<?php

use App\Models\LinkTracker;
use App\Models\LinkTrackerStat;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

test('link tracker referrers can move to the next page with database cache enabled', function () {
    config(['cache.default' => 'database']);
    Cache::clear();

    $user = User::factory()->create();
    $tracker = LinkTracker::query()->create([
        'user_id' => $user->id,
        'target_url' => 'https://target.example',
        'tracker_slug' => 'tracker-stats-pagination',
    ]);

    foreach (range(1, 26) as $index) {
        LinkTrackerStat::query()->create([
            'tracker_id' => $tracker->id,
            'ref_url' => "source-{$index}.example",
            'ip_address' => "192.0.2.{$index}",
        ]);
    }

    $this->actingAs($user);

    Livewire::test('pages::linktracker-stats', ['slug' => $tracker->tracker_slug])
        ->assertSee('source-1.example')
        ->call('nextPage', 'referrerPage')
        ->assertSee('source-9.example')
        ->assertDontSee('source-1.example');
});
