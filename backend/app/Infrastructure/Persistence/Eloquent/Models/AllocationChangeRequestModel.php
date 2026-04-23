<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AllocationChangeRequestModel extends Model
{
    use HasUuids;

    protected $table = 'allocation_change_requests';

    protected $fillable = [
        'id',
        'type',
        'payload',
        'requested_by',
        'reason',
        'status',
        'requested_at',
        'decided_by',
        'decided_at',
        'decision_note',
        'resulting_allocation_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];
}
