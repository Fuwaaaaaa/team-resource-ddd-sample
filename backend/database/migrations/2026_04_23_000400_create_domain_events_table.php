<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event sourcing foundation.
 *
 * audit_logs との分離:
 *   - audit_logs: 人間向けの監査ログ (UI から検索、エクスポート、retention あり)
 *   - domain_events: システム向けのイベントストリーム (順序保証、リプレイ、プロジェクション構築)
 *
 * スキーマ:
 *   - (stream_type, stream_id, stream_version) で per-stream 単調増加シーケンス
 *     → 同一ストリームへの並行追記は unique 制約で後者が失敗 (楽観ロック)
 *   - metadata: JSON で correlation_id (request_id) / causation_id / user_id 等を保持
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('stream_type', 64);      // 'project' | 'member' | 'allocation' ...
            $table->uuid('stream_id');
            $table->unsignedInteger('stream_version'); // 1-origin, per stream sequence
            $table->string('event_type', 128);
            $table->json('event_data');
            $table->json('metadata');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['stream_type', 'stream_id', 'stream_version']);
            $table->index(['stream_type', 'stream_id']);
            $table->index('event_type');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_events');
    }
};
