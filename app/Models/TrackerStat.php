<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackerStat extends Model
{
    const UPDATED_AT = null; // updated_at sütunu yok, Eloquent güncellemeye çalışmasın

    protected $fillable = [
        'tracker_id',
        'rotator_id',
        'ref_url',
        'ip_address',
        'device_type',
        'operating_system',
        'browser',
    ];

    public function tracker()
    {
        return $this->belongsTo(Tracker::class);
    }

    public function rotator()
    {
        return $this->belongsTo(Rotator::class);
    }
}
