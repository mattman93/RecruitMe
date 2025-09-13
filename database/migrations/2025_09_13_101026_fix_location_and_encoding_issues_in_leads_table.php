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
            // Increase location field size from 255 to 1000 characters, allow NULL
            $table->string('location', 1000)->nullable()->change();
            
            // Change company_tagline to use utf8mb4 collation and increase size, allow NULL
            $table->text('company_tagline')->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Revert location field size back to 255
            $table->string('location', 255)->change();
            
            // Revert company_tagline back to string
            $table->string('company_tagline', 250)->change();
        });
    }
};
