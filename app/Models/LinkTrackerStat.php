<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkTrackerStat extends Model
{
    const UPDATED_AT = null; // updated_at sütunu yok, Eloquent güncellemeye çalışmasın

    protected $table = 'tracker_stats';

    protected $fillable = [
        'tracker_id',
        'rotator_id',
        'ref_url',
        'ip_address',
        'device_type',
        'operating_system',
        'browser',
        'country_code',
    ];

    public function tracker()
    {
        return $this->belongsTo(LinkTracker::class, 'tracker_id');
    }

    public function rotator()
    {
        return $this->belongsTo(LinkRotator::class, 'rotator_id');
    }
}
