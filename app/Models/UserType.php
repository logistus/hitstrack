<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    protected $fillable = [
        'name',
        'label',
        'max_link_trackers',
        'max_link_rotators',
        'max_banner_trackers',
        'max_banner_rotators',
    ];
}
