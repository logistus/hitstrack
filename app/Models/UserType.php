<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserType extends Model
{
    protected $fillable = [
        'label',
        'max_link_trackers',
        'max_link_rotators',
        'max_banner_trackers',
        'max_banner_rotators',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
