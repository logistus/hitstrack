<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannerStat extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'banner_id',
        'banner_rotator_id',
        'event_type',
        'page_url',
        'ref_url',
        'ip_address',
        'device_type',
        'operating_system',
        'browser',
        'country_code',
    ];

    public function banner()
    {
        return $this->belongsTo(Banner::class);
    }

    public function rotator()
    {
        return $this->belongsTo(BannerRotator::class, 'banner_rotator_id');
    }
}
