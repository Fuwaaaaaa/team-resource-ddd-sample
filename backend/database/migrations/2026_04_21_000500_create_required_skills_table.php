<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('required_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('skill_id');
            $table->unsignedTinyInteger('required_proficiency');
            $table->unsignedInteger('headcount');
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('skill_id')->references('id')->on('skills')->restrictOnDelete();
            $table->unique(['project_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('required_skills');
    }
};
