<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * AuthController
 *
 * Thin HTTP controller for authentication endpoints.
 * All business logic is delegated to AuthService.
 *
 * Endpoints:
 *   POST /api/v1/auth/register  → register()
 *   POST /api/v1/auth/login     → login()
 *   POST /api/v1/auth/logout    → logout()   [protected]
 *   GET  /api/v1/auth/me        → me()       [protected]
 */
class AuthController extends ApiController
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Register a new user account.
     *
     * Returns a 201 message-only response.
     * User must login separately to obtain a token.
     *
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $this->authService->register($request->validated());

        return $this->created(
            null,
            'Registration successful. Please login to continue.'
        );
    }

    /**
     * Login and receive a Sanctum token.
     *
     * Returns the token and authenticated user object on success.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->authService->login($request->validated());

        if (! $token) {
            return $this->error(
                'Invalid credentials. Please check your email and password.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->success([
            'token' => $token,
            'user' => auth()->user(),
        ], 'Login successful.');
    }

    /**
     * Logout the authenticated user.
     *
     * Revokes the current Sanctum token only.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Return the currently authenticated user.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user(), 'Authenticated user fetched.');
    }
}
