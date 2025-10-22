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
     */
    public function createCheckoutSession(Request $request)
    {
        $user = Auth::user();

        try {
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

            $session = Session::create([
                'ui_mode' => 'embedded',
                'customer' => $customerId,
                'line_items' => [
                    [
                        'price' => $request->input('price_id'), // Price ID from Stripe dashboard
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'return_url' => url('/subscribe/success?session_id={CHECKOUT_SESSION_ID}'),
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

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
