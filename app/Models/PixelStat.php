<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PixelStat extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'page_url',
        'ref_url',
        'ip_address',
        'device_type',
        'operating_system',
        'browser',
    ];
}
