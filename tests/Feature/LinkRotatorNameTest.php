<?php

use App\Models\User;
use Livewire\Livewire;

test('a link rotator can be created with an optional name', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::linkrotators')
        ->set('rotator_name', 'Offer rotation')
        ->set('rotation_type', 'round_robin')
        ->call('saveRotator')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('rotators', [
        'user_id' => $user->id,
        'rotator_name' => 'Offer rotation',
        'rotation_type' => 'round_robin',
    ]);
});
