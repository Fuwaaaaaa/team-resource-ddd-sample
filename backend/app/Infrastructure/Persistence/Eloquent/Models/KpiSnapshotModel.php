<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KpiSnapshotModel extends Model
{
    use HasUuids;

    protected $table = 'kpi_snapshots';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'snapshot_date',
        'average_fulfillment_rate',
        'active_project_count',
        'overloaded_member_count',
        'upcoming_ends_this_week',
        'skill_gaps_total',
        'created_at',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'average_fulfillment_rate' => 'float',
        'active_project_count' => 'integer',
        'overloaded_member_count' => 'integer',
        'upcoming_ends_this_week' => 'integer',
        'skill_gaps_total' => 'integer',
        'created_at' => 'datetime',
    ];
}
