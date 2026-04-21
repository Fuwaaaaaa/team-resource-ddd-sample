<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceModel extends Model
{
    use HasUuids;

    protected $table = 'absences';

    protected $fillable = [
        'id',
        'member_id',
        'start_date',
        'end_date',
        'type',
        'note',
        'canceled',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'canceled' => 'bool',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'member_id');
    }
}
