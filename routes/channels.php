<?php

use App\Models\Rotator;
use App\Models\Tracker;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tracker-stats.{trackerId}', function ($user, $trackerId) {
    return Tracker::query()
        ->whereKey((int) $trackerId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('user-trackers.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('rotator-stats.{rotatorId}', function ($user, $rotatorId) {
    return Rotator::query()
        ->whereKey((int) $rotatorId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('user-rotators.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
