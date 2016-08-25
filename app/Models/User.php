<?php

namespace App\Models;

class User extends UuidModel {
    public function checkins() {
        return $this->hasMany('App\Models\Checkin');
    }

    public function getMetaData() {
        return [
            'id' => $this->id,
            'fb_id' => $this->fb_id,
            'name' => $this->name,
            'avatar' => $this->avatar_url
        ];
    }

    public function isFirstTimeLogin() {
        return $this->is_first_login == 1;
    }
}
