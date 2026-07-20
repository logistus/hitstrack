<?php

namespace App\Models;

use App\Models\Concerns\FiltersReferrerTarget;
use App\Support\ClientInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LinkRotator extends Model
{
    use FiltersReferrerTarget;
    protected $table = 'rotators';

    protected $fillable = ['user_id', 'rotator_name', 'rotator_slug', 'rotation_type'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trackers()
    {
        return $this->belongsToMany(LinkTracker::class, 'rotator_tracker', 'rotator_id', 'tracker_id')
            ->withPivot(['weight', 'order_column'])
            ->withTimestamps();
    }

    public function stats()
    {
        return $this->hasMany(LinkRotatorStat::class, 'rotator_id');
    }

    public function pickNextTracker(?string $refUrl = null): ?LinkTracker
    {
        $trackers = $this->trackers()->get();
        if ($trackers->isEmpty()) return null;

        $trackers = $this->withoutReferrerTarget($trackers, $refUrl);

        return match ($this->rotation_type) {
            'random' => $trackers->random(),
            'weighted' => $this->pickWeighted($trackers),
            default => $this->pickRoundRobin($trackers),
        };
    }

    protected function pickRoundRobin($trackers)
    {
        $sorted = $trackers->sortBy('pivot.order_column')->values();
        $lastStat = $this->stats()->latest('id')->first();

        if (! $lastStat) {
            return $sorted->first();
        }

        $lastIndex = $sorted->search(fn($t) => $t->id === $lastStat->tracker_id);

        return $lastIndex === false ? $sorted->first() : $sorted[($lastIndex + 1) % $sorted->count()];
    }

    protected function pickWeighted($trackers)
    {
        $total = $trackers->sum('pivot.weight');
        $rand = mt_rand(1, $total);
        $cumulative = 0;

        foreach ($trackers as $tracker) {
            $cumulative += $tracker->pivot->weight;
            if ($rand <= $cumulative) {
                return $tracker;
            }
        }

        return $trackers->first();
    }
}
