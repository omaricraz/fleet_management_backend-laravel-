<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesTenantResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\IndexPlatformUserRequest;
use App\Http\Requests\Platform\StorePlatformUserRequest;
use App\Http\Requests\Platform\UpdatePlatformUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformUserController extends Controller
{
    use ApiResponse, PaginatesTenantResources;

    public function index(IndexPlatformUserRequest $request): JsonResponse
    {
        $query = User::query();

        $validated = $request->validated();
        $tenantId = $validated['tenant_id'] ?? null;
        if ($tenantId !== null) {
            $query->where('tenant_id', (int) $tenantId);
        }

        $paginator = $this->applyTenantListFilters(
            $query,
            $request,
            ['name', 'email', 'role'],
            ['id', 'name', 'email', 'role', 'tenant_id', 'created_at', 'updated_at'],
            'id'
        );

        return $this->successResponse('Success', $this->formatPaginated($paginator, UserResource::class));
    }

    public function store(StorePlatformUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return $this->successResponse('Created successfully', new UserResource($user), 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        return $this->successResponse('Success', new UserResource($user));
    }

    public function update(UpdatePlatformUserRequest $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        $data = $request->validated();
        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        $user->fill($data);
        $user->save();

        return $this->successResponse('Updated successfully', new UserResource($user->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if ($request->user()?->id === $user->id) {
            return $this->errorResponse('You cannot delete your own account', (object) [], 403);
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->successResponse('Deleted successfully', (object) []);
    }
}
