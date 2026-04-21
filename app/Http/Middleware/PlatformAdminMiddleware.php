<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_platform_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
