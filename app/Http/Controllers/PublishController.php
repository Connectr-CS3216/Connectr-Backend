<?php

namespace App\Http\Controllers;

use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class PublishController extends Controller
{
    // $request->input('privacy') can be EVERYONE, FRIENDS_OF_FRIENDS, ALL_FRIENDS or SELF
    public function publish(Request $request, Facebook $fb)
    {
        $token = JWTAuth::setRequest($request)->getToken();
        $payload = JWTAuth::setToken($token)->parseToken()->getPayload();
        try {
            $privacy = ['value' => 'CUSTOM', 'friends' => 'SELF'];
            if ($request->has('privacy')) {
                $privacy['friends'] = $request->input('privacy');
            }
            $map = ['map' => '"http://connectr.tk/"', 'privacy' => json_encode($privacy), 'fb:explicitly_shared' => True];
            $response = $fb->post('/me/ogcustom:create', $map, $payload['token']);
            return response()->json(['success' => True]);
        } catch (JWTException $e) {
            // something went wrong whilst attempting to decode the token
            return response()->json(['error' => 'Error creating the token'], 500);
        } catch(FacebookResponseException $e) {
            return response()->json(['error' => 'Graph returned an error'], 500);
        } catch(FacebookSDKException $e) {
            return response()->json(['error' => 'Facebook SDK returned an error'], 500);
        }
    }
}