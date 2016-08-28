<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Facebook\FacebookApp;
use Facebook\SignedRequest;
use App\Http\Requests;

class UserController extends Controller
{
    public function deauthorize(Request $request)
    {
        $signedPayload = $request->input('signed_request', '');
        if ($signedPayload == '') {
            return response()->json(['error' => 'Invalid payload data'], 500);
        }
        $payload = $this->parseSignedRequest($signedPayload);
        if ($payload == null) {
            // Invalid payload
            error_log('Invalid payload data '.$signedPayload);
            return response()->json(['error' => 'Invalid payload data'], 500);
        }
        $userFbId = $payload['user_id'];
        try {
            $user = User::where('fb_id', $userFbId)->firstOrFail();
            $id = $user->id;
            // Remove all checkins own by user
            Checkin::where('user_id', $id)->delete();
            // Remove the user itself
            $user->delete();
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'The request user does not exist'], 500);
        }

        return response('ok', 200);
    }

    private function parseSignedRequest($signed_request)
    {
        list($encoded_sig, $payload) = explode('.', $signed_request, 2);

        $facebookSetting = \Config::get('facebook');

        $secret = $facebookSetting['app_secret'];

        // decode the data
        $sig = $this->base64UrlDecode($encoded_sig);
        $data = json_decode($this->base64UrlDecode($payload), true);

        // confirm the signature
        $expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
        if ($sig !== $expected_sig) {
            error_log('Bad Signed JSON signature!');
            return null;
        }

        return $data;
    }

    private function base64UrlDecode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
