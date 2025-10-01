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
            // Increase formatted_workplace_location from 255 to 1000 characters
            // Some job postings have very long location lists (multiple cities/states)
            $table->string('formatted_workplace_location', 1000)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Revert back to default string length (255)
            $table->string('formatted_workplace_location')->nullable()->change();
        });
    }
};