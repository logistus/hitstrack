<?php

use App\Models\User;
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
