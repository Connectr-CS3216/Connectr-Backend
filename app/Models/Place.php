<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Place extends Model
{
    public $timestamps = false;

    public function checkins()
    {
        return $this->hasMany('App\Models\Checkin');
    }
}
