<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context is required',
                'errors' => (object) [],
            ], 403);
        }

        if ($user->is_platform_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant user account required',
                'errors' => (object) [],
            ], 403);
        }

        if (! $user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context is required',
                'errors' => (object) [],
            ], 403);
        }

        $tenant = Tenant::query()->find($user->tenant_id);

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
                'errors' => (object) [],
            ], 403);
        }

        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_id', $tenant->id);
        app()->instance('tenant', $tenant);

        return $next($request);
    }
}
