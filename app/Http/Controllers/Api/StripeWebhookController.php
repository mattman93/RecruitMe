<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    /**
     * Handle Stripe webhook events
     */
    public function handleWebhook(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::error('Stripe webhook invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            Log::error('Stripe webhook invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdate($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;

            default:
                Log::info('Unhandled Stripe webhook event', ['type' => $event->type]);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle checkout session completed
     */
    protected function handleCheckoutSessionCompleted($session)
    {
        $userId = $session->metadata->user_id ?? null;

        if (!$userId) {
            Log::error('Checkout session completed without user_id', ['session_id' => $session->id]);
            return;
        }

        $user = User::find($userId);

        if (!$user) {
            Log::error('User not found for checkout session', ['user_id' => $userId]);
            return;
        }

        // Update user with Stripe customer ID
        $user->update([
            'stripe_customer_id' => $session->customer,
        ]);

        Log::info('Checkout session completed', [
            'user_id' => $userId,
            'customer_id' => $session->customer,
            'session_id' => $session->id
        ]);
    }

    /**
     * Handle subscription created or updated
     */
    protected function handleSubscriptionUpdate($subscription)
    {
        $user = User::where('stripe_customer_id', $subscription->customer)->first();

        if (!$user) {
            Log::error('User not found for subscription update', ['customer_id' => $subscription->customer]);
            return;
        }

        // Determine the plan based on the price ID
        $priceId = $subscription->items->data[0]->price->id ?? null;
        $plan = $this->determinePlan($priceId, $subscription->items->data[0]->price->product ?? null);

        $user->update([
            'stripe_subscription_id' => $subscription->id,
            'subscription_status' => $subscription->status,
            'subscription_plan' => $plan,
            'subscription_ends_at' => $subscription->cancel_at ? date('Y-m-d H:i:s', $subscription->cancel_at) : null,
        ]);

        Log::info('Subscription updated', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
            'plan' => $plan
        ]);
    }

    /**
     * Handle subscription deleted
     */
    protected function handleSubscriptionDeleted($subscription)
    {
        $user = User::where('stripe_subscription_id', $subscription->id)->first();

        if (!$user) {
            Log::error('User not found for subscription deletion', ['subscription_id' => $subscription->id]);
            return;
        }

        $user->update([
            'subscription_status' => 'canceled',
            'subscription_ends_at' => now(),
        ]);

        Log::info('Subscription canceled', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id
        ]);
    }

    /**
     * Handle successful payment
     */
    protected function handlePaymentSucceeded($invoice)
    {
        if (!$invoice->subscription) {
            return; // Not a subscription payment
        }

        $user = User::where('stripe_subscription_id', $invoice->subscription)->first();

        if (!$user) {
            Log::error('User not found for payment success', ['subscription_id' => $invoice->subscription]);
            return;
        }

        // Ensure subscription is active
        if ($user->subscription_status !== 'active') {
            $user->update(['subscription_status' => 'active']);
        }

        Log::info('Payment succeeded', [
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount_paid / 100
        ]);
    }

    /**
     * Handle failed payment
     */
    protected function handlePaymentFailed($invoice)
    {
        if (!$invoice->subscription) {
            return; // Not a subscription payment
        }

        $user = User::where('stripe_subscription_id', $invoice->subscription)->first();

        if (!$user) {
            Log::error('User not found for payment failure', ['subscription_id' => $invoice->subscription]);
            return;
        }

        $user->update([
            'subscription_status' => 'past_due',
        ]);

        Log::warning('Payment failed', [
            'user_id' => $user->id,
            'invoice_id' => $invoice->id
        ]);
    }

    /**
     * Determine subscription plan from price/product ID
     */
    protected function determinePlan($priceId, $productId)
    {
        // Map Stripe product IDs to plan names
        $productMapping = [
            'prod_THEIn7wWoK4Vi0' => 'starter',
            'prod_THEIWxs4BwkyZF' => 'pro',
        ];

        if ($productId && isset($productMapping[$productId])) {
            return $productMapping[$productId];
        }

        // Fallback: check if we can determine from price metadata or default to 'starter'
        return 'starter';
    }
}
