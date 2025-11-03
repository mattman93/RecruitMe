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
        Schema::table('user_settings', function (Blueprint $table) {
            $table->boolean('auto_apply_enabled')->default(false)->after('autonomous_auto_apply');
            $table->enum('auto_apply_frequency', ['daily', 'weekly'])->default('weekly')->after('auto_apply_enabled');
            $table->integer('auto_apply_max_per_period')->default(10)->after('auto_apply_frequency');
            $table->enum('auto_apply_relevance', ['high', 'medium', 'broad'])->default('high')->after('auto_apply_max_per_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_apply_enabled',
                'auto_apply_frequency',
                'auto_apply_max_per_period',
                'auto_apply_relevance',
            ]);
        });
    }
};
