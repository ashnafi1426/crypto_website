<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EducationalScamSimulator;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Educational Scam Controller
 * 
 * ⚠️ FOR EDUCATIONAL PURPOSES ONLY ⚠️
 * 
 * This controller demonstrates how fraudulent platforms operate.
 * It includes clear educational warnings and should never be used
 * for actual fraud or deception.
 */
class EducationalScamController extends Controller
{
    private EducationalScamSimulator $simulator;

    public function __construct(EducationalScamSimulator $simulator)
    {
        $this->simulator = $simulator;
    }

    /**
     * Display educational disclaimer
     */
    public function getEducationalDisclaimer(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'disclaimer' => [
                'title' => '🚨 EDUCATIONAL SIMULATION ONLY 🚨',
                'warning' => 'This is a learning tool to understand how cryptocurrency scams work',
                'prohibited_uses' => [
                    'Real financial transactions',
                    'Deceiving actual users',
                    'Commercial deployment',
                    'Any form of fraud'
                ],
                'learning_objectives' => [
                    'Understand scam tactics',
                    'Identify red flags',
                    'Learn fraud prevention',
                    'Protect yourself and others'
                ]
            ]
        ]);
    }

    /**
     * Demonstrate artificial profit generation
     */
    public function simulateArtificialProfits(Request $request): JsonResponse
    {
        $user = $request->user();
        $profitPercentage = $request->input('profit_percentage', 5.0);

        Log::warning('EDUCATIONAL SIMULATION: Artificial profit generation accessed', [
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'warning' => 'This is for educational purposes only'
        ]);

        $result = $this->simulator->generateArtificialProfits($user, $profitPercentage);

        return response()->json([
            'success' => true,
            'simulation_result' => $result,
            'educational_warning' => '⚠️ This demonstrates how scammers create fake profits. In real scams, these profits cannot be withdrawn.',
            'red_flags_to_watch' => [
                'Unrealistic profit rates',
                'Guaranteed returns',
                'Profits that seem too good to be true',
                'No clear explanation of trading strategy'
            ]
        ]);
    }

    /**
     * Demonstrate withdrawal blocking tactics
     */
    public function simulateWithdrawalBlocking(Request $request): JsonResponse
    {
        $user = $request->user();
        $currency = $request->input('currency', 'BTC');
        $amount = $request->input('amount', 1000);

        Log::warning('EDUCATIONAL SIMULATION: Withdrawal blocking demonstration', [
            'user_id' => $user->id,
            'currency' => $currency,
            'amount' => $amount,
            'warning' => 'This demonstrates fraud tactics for educational purposes'
        ]);

        $result = $this->simulator->simulateWithdrawalBlocking($user, $currency, $amount);

        return response()->json([
            'success' => false,
            'simulation_result' => $result,
            'educational_explanation' => [
                'what_this_demonstrates' => 'Common tactics scammers use to prevent withdrawals',
                'victim_psychology' => 'Scammers exploit sunk cost fallacy - victims pay fees hoping to recover their money',
                'reality' => 'In real scams, paying these fees leads to more demands and eventual total loss',
                'protection_tips' => [
                    'Legitimate exchanges never require additional fees for withdrawals',
                    'Be suspicious of unexpected verification requirements',
                    'Never pay upfront fees to access your own money',
                    'Research the platform thoroughly before depositing'
                ]
            ]
        ]);
    }

    /**
     * Generate fake transaction IDs for educational purposes
     */
    public function generateFakeTransaction(Request $request): JsonResponse
    {
        $currency = $request->input('currency', 'BTC');

        $result = $this->simulator->generateFakeTransactionId($currency);

        return response()->json([
            'success' => true,
            'fake_transaction' => $result,
            'educational_warning' => '⚠️ This transaction ID is completely fake and will not appear on any blockchain explorer',
            'how_scammers_use_this' => [
                'Show fake transaction IDs to convince users their deposit was processed',
                'Create false sense of legitimacy',
                'Buy time while planning to disappear with funds'
            ],
            'how_to_verify_real_transactions' => [
                'Always check transaction IDs on official blockchain explorers',
                'Verify the receiving address matches what the platform provided',
                'Check transaction confirmations and timestamps',
                'Be suspicious if transactions cannot be found on explorers'
            ]
        ]);
    }

    /**
     * Display fake investment plans for educational analysis
     */
    public function getFakeInvestmentPlans(): JsonResponse
    {
        $plans = $this->simulator->generateFakeInvestmentPlans();

        return response()->json([
            'success' => true,
            'fake_plans' => $plans,
            'educational_analysis' => [
                'why_these_are_scams' => [
                    'No legitimate investment can guarantee daily returns of 8-25%',
                    'Real trading involves significant risks and potential losses',
                    'Regulatory authorities warn against such promises',
                    'Mathematical impossibility of sustained high returns'
                ],
                'psychological_tactics' => [
                    'Appeal to greed with unrealistic promises',
                    'Create urgency with limited-time offers',
                    'Use technical jargon to sound legitimate',
                    'Show fake testimonials and success stories'
                ],
                'legitimate_investment_education' => [
                    'Diversification reduces risk',
                    'Long-term investing typically yields 5-10% annually',
                    'All investments carry risk of loss',
                    'Regulatory oversight protects investors'
                ]
            ]
        ]);
    }

    /**
     * Admin simulation - demonstrate manipulation capabilities
     * (Only accessible to admin users for educational purposes)
     */
    public function getAdminManipulationDemo(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin access required for this educational demonstration'
            ], 403);
        }

        $capabilities = $this->simulator->getAdminManipulationCapabilities();

        return response()->json([
            'success' => true,
            'admin_capabilities' => $capabilities,
            'educational_warning' => '⚠️ This shows what scam operators can control behind the scenes',
            'victim_perspective' => [
                'Users see only the frontend interface',
                'They cannot see admin manipulations happening',
                'Database changes appear as legitimate transactions',
                'Victims trust the displayed numbers'
            ],
            'protection_measures' => [
                'Only use regulated, licensed exchanges',
                'Verify platform legitimacy through official channels',
                'Start with small amounts to test withdrawals',
                'Be suspicious of platforms with no regulatory oversight'
            ]
        ]);
    }

    /**
     * Simulate fake user testimonials and social proof
     */
    public function getFakeSocialProof(): JsonResponse
    {
        $fakeTestimonials = [
            [
                'name' => 'Sarah M.',
                'location' => 'New York, USA',
                'fake_story' => 'I made $50,000 in just 2 months with their AI trading bot!',
                'fake_profit' => '$50,000',
                'red_flags' => [
                    'Unrealistic profit claims',
                    'Vague identity (only first name and initial)',
                    'No verifiable contact information',
                    'Professional-looking photo (likely stock image)'
                ]
            ],
            [
                'name' => 'Michael K.',
                'location' => 'London, UK',
                'fake_story' => 'Best investment platform ever! Guaranteed daily profits!',
                'fake_profit' => '$25,000',
                'red_flags' => [
                    'Use of word "guaranteed" (impossible in real trading)',
                    'Overly enthusiastic language',
                    'No specific details about trading strategy',
                    'Generic testimonial format'
                ]
            ]
        ];

        return response()->json([
            'success' => true,
            'fake_testimonials' => $fakeTestimonials,
            'educational_analysis' => [
                'how_scammers_create_fake_social_proof' => [
                    'Use stock photos for fake user profiles',
                    'Write testimonials with unrealistic profit claims',
                    'Create fake leaderboards and statistics',
                    'Pay for fake reviews on external sites'
                ],
                'how_to_identify_fake_testimonials' => [
                    'Check if photos appear in reverse image searches',
                    'Look for overly generic or enthusiastic language',
                    'Verify if contact information is provided',
                    'Be suspicious of testimonials with exact profit amounts'
                ],
                'legitimate_social_proof_indicators' => [
                    'Regulatory licenses and certifications',
                    'Transparent company information',
                    'Verifiable customer support',
                    'Independent third-party reviews'
                ]
            ]
        ]);
    }
}