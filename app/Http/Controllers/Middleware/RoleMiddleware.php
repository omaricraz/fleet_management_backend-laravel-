<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roleParams): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'errors' => (object) [],
            ], 401);
        }

        $allowed = [];

        foreach ($roleParams as $param) {
            foreach (array_map('trim', explode(',', $param)) as $role) {
                if ($role !== '') {
                    $allowed[] = $role;
                }
            }
        }

        $allowed = array_values(array_unique($allowed));

        if ($allowed === [] || ! in_array($user->role, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
