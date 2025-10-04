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
            $table->json('discovered_contacts')->nullable()->after('fields_discovered_at');
            $table->timestamp('discovered_contacts_at')->nullable()->after('discovered_contacts');

            $table->index('discovered_contacts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['discovered_contacts_at']);
            $table->dropColumn(['discovered_contacts', 'discovered_contacts_at']);
        });
    }
};
