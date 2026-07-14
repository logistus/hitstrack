<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkRotatorStat extends Model
{
    protected $table = 'rotator_stats';

    protected $fillable = [
        'rotator_id',
        'tracker_id',
        'ref_url',
        'ip_address',
    ];

    public function rotator()
    {
        return $this->belongsTo(LinkRotator::class, 'rotator_id');
    }

    public function tracker()
    {
        return $this->belongsTo(LinkTracker::class, 'tracker_id');
    }
}
