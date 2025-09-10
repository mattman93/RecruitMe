<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('job_title');
            $table->string('company');
            $table->string('pay_range');
            $table->text('description');
            $table->string('location')->nullable();
            $table->string('employment_type')->default('Full-time');
            $table->string('experience_level')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('leads');
    }
};