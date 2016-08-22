<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    public $timestamps = false;

    public function place() {
        return $this->belongsTo('App\Models\Place');
    }
}
