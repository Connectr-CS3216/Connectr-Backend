<?php

use Illuminate\Http\Request;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

// Verify access token (login)
Route::post('/verify-facebook-token', 'AuthenticateController@authenticate');

// Checkins data
Route::get('/checkins', 'CheckinController@index');
Route::get('/checkins/{friendId}', 'CheckinController@getCheckinsByFriendId');

// Get user itself
Route::get('/user/me', [
    'middleware' => 'auth',
    'uses' => 'UserController@me'
]);

// Get all friends
Route::get('/friends', [
    'middleware' => 'auth',
    'uses' => 'FriendController@index'
]);

// Publish a open graph story on user's wall
Route::post('/publish', [
    'middleware' => 'auth',
    'uses' => 'PublishController@publish'
]);

// De-authorize the user if the user removed the application.
Route::post('/deauthorize', 'UserController@deauthorize');