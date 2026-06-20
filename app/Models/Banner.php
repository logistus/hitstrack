<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'banner_slug',
        'target_url',
        'image_url',
        'alt_text',
        'width',
        'height',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stats()
    {
        return $this->hasMany(BannerStat::class);
    }

    public function rotators()
    {
        return $this->belongsToMany(BannerRotator::class, 'banner_rotator_banner')
            ->withPivot(['weight', 'order_column'])
            ->withTimestamps();
    }
}
