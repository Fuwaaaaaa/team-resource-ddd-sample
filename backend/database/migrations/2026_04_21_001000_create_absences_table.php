<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('type', 32); // AbsenceType enum value
            $table->text('note')->default('');
            $table->boolean('canceled')->default(false);
            $table->timestamps();

            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->cascadeOnDelete();

            $table->index(['member_id', 'start_date']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
    }
};
