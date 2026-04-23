<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 32); // 'create_allocation' | 'revoke_allocation'
            $table->json('payload');    // 型別の入力パラメータ
            $table->foreignId('requested_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 16)->default('pending'); // 'pending' | 'approved' | 'rejected'
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->uuid('resulting_allocation_id')->nullable(); // approve で作成/revoke された Allocation の ID
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->index('requested_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_change_requests');
    }
};
