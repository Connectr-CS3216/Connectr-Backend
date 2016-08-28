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
    // $request->input('title') and $request->input('description') can be any string for the user's Open Graph story
    public function publish(Request $request, Facebook $fb)
    {
        $token = $request->input('token');
        $payload = JWTAuth::setToken($token)->getPayload();
        // upload image to Cloudinary
        $data = $request->input('data');
        \Cloudinary::config([
            'cloud_name' => 'connectr',
            'api_key' => env('CLOUDINARY_KEY', ''),
            'api_secret' => env('CLOUDINARY_SECRET', '')
        ]);
        $output = \Cloudinary\Uploader::upload($data,[
            'transformation' => [
                [
                    "width" => 1200,
                    "height" => 630,
                    "crop" => "fill"
                ],
                [
                    'overlay' => 'connectr_logo',
                    'gravity' => 'south_east',
                    'width' => 300,
                    'y' => 10
                ]
            ]
        ]);
        // publish on user's Facebook timeline
        try {
            $title = 'My Travel Map';
            $description = 'I have travelled to these countries! Join Connectr to show me where you have been to!';
            if ($request->has('title')) {
                $title = $request->input('title');
            }
            if ($request->has('description')) {
                $description = $request->input('description');
            }
            $object = [
                'type' => 'ogcustom:map',
                'title' => $title,
                'description' => $description,
                'image' => $output['url']
            ];
            $map = ['map' => json_encode($object), 'fb:explicitly_shared' => True];
            $response = $fb->post('/me/ogcustom:create', $map, $payload['token']);
            return response()->json(['success' => True]);
        } catch (JWTException $e) {
            // something went wrong whilst attempting to decode the token
            return response()->json(['error' => 'Error creating the token'], 500);
        } catch(FacebookResponseException $e) {
            return $e;
            return response()->json(['error' => 'Graph returned an error'], 500);
        } catch(FacebookSDKException $e) {
            return response()->json(['error' => 'Facebook SDK returned an error'], 500);
        }
    }
}