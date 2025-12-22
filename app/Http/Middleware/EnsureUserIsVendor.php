<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Vendor;

class EnsureUserIsVendor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof Vendor) {
            return response()->json([
                'message' => 'Access denied. This endpoint is only for vendors.',
                'error' => 'You are logged in as a user, but trying to access vendor endpoints. Please login with type=vendor.'
            ], 403);
        }

        return $next($request);
    }
}

