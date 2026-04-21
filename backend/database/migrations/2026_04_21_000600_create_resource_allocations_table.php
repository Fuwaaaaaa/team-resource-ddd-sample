<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->uuid('project_id');
            $table->uuid('skill_id');
            $table->unsignedTinyInteger('allocation_percentage');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 16);
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('skill_id')->references('id')->on('skills')->restrictOnDelete();

            $table->index(['member_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['period_start', 'period_end']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_allocations');
    }
};
