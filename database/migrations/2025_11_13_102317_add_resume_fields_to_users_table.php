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
            $table->string('resume_path')->nullable()->after('avatar');
            $table->unsignedBigInteger('parsed_resume_id')->nullable()->after('resume_path');
            $table->unsignedBigInteger('active_uploaded_file_id')->nullable()->after('parsed_resume_id');

            $table->foreign('parsed_resume_id')->references('id')->on('parsed_resumes')->onDelete('set null');
            $table->foreign('active_uploaded_file_id')->references('id')->on('uploaded_files')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parsed_resume_id']);
            $table->dropForeign(['active_uploaded_file_id']);
            $table->dropColumn(['resume_path', 'parsed_resume_id', 'active_uploaded_file_id']);
        });
    }
};
