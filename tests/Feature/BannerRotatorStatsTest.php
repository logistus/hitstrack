<?php

use App\Models\BannerRotator;
use App\Models\User;
use Livewire\Livewire;

test('banner rotator stats page renders without client detail metrics', function () {
    $user = User::factory()->create();
    $rotator = BannerRotator::query()->create([
        'user_id' => $user->id,
        'name' => 'Stats rotator',
        'rotator_slug' => 'stats-rotator',
        'rotation_type' => 'round_robin',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::bannerrotator-stats', ['slug' => $rotator->rotator_slug])
        ->assertOk()
        ->assertSee('Banner rotator stats')
        ->assertDontSee('Unique Impressions')
        ->assertDontSee('Device Type');
});
