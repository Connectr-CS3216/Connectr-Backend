<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AuthenticateMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        try {
            $token = JWTAuth::setRequest($request)->getToken();
            $payload = JWTAuth::setToken($token)->parseToken()->getPayload();
            try {
                User::where('id', $payload['user']['id'])->firstOrFail();
                return $next($request);
            } catch (ModelNotFoundException $e) {
                return response()->json(['error' => 'Requested user does not exist'], 500);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expired'], 500);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token invalid'], 500);
        }
    }
}
