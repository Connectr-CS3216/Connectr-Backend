<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Place;
use App\Models\User;
use DateTime;
use Facebook\Facebook;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

use App\Http\Requests;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckinController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request $request
     * @param Facebook $fb
     * @return array|\Facebook\GraphNodes\GraphEdge|null
     */
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

        // Get request data format
        $format = $request->input('format');
        $useGeoJson = false;
        if ($format == 'geojson') {
            $useGeoJson = true;
        }

        $userId = $user->id;
        $isFirstTimeLogin = $user->is_first_login == 1;
        $lastLoginTime = $user->last_login_time;
        $lastLogin = new DateTime($lastLoginTime);

        // Check if this is the first time getting data
        // If YES, set the boolean to be false, and just pull all tagged_places.
        // If NO, get the last_login_time, and get all tagged_places that is created after last_login_time.
        $checkins = $fb->get('me/tagged_places?limit=250', $accessToken)->getGraphEdge();
        $hasLoadedAll = false;
        do {
            foreach ($checkins as $checkin) {
                // Checks if there is any new checkins that is created after the last login time.
                if (!$isFirstTimeLogin && $checkin->getField('created_time') < $lastLogin) {
                    $hasLoadedAll = true;
                    break;
                }

                $place = $checkin->getField('place');
                $location = $place->getField('location');
                if ($location->getField('latitude') === null || $location->getField('longitude') === null) {
                    // We have to skip checkins without geolocation.
                    continue;
                }
                $placeCreated = $this->createOrReturnExistingPlaceModel(
                    $place->getField('id'),
                    $place->getField('name'),
                    $location->getField('latitude'),
                    $location->getField('longitude'),
                    $location->getField('city', ''),
                    $location->getField('street', ''),
                    $location->getField('zip', ''),
                    $location->getField('country', ''));

                $this->createCheckinModel(
                    $checkin->getField('id'),
                    $userId,
                    $placeCreated->id,
                    $checkin->getField('created_time')
                );
            }
            if ($hasLoadedAll) {
                break;
            }
        } while ($checkins = $fb->next($checkins));
        $user->is_first_login = 0;
        $user->last_login_time = date('Y-m-d H:i:s');
        $user->save();
        $checkins = $user->checkins;
        $checkins->load('place');
        if ($useGeoJson) {
            $featuresArray = $checkins->map(function ($item, $key) {
                return $this->createGeoJsonResponse($item);
            });
            return $this->createGeoJsonFeaturesArray($featuresArray);
        }
        return $checkins;
    }

    /**
     * @param Request $request
     * @param Facebook $fb
     * @param  string $friendId
     * @return array|\Illuminate\Http\Response
     */
    public function getCheckinsByFriendId(Request $request, Facebook $fb, $friendId)
    {
        $token = JWTAuth::setRequest($request)->getToken();
        $payload = JWTAuth::setToken($token)->parseToken()->getPayload();
        $accessToken = $payload['token'];

        // Get request data format
        $format = $request->input('format');
        $useGeoJson = false;
        if ($format == 'geojson') {
            $useGeoJson = true;
        }

        $user = null;
        try {
            $user = User::where('id', $payload['userid'])->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'The request user does not exist'], 500);
        }

        // Make sure friend exists in the app.
        try {
            $friend = User::where('id', $friendId)->firstOrFail();
            // verify the relationship
            $isFriend = $this->isFriend($fb, $friend, $accessToken);
            if ($isFriend) {
                $checkins = $friend->checkins;
                $checkins->load('place');
                if ($useGeoJson) {
                    $featuresArray = $checkins->map(function ($item, $key) {
                        return $this->createGeoJsonResponse($item);
                    });
                    return $this->createGeoJsonFeaturesArray($featuresArray);
                }
                return $checkins;
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'The request friend is invalid'], 500);
        }

        return response()->json(['error' => 'The request friend is invalid'], 500);
    }

    private function createOrReturnExistingPlaceModel($facebookId, $name, $lat, $long, $city, $street, $zip, $country)
    {
        try {
            return Place::where('fb_id', $facebookId)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $newPlace = new Place;
            $newPlace->fb_id = $facebookId;
            $newPlace->name = $name;
            $newPlace->lat = $lat;
            $newPlace->long = $long;
            $newPlace->city = $city;
            $newPlace->street = $street;
            $newPlace->zip = $zip;
            $newPlace->country = $country;
            $newPlace->save();
            return $newPlace;
        }
    }

    private function createCheckinModel($facebookId, $userId, $placeId, $checkinTime)
    {
        $newCheckin = new Checkin;
        $newCheckin->fb_id = $facebookId;
        $newCheckin->user_id = $userId;
        $newCheckin->place_id = $placeId;
        $newCheckin->checkin_time = $checkinTime;
        $newCheckin->save();
        return $newCheckin;
    }

    private function createGeoJsonResponse($checkin)
    {
        $place = $checkin->place;
        return [
            'type' => 'Feature',
            'properties' => [
                'id' => $checkin->id,
                'fb_id' => $checkin->fb_id,
                'user_id' => $checkin->user_id,
                'checkin_time' => $checkin->checkin_time,
                'place_id' => $place->id,
                'place_fb_id' => $place->fb_id,
                'place_name' => $place->name,
                'place_city' => $place->city,
                'place_street' => $place->street,
                'place_zip' => $place->zip,
                'place_country' => $place->country
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$place->long, $place->lat]
            ]
        ];
    }

    /**
     * @param Facebook $fb
     * @param $friend
     * @param $accessToken
     * @return int
     */
    private function isFriend(Facebook $fb, $friend, $accessToken)
    {
        return $fb->get('me/friends/' . $friend->fb_id, $accessToken)->getGraphEdge()->count() == 1;
    }

    /**
     * @param $featuresArray
     * @return array
     */
    private function createGeoJsonFeaturesArray($featuresArray)
    {
        return [
            'type' => 'FeatureCollection',
            'crs' => ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:OGC:1.3:CRS84']],
            'features' => $featuresArray
        ];
    }
}
