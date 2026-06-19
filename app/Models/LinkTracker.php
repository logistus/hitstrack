<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkTracker extends Model
{
    protected $table = 'trackers';

    protected $fillable = [
        'user_id',
        'target_url',
        'tracker_slug',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stats()
    {
        return $this->hasMany(LinkTrackerStat::class, 'tracker_id');
    }

    public function rotators()
    {
        return $this->belongsToMany(LinkRotator::class, 'rotator_tracker', 'tracker_id', 'rotator_id')
            ->withPivot(['weight', 'order_column'])
            ->withTimestamps();
    }
}
