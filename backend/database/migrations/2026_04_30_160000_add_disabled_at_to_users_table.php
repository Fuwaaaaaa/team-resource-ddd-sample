<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ユーザー無効化フラグ。 null = 有効 / 値あり = 無効化された日時。
            // soft delete ではなく明示的フラグにしている: row は audit_logs の
            // user_id 参照保全のため残す。 login 時にこのカラムが見られて拒否される。
            $table->timestamp('disabled_at')->nullable();
            $table->index('disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['disabled_at']);
            $table->dropColumn('disabled_at');
        });
    }
};
