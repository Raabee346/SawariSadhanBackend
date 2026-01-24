<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableCompression
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Add headers to prevent server compression for mobile API responses
        $response->headers->set('Content-Encoding', 'identity');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, no-transform');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Vary', 'Accept-Encoding');
        
        return $response;
    }
}
