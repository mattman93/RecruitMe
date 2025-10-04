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
        Schema::table('leads', function (Blueprint $table) {
            $table->json('discovered_fields')->nullable()->after('security_clearance');
            $table->timestamp('fields_discovered_at')->nullable()->after('discovered_fields');

            $table->index('fields_discovered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['fields_discovered_at']);
            $table->dropColumn(['discovered_fields', 'fields_discovered_at']);
        });
    }
};
