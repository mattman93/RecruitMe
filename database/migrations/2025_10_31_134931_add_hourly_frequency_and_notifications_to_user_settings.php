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
        // Add notification_frequency field if it doesn't exist
        if (!Schema::hasColumn('user_settings', 'notification_frequency')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->enum('notification_frequency', ['realtime', 'daily', 'weekly', 'none'])
                    ->default('daily')
                    ->after('auto_apply_relevance');
            });
        }

        // Add max_applications_per_day field if it doesn't exist
        if (!Schema::hasColumn('user_settings', 'max_applications_per_day')) {
            Schema::table('user_settings', function (Blueprint $table) {
                $table->integer('max_applications_per_day')->default(25)->after('notification_frequency');
            });
        }

        // Alter auto_apply_frequency enum to include 'hourly'
        DB::statement("ALTER TABLE user_settings MODIFY COLUMN auto_apply_frequency ENUM('hourly', 'daily', 'weekly') DEFAULT 'hourly'");

        // Update existing Pro users to hourly frequency
        DB::statement("
            UPDATE user_settings
            SET auto_apply_frequency = 'hourly'
            WHERE user_id IN (
                SELECT id FROM users WHERE subscription_plan = 'pro'
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['notification_frequency', 'max_applications_per_day']);
        });

        // Revert auto_apply_frequency enum
        DB::statement("ALTER TABLE user_settings MODIFY COLUMN auto_apply_frequency ENUM('daily', 'weekly') DEFAULT 'daily'");
    }
};
