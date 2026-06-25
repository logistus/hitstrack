<?php

use App\Models\User;
use Livewire\Livewire;

test('a link tracker can be created with an optional name', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::linktrackers')
        ->set('tracker_name', 'Campaign landing page')
        ->set('target_url', 'https://example.com/landing-page')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('trackers', [
        'user_id' => $user->id,
        'tracker_name' => 'Campaign landing page',
        'target_url' => 'https://example.com/landing-page',
    ]);
});
