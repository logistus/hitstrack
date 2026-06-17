<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RotatorStat extends Model
{
    protected $fillable = [
        'rotator_id',
        'tracker_id',
        'ref_url',
        'ip_address',
        'device_type',
        'operating_system',
        'browser',
    ];

    public function rotator()
    {
        return $this->belongsTo(Rotator::class);
    }

    public function tracker()
    {
        return $this->belongsTo(Tracker::class);
    }
}
