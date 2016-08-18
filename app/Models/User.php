<?php

namespace App\Models;

class User extends UuidModel {
    public function checkins() {
        return $this->hasMany('App\Models\Checkin');
    }
}
