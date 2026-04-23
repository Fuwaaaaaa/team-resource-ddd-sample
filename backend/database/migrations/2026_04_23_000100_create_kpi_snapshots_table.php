<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('snapshot_date')->unique();
            $table->decimal('average_fulfillment_rate', 5, 1); // 0.0 - 100.0
            $table->unsignedInteger('active_project_count');
            $table->unsignedInteger('overloaded_member_count');
            $table->unsignedInteger('upcoming_ends_this_week');
            $table->unsignedInteger('skill_gaps_total');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_snapshots');
    }
};
