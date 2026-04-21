<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 運用メモ / コメント。ドメインロジックではなく、チーム運用上の
 * 「なぜこの配置にしたか」「育成目的」等の文脈を残すための軽量 CRUD。
 * 監査ログ対象外、イベント発火なし。
 */
class Note extends Model
{
    use HasUuids;

    protected $table = 'notes';

    protected $fillable = [
        'id',
        'entity_type',
        'entity_id',
        'author_id',
        'body',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
