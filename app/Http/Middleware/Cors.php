<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    public function handle(Request $request, Closure $next)
    {
        // Define allowed origins
        $allowedOrigins = [
            'http://localhost:3000', // For local development
            'https://your-frontend-domain.com', // Replace with your actual frontend domain
        ];

        $origin = $request->header('Origin') ?? '';

        // Only set CORS headers for allowed origins
        if (in_array($origin, $allowedOrigins)) {
            $response = $next($request);

            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->headers->set('Access-Control-Max-Age', '86400');

            // Handle preflight OPTIONS requests
            if ($request->isMethod('OPTIONS')) {
                return response()->json([], 200, $response->headers->all());
            }

            return $response;
        }

        // Proceed without CORS headers for non-allowed origins
        return $next($request);
    }
}