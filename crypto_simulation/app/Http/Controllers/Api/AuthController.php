<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\AuthenticationServiceInterface;
use App\Services\EmailVerificationService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private AuthenticationServiceInterface $authService;
    private EmailVerificationService $emailVerificationService;
    private TwoFactorAuthService $twoFactorService;

    public function __construct(
        AuthenticationServiceInterface $authService,
        EmailVerificationService $emailVerificationService,
        TwoFactorAuthService $twoFactorService
    ) {
        $this->authService = $authService;
        $this->emailVerificationService = $emailVerificationService;
        $this->twoFactorService = $twoFactorService;
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

        // Send email verification if registration successful
        if ($result['success'] && isset($result['user'])) {
            try {
                $this->emailVerificationService->sendVerificationEmail($result['user']);
            } catch (\Exception $e) {
                // Log error but don't fail registration
                Log::warning('Failed to send verification email: ' . $e->getMessage());
            }
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
     * Reset password with token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|string|email',
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

        try {
            $user = \App\Models\User::where('email', $request->input('email'))
                ->where('password_reset_token', $request->input('token'))
                ->where('password_reset_expires_at', '>', now())
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token.',
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->input('password')),
                'password_reset_token' => null,
                'password_reset_expires_at' => null,
            ]);

            // Reset failed attempts
            $user->resetFailedAttempts();

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed.',
            ], 500);
        }
    }

    // ===== EMAIL VERIFICATION METHODS =====

    /**
     * Send email verification
     */
    public function sendEmailVerification(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $result = $this->emailVerificationService->sendVerificationEmail($user);
            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify email with token
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->emailVerificationService->verifyEmail($request->input('token'));
            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get email verification status
     */
    public function getEmailVerificationStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $status = $this->emailVerificationService->getVerificationStatus($user);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get verification status: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===== TWO-FACTOR AUTHENTICATION METHODS =====

    /**
     * Generate 2FA secret
     */
    public function generateTwoFactorSecret(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $result = $this->twoFactorService->generateSecret($user);
            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate 2FA secret: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm and enable 2FA
     */
    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $result = $this->twoFactorService->confirmTwoFactor($user, $request->input('code'));
            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm 2FA: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify 2FA code during login
     */
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = \App\Models\User::where('email', $request->input('email'))->first();
            
            if (!$user || !$user->two_factor_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => '2FA not enabled for this user.'
                ], 400);
            }

            $isValid = $this->twoFactorService->verifyCode($user, $request->input('code'));

            if ($isValid) {
                // Generate new token for authenticated user
                $token = $user->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => '2FA verification successful.',
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'is_admin' => $user->is_admin,
                        'email_verified_at' => $user->email_verified_at
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid 2FA code.'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify 2FA: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable 2FA
     */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            // Verify password before disabling 2FA
            if (!Hash::check($request->input('password'), $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid password.'
                ], 400);
            }

            $result = $this->twoFactorService->disableTwoFactor($user);
            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to disable 2FA: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $result = $this->twoFactorService->regenerateRecoveryCodes($user);
            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate recovery codes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get 2FA status
     */
    public function getTwoFactorStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $status = $this->twoFactorService->getTwoFactorStatus($user);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get 2FA status: ' . $e->getMessage()
            ], 500);
        }
    }

    // ===== OTP VERIFICATION METHODS =====

    /**
     * Generate and send OTP
     */
    public function generateOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string', // email or phone
            'type' => 'required|string|in:email,sms',
            'purpose' => 'required|string|in:registration,login,password_reset,transaction,email_verification'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $otpService = app(\App\Services\OtpVerificationService::class);
            $result = $otpService->generateOtp(
                $user,
                $request->input('identifier'),
                $request->input('type'),
                $request->input('purpose'),
                $request
            );

            $statusCode = $result['success'] ? 200 : 400;
            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate OTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
            'otp_code' => 'required|string|size:6',
            'type' => 'required|string|in:email,sms',
            'purpose' => 'required|string|in:registration,login,password_reset,transaction,email_verification'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $otpService = app(\App\Services\OtpVerificationService::class);
            $result = $otpService->verifyOtp(
                $user,
                $request->input('identifier'),
                $request->input('otp_code'),
                $request->input('type'),
                $request->input('purpose')
            );

            // If OTP verification successful and purpose is registration or email_verification, update email_verified_at
            if ($result['success']) {
                $purpose = $request->input('purpose');
                if (in_array($purpose, ['registration', 'email_verification']) && !$user->email_verified_at) {
                    $user->email_verified_at = now();
                    $user->save();
                    
                    Log::info('Email verified via OTP', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'purpose' => $purpose
                    ]);
                }
            }

            $statusCode = $result['success'] ? 200 : 400;
            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify OTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get OTP status
     */
    public function getOtpStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
            'type' => 'required|string|in:email,sms',
            'purpose' => 'required|string|in:registration,login,password_reset,transaction,email_verification'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }

            $otpService = app(\App\Services\OtpVerificationService::class);
            $status = $otpService->getOtpStatus(
                $user,
                $request->input('identifier'),
                $request->input('type'),
                $request->input('purpose')
            );

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get OTP status: ' . $e->getMessage()
            ], 500);
        }
    }
}