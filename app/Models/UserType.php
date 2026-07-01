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

    protected function casts(): array
    {
        return [
            'max_link_trackers' => 'integer',
            'max_link_rotators' => 'integer',
            'max_banner_trackers' => 'integer',
            'max_banner_rotators' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
