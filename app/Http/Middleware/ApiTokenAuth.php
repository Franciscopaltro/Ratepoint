<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     * Validates the Bearer token from the Authorization header.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required',
                'message' => 'Please provide a valid API token in the Authorization header (Bearer <token>).'
            ], 401);
        }

        $accessToken = PersonalAccessToken::findByToken($token);

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid token',
                'message' => 'The provided API token is not valid.'
            ], 401);
        }

        if ($accessToken->isExpired()) {
            $accessToken->delete();
            return response()->json([
                'success' => false,
                'error' => 'Token expired',
                'message' => 'Your session has expired. Please log in again.'
            ], 401);
        }

        $user = $accessToken->user;

        if (!$user || !$user->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Account disabled',
                'message' => 'Your account has been deactivated. Contact your administrator.'
            ], 403);
        }

        // Mark token as used and bind user to the request
        $accessToken->markAsUsed();
        $request->merge(['api_user' => $user]);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
