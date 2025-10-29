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
            $table->string('timezone')->default('America/New_York')->after('email');
            $table->enum('match_email_frequency', ['daily', 'weekly', 'never'])->default('weekly')->after('timezone');
            $table->timestamp('last_match_email_sent_at')->nullable()->after('match_email_frequency');
            $table->integer('match_email_count')->default(0)->after('last_match_email_sent_at');
            $table->timestamp('last_engagement_at')->nullable()->after('match_email_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'match_email_frequency',
                'last_match_email_sent_at',
                'match_email_count',
                'last_engagement_at'
            ]);
        });
    }
};
