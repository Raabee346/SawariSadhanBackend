<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class EnsureUserIsCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authenticatedUser = $request->user();

        if (!$authenticatedUser instanceof User) {
            return response()->json([
                'message' => 'Access denied. This endpoint is only for users.',
                'error' => 'You are logged in as a vendor, but trying to access user endpoints. Please login with type=user.'
            ], 403);
        }

        return $next($request);
    }
}

