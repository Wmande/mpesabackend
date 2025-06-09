<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', '*'); // Use your frontend URL in production
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        // Respond early to OPTIONS requests (CORS preflight)
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json([], 200, $response->headers->all());
        }

        return $response;
    }
}
