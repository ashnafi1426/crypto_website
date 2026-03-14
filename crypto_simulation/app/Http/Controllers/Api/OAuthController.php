<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleOAuthService;
use App\Services\AppleOAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class OAuthController extends Controller
{
    private GoogleOAuthService $googleService;
    private AppleOAuthService $appleService;

    public function __construct(
        GoogleOAuthService $googleService,
        AppleOAuthService $appleService
    ) {
        $this->googleService = $googleService;
        $this->appleService = $appleService;
    }

    /**
     * Redirect to Google OAuth.
     */
    public function redirectToGoogle(Request $request): JsonResponse
    {
        try {
            $redirectUrl = $request->query('redirect_url');
            $authData = $this->googleService->getAuthUrl($redirectUrl);

            return response()->json([
                'success' => true,
                'auth_url' => $authData['url'],
                'state' => $authData['state'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Google OAuth URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $code = $request->query('code');
            $state = $request->query('state');
            $error = $request->query('error');
            
            // Debug log
            Log::info('OAuth callback received', [
                'code' => $code ? 'present' : 'missing',
                'state' => $state ? 'present' : 'missing',
                'error' => $error,
                'all_params' => $request->all()
            ]);

            if ($error) {
                return $this->redirectToFrontend('error', 'OAuth cancelled or failed: ' . $error);
            }

            if (!$code || !$state) {
                return $this->redirectToFrontend('error', 'Missing authorization code or state');
            }

            $result = $this->googleService->handleCallback($code, $state);

            if ($result['success']) {
                $redirectUrl = $result['redirect_url'] ?: config('services.frontend.url') . '/login';
                
                // Check if OTP verification is required
                if ($result['requires_otp'] ?? false) {
                    // Send OTP for email verification
                    $otpService = app(\App\Services\OtpVerificationService::class);
                    $otpResult = $otpService->generateOtp(
                        $result['user'],
                        $result['user']->email,
                        'email',
                        'registration',
                        $request
                    );
                    
                    Log::info('OTP sent for OAuth user', [
                        'user_id' => $result['user']->id,
                        'email' => $result['user']->email,
                        'otp_success' => $otpResult['success']
                    ]);
                    
                    // Redirect with OTP requirement flag
                    return redirect($redirectUrl . '?' . http_build_query([
                        'auth_success' => 'true',
                        'token' => $result['token'],
                        'requires_otp' => 'true',
                        'user' => base64_encode(json_encode([
                            'id' => $result['user']->id,
                            'name' => $result['user']->name,
                            'email' => $result['user']->email,
                            'avatar' => $result['user']->avatar,
                            'is_admin' => $result['user']->is_admin,
                            'email_verified' => false,
                        ])),
                    ]));
                }
                
                // No OTP required - proceed normally
                return redirect($redirectUrl . '?' . http_build_query([
                    'auth_success' => 'true',
                    'token' => $result['token'],
                    'user' => base64_encode(json_encode([
                        'id' => $result['user']->id,
                        'name' => $result['user']->name,
                        'email' => $result['user']->email,
                        'avatar' => $result['user']->avatar,
                        'is_admin' => $result['user']->is_admin,
                    ])),
                ]));
            }

            return $this->redirectToFrontend('error', $result['message']);

        } catch (\Exception $e) {
            return $this->redirectToFrontend('error', 'OAuth callback failed: ' . $e->getMessage());
        }
    }

    /**
     * Redirect to Apple OAuth.
     */
    public function redirectToApple(Request $request): JsonResponse
    {
        try {
            $redirectUrl = $request->query('redirect_url');
            $authData = $this->appleService->getAuthUrl($redirectUrl);

            return response()->json([
                'success' => true,
                'auth_url' => $authData['url'],
                'state' => $authData['state'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Apple OAuth URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Apple OAuth callback.
     */
    public function handleAppleCallback(Request $request): RedirectResponse
    {
        try {
            $code = $request->input('code');
            $state = $request->input('state');
            $user = $request->input('user'); // Apple sends user data on first login
            $error = $request->input('error');

            if ($error) {
                return $this->redirectToFrontend('error', 'OAuth cancelled or failed: ' . $error);
            }

            if (!$code || !$state) {
                return $this->redirectToFrontend('error', 'Missing authorization code or state');
            }

            $result = $this->appleService->handleCallback($code, $state, $user);

            if ($result['success']) {
                $redirectUrl = $result['redirect_url'] ?: config('services.frontend.url') . '/login';
                
                return redirect($redirectUrl . '?' . http_build_query([
                    'auth_success' => 'true',
                    'token' => $result['token'],
                    'user' => base64_encode(json_encode([
                        'id' => $result['user']->id,
                        'name' => $result['user']->name,
                        'email' => $result['user']->email,
                        'avatar' => $result['user']->avatar,
                        'is_admin' => $result['user']->is_admin,
                    ])),
                ]));
            }

            return $this->redirectToFrontend('error', $result['message']);

        } catch (\Exception $e) {
            return $this->redirectToFrontend('error', 'OAuth callback failed: ' . $e->getMessage());
        }
    }

    /**
     * Get OAuth providers configuration for frontend.
     */
    public function getProviders(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'providers' => [
                'google' => [
                    'enabled' => !empty(config('services.google.client_id')),
                    'name' => 'Google',
                    'icon' => 'google',
                ],
                'apple' => [
                    'enabled' => !empty(config('services.apple.client_id')),
                    'name' => 'Apple',
                    'icon' => 'apple',
                ],
            ],
        ]);
    }

    /**
     * Redirect to frontend with result.
     */
    private function redirectToFrontend(string $status, string $message, ?string $redirectUrl = null): RedirectResponse
    {
        $frontendUrl = $redirectUrl ?: config('services.frontend.url') . '/login';
        
        return redirect($frontendUrl . '?' . http_build_query([
            'oauth_status' => $status,
            'oauth_message' => $message,
        ]));
    }
}