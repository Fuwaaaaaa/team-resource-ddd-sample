<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 招待リンク用 single-use token (32 byte → 64 hex char)
            $table->string('invite_token', 64)->nullable()->unique();
            // 24 時間後に失効。null = 招待未発行 or 既に accept 済
            $table->timestamp('invite_token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['invite_token']);
            $table->dropColumn(['invite_token', 'invite_token_expires_at']);
        });
    }
};
