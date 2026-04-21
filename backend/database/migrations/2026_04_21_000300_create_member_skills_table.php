<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->uuid('skill_id');
            $table->unsignedTinyInteger('proficiency');
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('skill_id')->references('id')->on('skills')->restrictOnDelete();
            $table->unique(['member_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_skills');
    }
};
