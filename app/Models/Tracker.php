<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tracker extends Model
{
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
        return $this->hasMany(TrackerStat::class);
    }

    public function rotators()
    {
        return $this->belongsToMany(Rotator::class)
            ->withPivot(['weight', 'order_column'])
            ->withTimestamps();
    }
}
