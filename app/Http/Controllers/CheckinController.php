<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Place;
use App\Models\User;
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
     * @return \Illuminate\Http\Response
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

        $userId = $user->id;
        $isFirstTimeLogin = $user->is_first_login == 1;
        $lastLoginTime = $user->last_login_time;

        // Check if this is the first time getting data
        // If YES, set the boolean to be false, and just pull all tagged_places.
        // If NO, get the last_login_time, and get all tagged_places that is created after last_login_time.
        $checkins = $fb->get('me/tagged_places?limit=100', $accessToken)->getGraphEdge();
        $hasLoadedAll = false;
        do {
            foreach ($checkins as $checkin) {
                // Checks if there is any new checkins that is created after the last login time.
                if (!$isFirstTimeLogin && $checkin->getField('created_time') > $lastLoginTime) {
                    $hasLoadedAll = true;
                    break;
                }

                $place = $checkin->getField('place');
                $location = $place->getField('location');
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
        $user->save();
        $checkins = $user->checkins;
        $checkins->load('place');
        return $checkins;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
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
}
