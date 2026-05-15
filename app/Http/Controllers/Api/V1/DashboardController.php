<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $tenantId = (int) $request->attributes->get('tenant_id');

        return $this->successResponse('Success', $this->dashboard->summarize($tenantId));
    }
}
