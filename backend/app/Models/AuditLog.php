<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 本番経路で audit_logs に 1 行書き込む唯一の writer。
     *
     * `$timestamps = false` かつ id は uuid7 を採用しているため、 id / created_at は
     * ここで明示生成する。 ドメインイベント経路 ({@see \App\Listeners\RecordAuditLog})
     * も、 アグリゲート状態変化を伴わない直接記録 (例: invite 拒否) も、 すべて本メソッド経由。
     * カラムマッピングを 1 箇所に集約することで、 列追加時の追従漏れを防ぐ。
     *
     * @param  array<string, mixed>  $payload
     */
    public static function record(
        string $eventType,
        string $aggregateType,
        string $aggregateId,
        array $payload,
        ?string $ipAddress,
        ?string $userAgent,
        int|string|null $userId,
    ): self {
        return self::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $userId,
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
