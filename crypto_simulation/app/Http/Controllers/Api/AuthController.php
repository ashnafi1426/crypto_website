<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\AuthenticationServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private AuthenticationServiceInterface $authService;

    public function __construct(AuthenticationServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->authService->register($request->all());

        $statusCode = $result['success'] ? 201 : 400;
        
        if (isset($result['error_code']) && $result['error_code'] === 'RATE_LIMIT_EXCEEDED') {
            $statusCode = 429;
        }

        return response()->json($result, $statusCode);
    }

    /**
     * Authenticate user and return token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->authService->authenticate(
            $request->input('email'),
            $request->input('password')
        );

        $statusCode = $result['success'] ? 200 : 401;
        
        if (isset($result['error_code'])) {
            $statusCode = match ($result['error_code']) {
                'RATE_LIMIT_EXCEEDED' => 429,
                'ACCOUNT_LOCKED' => 423,
                default => 401
            };
        }

        return response()->json($result, $statusCode);
    }

    /**
     * Logout user and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No token provided.'
                ], 400);
            }

            $revoked = $this->authService->revokeToken($token);

            if ($revoked) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully logged out.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to logout.'
            ], 400);

        } catch (\Exception) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed due to server error.'
            ], 500);
        }
    }

    /**
     * Get authenticated user information.
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'portfolio_value' => $user->portfolio_value
                ]
            ]);

        } catch (\Exception) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user information.'
            ], 500);
        }
    }

    /**
     * Request password reset.
     */
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->authService->requestPasswordReset($request->input('email'));

        $statusCode = $result['success'] ? 200 : 400;
        
        if (isset($result['error_code']) && $result['error_code'] === 'RATE_LIMIT_EXCEEDED') {
            $statusCode = 429;
        }

        return response()->json($result, $statusCode);
    }

    /**
     * Confirm password reset.
     */
    public function confirmPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->authService->confirmPasswordReset(
            $request->input('email'),
            $request->input('token'),
            $request->input('password')
        );

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }
}