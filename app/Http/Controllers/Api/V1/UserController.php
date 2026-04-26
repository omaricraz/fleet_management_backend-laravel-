<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\PaginatesTenantResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\admin_user\StoreUserRequest;
use App\Http\Requests\admin_user\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse, PaginatesTenantResources;

    public function index(Request $request): JsonResponse
    {
        $query = User::query()->where('tenant_id', $request->attributes->get('tenant_id'));

        $paginator = $this->applyTenantListFilters(
            $query,
            $request,
            ['name', 'email', 'role'],
            ['id', 'name', 'email', 'role', 'created_at', 'updated_at'],
            'id'
        );

        return $this->successResponse('Success', $this->formatPaginated($paginator, UserResource::class));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = (int) $request->attributes->get('tenant_id');

        $user = User::query()->create($data);

        return $this->successResponse('Created successfully', new UserResource($user), 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        return $this->successResponse('Success', new UserResource($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        $user->fill($data);
        $user->save();

        return $this->successResponse('Updated successfully', new UserResource($user->fresh()));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->id === $user->id) {
            return $this->errorResponse('You cannot delete your own account', (object) [], 403);
        }

        $user->tokens()->delete();
        $user->delete();

        return $this->successResponse('Deleted successfully', (object) []);
    }
}
