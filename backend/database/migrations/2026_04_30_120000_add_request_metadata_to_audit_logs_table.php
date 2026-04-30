<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // IPv6 max textual length is 45 chars (incl. zone id, IPv4-mapped form).
            $table->string('ip_address', 45)->nullable();
            // 512 keeps long mobile UA strings intact while bounding row size.
            $table->string('user_agent', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
