<?php

namespace App\Models;

class User extends UuidModel {
    public function checkins() {
        return $this->hasMany('App\Models\Checkin');
    }

    public function getMetaData() {
        $checkins = $this->checkins();
        return [
            'id' => $this->id,
            'fb_id' => $this->fb_id,
            'name' => $this->name,
            'avatar' => $this->avatar_url,
            'total_checkins' => $checkins->count()
        ];
    }

    public function isFirstTimeLogin() {
        return $this->is_first_login == 1;
    }
}
