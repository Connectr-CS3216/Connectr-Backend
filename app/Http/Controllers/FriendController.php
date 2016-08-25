<?php

namespace App\Http\Controllers;

use App\Models\User;
use Facebook\Facebook;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

use App\Http\Requests;
use Tymon\JWTAuth\Facades\JWTAuth;

class FriendController extends Controller
{
    public function index(Request $request, Facebook $fb) {
        $token = JWTAuth::setRequest($request)->getToken();
        $payload = JWTAuth::setToken($token)->parseToken()->getPayload();
        $accessToken = $payload['token'];
        $user = null;
        try {
            $user = User::where('id', $payload['userid'])->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'The request user does not exist'], 500);
        }

        $friends = $fb->get('me/friends', $accessToken)->getGraphEdge();
        $friendList = [];
        do {
            foreach ($friends as $friend) {
                $friendModel = User::where('fb_id', $friend->getField('id'))->first();
                if ($friendModel && !$friendModel->isFirstTimeLogin()) {
                    $friendList[] = $friendModel->getMetaData();
                }
            }
        } while ($checkins = $fb->next($friends));
        return $friendList;
    }
}
