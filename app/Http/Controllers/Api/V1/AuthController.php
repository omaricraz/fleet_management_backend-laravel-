<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request, AuthService $auth): JsonResponse
    {
        $result = $auth->login($request->validated());

        return $this->successResponse('Login successful', [
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->successResponse('Logout successful', (object) []);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse('Success', new UserResource($request->user()));
    }
}
