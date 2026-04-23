<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // 既存レコード互換のため nullable。未設定の project はフォレキャストで全バケットに demand を寄せる。
            $table->date('planned_start_date')->nullable()->after('status');
            $table->date('planned_end_date')->nullable()->after('planned_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['planned_start_date', 'planned_end_date']);
        });
    }
};
