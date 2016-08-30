<?php

namespace App\Http\Controllers;

use App\Models\User;
use Facebook\Facebook;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class StatsController extends Controller
{
    public function index(Request $request, Facebook $fb)
    {
        $token = JWTAuth::setRequest($request)->getToken();
        $payload = JWTAuth::setToken($token)->parseToken()->getPayload();
        $accessToken = $payload['token'];
        $user = null;
        try {
            $user = User::where('id', $payload['userid'])->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'The request user does not exist'], 500);
        }

        return [
            'friends' => $this->getFriendStats($fb, $user->id, $accessToken),
            'global' => $this->getSiteStats()
        ];
    }

    private function getFriendStats(Facebook $fb, $userId, $accessToken) {
        $friendList = $this->getFriends($fb, $accessToken);
        $friendList[] = $userId; // add the user itself
        $mostVisitedCountries_friends = $this->getCountryStats(true, true, $friendList);
        $leastVisitedCountries_friends = $this->getCountryStats(false, true, $friendList);
        $mostVisitedPlaces_friends = $this->getPlaceStats(true, true, $friendList);
        $leastVisitedPlaces_friends = $this->getPlaceStats(false, true, $friendList);
        return [
            'country' => ['most' => $mostVisitedCountries_friends, 'least' => $leastVisitedCountries_friends],
            'place' => ['most' => $mostVisitedPlaces_friends, 'least' => $leastVisitedPlaces_friends]
        ];
    }

    private function getSiteStats()
    {

        $mostVisitedCountries = $this->getCountryStats(true, false);
        $leastVisitedCountries = $this->getCountryStats(false, false);
        $mostVisitedPlaces = $this->getPlaceStats(true, false);
        $leastVisitedPlaces = $this->getPlaceStats(false, false);

        return [
            'country' => ['most' => $mostVisitedCountries, 'least' => $leastVisitedCountries],
            'place' => ['most' => $mostVisitedPlaces, 'least' => $leastVisitedPlaces]
        ];
    }

    private function getCountryStats($desc, $friendsOnly, $friendList = null)
    {
        $query = DB::table('checkins')
            ->select('places.country', DB::raw('count(*) as visits_count'))
            ->join('places', 'checkins.place_id', '=', 'places.id');
        if ($friendsOnly) {
            $query = $query->whereIn('checkins.user_id', $friendList);
        }
        return $query->groupBy('places.country')
            ->orderBy('visits_count', $desc ? 'desc' : 'asc')
            ->limit(5)
            ->get();
    }

    private function getPlaceStats($desc, $friendsOnly, $friendList = null)
    {
        $query = DB::table('checkins')
            ->select('places.name', 'places.country', DB::raw('count(*) as visits_count'))
            ->join('places', 'checkins.place_id', '=', 'places.id');
        if ($friendsOnly) {
            $query = $query->whereIn('checkins.user_id', $friendList);
        }
        return $query->groupBy('places.id')
            ->orderBy('visits_count', $desc ? 'desc' : 'asc')
            ->limit(5)
            ->get();
    }

    private function getFriends(Facebook $fb, $accessToken)
    {
        $friends = $fb->get('me/friends', $accessToken)->getGraphEdge();
        $friendList = [];
        do {
            foreach ($friends as $friend) {
                $friendModel = User::where('fb_id', $friend->getField('id'))->first();
                if ($friendModel != null) {
                    $friendList[] = $friendModel->id;
                }
            }
        } while ($friends = $fb->next($friends));
        return $friendList;
    }
}
