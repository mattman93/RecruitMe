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
        Schema::create('discovered_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->onDelete('cascade');
            $table->string('company_name');
            $table->string('company_domain');
            $table->string('email');
            $table->string('contact_type')->default('recruiting'); // hr, recruiting, careers, etc.
            $table->boolean('is_verified')->default(false); // for future verification
            $table->integer('use_count')->default(0); // track how often this contact is used
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['company_domain', 'contact_type']);
            $table->index('lead_id');
            $table->unique(['lead_id', 'email']); // prevent duplicate contacts per lead
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discovered_contacts');
    }
};
