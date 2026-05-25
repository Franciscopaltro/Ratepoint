<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiRoleCheck
{
    /**
     * Check if the authenticated user has the required role.
     * Usage: ->middleware('api.role:field_agent,agent')
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthenticated'
            ], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'error' => 'Forbidden',
                'message' => 'You do not have permission to access this resource.'
            ], 403);
        }

        return $next($request);
    }
}
