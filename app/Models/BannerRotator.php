<?php

namespace App\Models;

use App\Models\Concerns\FiltersReferrerTarget;
use Illuminate\Database\Eloquent\Model;

class BannerRotator extends Model
{
    use FiltersReferrerTarget;
    protected $fillable = ['user_id', 'name', 'rotator_slug', 'rotation_type', 'current_banner_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function banners()
    {
        return $this->belongsToMany(Banner::class, 'banner_rotator_banner')
            ->withPivot(['weight', 'order_column'])
            ->withTimestamps();
    }

    public function currentBanner()
    {
        return $this->belongsTo(Banner::class, 'current_banner_id');
    }

    public function stats()
    {
        return $this->hasMany(BannerStat::class);
    }

    public function pickNextBanner(): ?Banner
    {
        $banners = $this->banners()->get();

        if ($banners->isEmpty()) {
            return null;
        }

        return match ($this->rotation_type) {
            'random' => $banners->random(),
            'weighted' => $this->pickWeighted($banners),
            default => $this->pickRoundRobin($banners),
        };
    }

    protected function pickRoundRobin($banners): Banner
    {
        $sorted = $banners->sortBy('pivot.order_column')->values();
        $lastStat = $this->stats()
            ->whereIn('banner_id', $sorted->pluck('id'))
            ->where('event_type', 'impression')
            ->latest('id')
            ->first();

        if (! $lastStat) {
            return $sorted->first();
        }

        $lastIndex = $sorted->search(fn($banner) => $banner->id === $lastStat->banner_id);

        return $lastIndex === false ? $sorted->first() : $sorted[($lastIndex + 1) % $sorted->count()];
    }

    protected function pickWeighted($banners): Banner
    {
        $total = $banners->sum('pivot.weight');
        $rand = mt_rand(1, $total);
        $cumulative = 0;

        foreach ($banners as $banner) {
            $cumulative += $banner->pivot->weight;
            if ($rand <= $cumulative) {
                return $banner;
            }
        }

        return $banners->first();
    }
}
