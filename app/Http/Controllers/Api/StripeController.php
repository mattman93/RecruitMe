<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;

class StripeController extends Controller
{
    public function __construct()
    {
        // Set Stripe API key
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    /**
     * Create a Stripe Checkout session for subscription
     * Supports both authenticated and unauthenticated users
     * Handles upgrades/downgrades for existing subscriptions
     */
    public function createCheckoutSession(Request $request)
    {
        $user = Auth::user();
        $requestedPriceId = $request->input('price_id');

        try {
            // If user is authenticated, check for existing subscription
            if ($user && $user->stripe_subscription_id && $user->subscription_status === 'active') {
                // User has an active subscription - check if they're trying to upgrade/downgrade
                $subscription = \Stripe\Subscription::retrieve($user->stripe_subscription_id);

                // Get current price ID
                $currentPriceId = $subscription->items->data[0]->price->id ?? null;

                // If trying to subscribe to the same plan, return error
                if ($currentPriceId === $requestedPriceId) {
                    return response()->json([
                        'error' => 'You are already subscribed to this plan.',
                        'already_subscribed' => true,
                    ], 400);
                }

                // Handle upgrade/downgrade
                return $this->upgradeSubscription($user, $subscription, $requestedPriceId);
            }

            $sessionData = [
                'ui_mode' => 'embedded',
                'line_items' => [
                    [
                        'price' => $requestedPriceId,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'return_url' => url('/subscribe/success?session_id={CHECKOUT_SESSION_ID}'),
            ];

            // If user is authenticated, link to their account
            if ($user) {
                // Create or retrieve Stripe customer
                $customerId = $user->stripe_customer_id;

                if (!$customerId) {
                    // Create new customer
                    $customer = Customer::create([
                        'email' => $user->email,
                        'name' => $user->name,
                        'metadata' => [
                            'user_id' => $user->id,
                        ],
                    ]);

                    // Save customer ID to user
                    $user->update(['stripe_customer_id' => $customer->id]);
                    $customerId = $customer->id;
                }

                $sessionData['customer'] = $customerId;
                $sessionData['metadata'] = [
                    'user_id' => $user->id,
                ];
            } else {
                // For unauthenticated users, Stripe will create a customer automatically
                // during subscription checkout (no need for customer_creation parameter)
                // The customer will be created with the email they provide
            }

            $session = Session::create($sessionData);

            return response()->json([
                'clientSecret' => $session->client_secret,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upgrade or downgrade an existing subscription
     */
    protected function upgradeSubscription($user, $currentSubscription, $newPriceId)
    {
        try {
            // Update the subscription to the new price
            $subscription = \Stripe\Subscription::update($currentSubscription->id, [
                'items' => [
                    [
                        'id' => $currentSubscription->items->data[0]->id,
                        'price' => $newPriceId,
                    ],
                ],
                'proration_behavior' => 'always_invoice', // Pro-rate the difference immediately
            ]);

            // Determine the new plan
            $newPlan = $this->determinePlanFromPrice($newPriceId);

            // Update user record
            $user->update([
                'subscription_plan' => $newPlan,
            ]);

            return response()->json([
                'success' => true,
                'upgraded' => true,
                'message' => 'Your subscription has been upgraded successfully!',
                'new_plan' => $newPlan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determine plan from price ID
     */
    protected function determinePlanFromPrice($priceId)
    {
        $starterPriceId = config('services.stripe.prices.starter');
        $proPriceId = config('services.stripe.prices.pro');

        if ($priceId === $proPriceId) {
            return 'pro';
        } elseif ($priceId === $starterPriceId) {
            return 'starter';
        }

        return 'unknown';
    }

    /**
     * Retrieve session status
     */
    public function getSessionStatus(Request $request)
    {
        $sessionId = $request->query('session_id');

        try {
            $session = Session::retrieve($sessionId);

            return response()->json([
                'status' => $session->status,
                'customer_email' => $session->customer_details->email ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pricing configuration (public endpoint)
     */
    public function getPricingConfig()
    {
        return response()->json([
            'prices' => [
                'starter' => config('services.stripe.prices.starter'),
                'pro' => config('services.stripe.prices.pro'),
            ],
            'publishable_key' => config('services.stripe.publishable_key'),
        ]);
    }
}
