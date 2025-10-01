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
        Schema::create('user_form_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('field_identifier'); // Question pattern or field identifier
            $table->string('field_type'); // 'yes_no', 'select', 'radio', 'checkbox', 'text'
            $table->text('question_text')->nullable(); // The actual question text for fuzzy matching
            $table->json('response_data'); // User's response (could be boolean, string, array)
            $table->integer('confidence_score')->default(100); // How confident we are in this mapping
            $table->integer('use_count')->default(1); // How many times this preference has been used
            $table->timestamps();
            
            // Index for fast lookups
            $table->index(['user_id', 'field_identifier']);
            $table->index(['user_id', 'field_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_form_preferences');
    }
};
