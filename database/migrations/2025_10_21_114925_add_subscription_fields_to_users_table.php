<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Stripe subscription tracking
            $table->string('stripe_customer_id')->nullable()->index()->after('credits_used');
            $table->string('stripe_subscription_id')->nullable()->index()->after('stripe_customer_id');
            $table->string('subscription_status')->nullable()->after('stripe_subscription_id'); // active, canceled, past_due, unpaid
            $table->string('subscription_plan')->nullable()->after('subscription_status'); // starter, pro
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_customer_id',
                'stripe_subscription_id',
                'subscription_status',
                'subscription_plan',
                'subscription_ends_at'
            ]);
        });
    }
};
