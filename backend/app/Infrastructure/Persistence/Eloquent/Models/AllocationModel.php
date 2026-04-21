<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class AllocationModel extends Model
{
    protected $table = 'resource_allocations';
    protected $keyType = 'string';
    public $incrementing = false;

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
