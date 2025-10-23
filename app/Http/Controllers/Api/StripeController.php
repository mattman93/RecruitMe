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
     */
    public function createCheckoutSession(Request $request)
    {
        $user = Auth::user();

        try {
            $sessionData = [
                'ui_mode' => 'embedded',
                'line_items' => [
                    [
                        'price' => $request->input('price_id'), // Price ID from frontend
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
