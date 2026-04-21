<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AllocationModel extends Model
{
    use HasUuids;

    protected $table = 'resource_allocations';

    protected $fillable = [
        'id',
        'member_id',
        'project_id',
        'skill_id',
        'allocation_percentage',
        'period_start',
        'period_end',
        'status',
    ];

    protected $casts = [
        'allocation_percentage' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
    ];
}
