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
        Schema::table('job_site_structures', function (Blueprint $table) {
            $table->string('platform_name')->default('unknown')->after('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_site_structures', function (Blueprint $table) {
            $table->dropColumn('platform_name');
        });
    }
};